#!/usr/bin/env bash
# HTTP 端到端验证：走真实 API 路径（与前端调用一致），覆盖
# 锁定后修改(423) / 错版报废审批 / 入口调拨不增库存 / 最终核对一致
set -uo pipefail
BASE="${BASE:-http://127.0.0.1:8000}"
PASS=0; FAIL=0
ok()   { if [ "$1" = "$2" ]; then PASS=$((PASS+1)); echo "  ✅ $3"; else FAIL=$((FAIL+1)); echo "  ❌ $3 (期望 $2 实际 $1) ${4:-}"; fi; }
jget() { python3 -c "import json,sys; d=json.load(sys.stdin); print(eval('d'+sys.argv[1]))" "$1" 2>/dev/null; }

echo "1) 创建演出"
PID=$(curl -s -X POST "$BASE/api/performances" -H 'Content-Type: application/json' \
  -d '{"title":"API验证场","performed_at":"2026-10-10 19:30","venue":"实验剧场","file_name":"api-v1.pdf","cast_list":"甲：A 乙：B"}' \
  | jget "['id']")
echo "   performance_id=$PID"
VID=$(curl -s "$BASE/api/performances/$PID" | jget "['versions'][0]['id']")
echo "   version_id=$VID"

echo "2) 草稿修改应成功(200)"
code=$(curl -s -o /tmp/r.json -w "%{http_code}" -X PATCH "$BASE/api/versions/$VID" -H 'Content-Type: application/json' -d '{"cast_list":"甲：A1 乙：B"}')
ok "$code" 200 "草稿可编辑"

echo "3) 锁定"
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/api/versions/$VID/lock" -H 'Content-Type: application/json' -d '{"by":"编辑"}')
ok "$code" 200 "锁定成功"

echo "4) 锁定后修改必须被拒绝(423 VersionLockedException)"
code=$(curl -s -o /tmp/r.json -w "%{http_code}" -X PATCH "$BASE/api/versions/$VID" -H 'Content-Type: application/json' -d '{"file_name":"HACKED.pdf"}')
ok "$code" 423 "锁定版本拒绝改名"
python3 -c "import json;print('     服务端消息:', json.load(open('/tmp/r.json'))['message'])"

echo "5) 下单印刷 300"
BID=$(curl -s -X POST "$BASE/api/versions/$VID/print-batches" -H 'Content-Type: application/json' -d '{"quantity":300}' | jget "['batch']['id']")
echo "   batch_id=$BID"

echo "6) 印厂交货（回执经本地模拟接口 /api/mock/printer/receipt）"
# 先直接问模拟接口本批次应收多少（确定性回执），交货数量必须与回执一致
EXPECT_DELIVERED=$(curl -s -X POST "$BASE/api/mock/printer/receipt" -H 'Content-Type: application/json' \
  -d "{\"batch_no\":\"PB-1004-0001\",\"quantity_ordered\":300}" | jget "['data']['quantity_delivered']")
RESP=$(curl -s -X POST "$BASE/api/print-batches/$BID/deliver")
QDEL=$(echo "$RESP" | jget "['batch']['quantity_delivered']")
SRC=$(echo "$RESP" | jget "['receipt']['source']")
echo "   交货 $QDEL 份（模拟回执 $EXPECT_DELIVERED），回执来源 $SRC"
ok "$QDEL" "$EXPECT_DELIVERED" "交货数量与印厂回执一致"
[ "$SRC" = "mock-api" ] && ok "$SRC" "mock-api" "回执来自本地模拟接口"

echo "7) 前台分配 A=120 B=100"
curl -s -o /dev/null -X POST "$BASE/api/print-batches/$BID/allocate" -H 'Content-Type: application/json' -d '{"entrance_id":1,"quantity":120}'
curl -s -o /dev/null -X POST "$BASE/api/print-batches/$BID/allocate" -H 'Content-Type: application/json' -d '{"entrance_id":2,"quantity":100}'

echo "8) 超量分配必须被拒绝(422)"
code=$(curl -s -o /tmp/r.json -w "%{http_code}" -X POST "$BASE/api/print-batches/$BID/allocate" -H 'Content-Type: application/json' -d '{"entrance_id":3,"quantity":99999}')
ok "$code" 422 "总仓余量不足时拒绝分配"

echo "9) 入口发放 A=100 B=60"
curl -s -o /dev/null -X POST "$BASE/api/entrances/1/issue" -H 'Content-Type: application/json' -d "{\"program_version_id\":$VID,\"quantity\":100}"
curl -s -o /dev/null -X POST "$BASE/api/entrances/2/issue" -H 'Content-Type: application/json' -d "{\"program_version_id\":$VID,\"quantity\":60}"

echo "10) 错版报废：申请 pending 库存不动；批准后扣减"
SID=$(curl -s -X POST "$BASE/api/scraps" -H 'Content-Type: application/json' \
  -d "{\"program_version_id\":$VID,\"location\":\"entrance:2\",\"quantity\":8,\"reason\":\"错版测试\"}" | jget "['scrap']['id']")
BA=$(curl -s "$BASE/api/performances/$PID/entrances/2/board" | jget "['allowed_versions'][0]['remaining']")
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/api/scraps/$SID/approve" -H 'Content-Type: application/json' -d '{"by":"主管"}')
ok "$code" 200 "报废批准成功"
BB=$(curl -s "$BASE/api/performances/$PID/entrances/2/board" | jget "['allowed_versions'][0]['remaining']")
ok "$BA" 40 "审批前 B 余量=40"
ok "$BB" 32 "审批后 B 余量=32（扣减8）"

# 已批准的报废单不能重复审批
code=$(curl -s -o /tmp/r.json -w "%{http_code}" -X POST "$BASE/api/scraps/$SID/approve" -H 'Content-Type: application/json')
ok "$code" 422 "报废单不能重复审批"

echo "11) 入口调拨 A→B 15：调出/调入成对且总数不变"
OH_BEFORE=$(curl -s "$BASE/api/performances/$PID/reconcile" | jget "['totals']['on_hand']")
code=$(curl -s -o /tmp/r.json -w "%{http_code}" -X POST "$BASE/api/transfers" -H 'Content-Type: application/json' \
  -d "{\"program_version_id\":$VID,\"from_entrance_id\":1,\"to_entrance_id\":2,\"quantity\":15}")
ok "$code" 201 "调拨单据创建成功"
REC=$(curl -s "$BASE/api/performances/$PID/reconcile")
OH_AFTER=$(echo "$REC" | jget "['totals']['on_hand']")
DIFF=$(echo "$REC" | jget "['totals']['diff']")
BAL=$(echo "$REC" | jget "['totals']['balanced']")
ok "$OH_BEFORE" "$OH_AFTER" "调拨前后在库结余不变 ($OH_BEFORE→$OH_AFTER)"
ok "$DIFF" 0 "核对差异为 0"
ok "$BAL" "True" "库存总数一致 balanced=true"

# 超量调拨拒绝
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/api/transfers" -H 'Content-Type: application/json' \
  -d "{\"program_version_id\":$VID,\"from_entrance_id\":1,\"to_entrance_id\":2,\"quantity\":99999}")
ok "$code" 422 "超余量调出被拒绝"

echo "12) 看板口径（A 口剩余 5 = 120-100-15）"
A_REM=$(curl -s "$BASE/api/performances/$PID/entrances/1/board" | jget "['allowed_versions'][0]['remaining']")
ok "$A_REM" 5 "A 口看板剩余正确"

echo ""
echo "================================================================"
echo "核对明细："
echo "$REC" | python3 -c "
import json,sys
d=json.load(sys.stdin)
for r in d['rows']:
    print(f\"  v{r['version_no']} {r['file_name']}: 交货{r['delivered']} = 总仓{r['warehouse']}+入口{r['entrance_total']}+发放{r['issued']}+报废{r['scrapped']} = {r['accounted']}  差异{r['diff']}\")
"
echo "通过 $PASS / 失败 $FAIL"
[ "$FAIL" = 0 ]
