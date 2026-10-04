# 剧院节目单印刷与散场余量统计系统

覆盖节目单「编辑定版 → 印厂交货 → 前台分配 → 入口发放 → 散场盘点 → 补印决策」全流程，
核心约束：**锁定后的待印文件不可修改、错版报废必须审批、入口调拨不增加总库存、
入库 = 发放 + 报废 + 剩余 始终可核对**。

技术栈：PHP 8.2 + Laravel 12 + Eloquent + SQLite + React 18（Vite/Tailwind）。

## 一条命令启动

```bash
./start.sh           # 初始化依赖/数据库/前端并启动 → http://127.0.0.1:8000
./start.sh --fresh   # 重置为演示数据后启动
./start.sh demo      # 命令行端到端演示（19 项自动核对，退出码非 0 即有失败）
```

无 root 环境（如本机）会自动使用 `~/local` 下的私有 PHP；全新 Debian 环境缺失 PHP 时，
`start.sh` 会调用 `scripts/install-php.sh` 以解包 .deb 的方式安装，无需 sudo。

## 功能与页面对照

| 角色/场景 | 页面 | 说明 |
| --- | --- | --- |
| 编辑定版 | `/productions`（节目单版本） | 上传待印文件、锁定；演员名单变化/加演时**新建版本**；对锁定版本点「尝试改名/修改」会收到 422 `VERSION_LOCKED`，旧节目单不能改名顶替 |
| 安排场次 | `/performances` | 新建场次；确认场次时锁定所选版本，本场只允许发放该版本，确认后不可换版 |
| 印厂交货 | 场次详情页 | 按批次下单、登记交货（回执来自本地模拟印厂接口 `/api/mock-printer/receipts`，含回执单号/短交数） |
| 前台分配 | 场次详情页 | 已交货批次从总库分配到各入口，总库减、入口加，总库存不变 |
| 入口发放 | 场次详情页 | 只扣本场确认版本的入口库存，超量发放被拒绝 |
| 入口看板 | `/board` | 每 5 秒刷新；卡片显示本场**允许发放的唯一版本**（文件名/指纹/锁定时间）和各入口剩余可发数量 |
| 入口调拨 | 场次详情页 | 一条记录同时写调出(-)与调入(+)，净额恒为 0 |
| 错版报废 | `/scraps` | 提交→待审批（不扣账）→主管批准才扣库存；驳回不动账 |
| 散场盘点 | 场次详情页 | 按入口录入实点结余，显示账面/差异；全部入口盘点后关闭场次，按每场实际结余给出补印建议 |
| 库存核对 | `/reconciliation` | 恒等式 `交货 = 发放 + 报废 + 剩余`，分版本+总账双口径，调拨/分配净额必须为 0，出现负库存即标红 |

场次详情页可一站式追溯：**待印文件（SHA-256 指纹）→ 印刷批次/回执 → 各入口领用 → 发放 → 盘点**。

## 数据模型（11 张表）

```
productions 剧目
├─ program_versions 节目单版本（draft/locked，file_hash，locked_at/by）
│  ├─ print_batches 印刷批次（订货/实交/回执）
│  │  └─ allocations 前台→入口分配
│  ├─ transfers 入口调拨（from/to 同表双向外键）
│  ├─ scraps 报废审批（pending/approved/rejected）
│  └─ stock_movements ★ 库存流水（唯一事实来源，带符号数量）
performances 场次 → program_version（本场允许版本）
├─ issues 入口发放
└─ stock_counts 散场盘点（场次×入口×版本唯一）
entrances 入口
```

库存守恒规则全部在数据库事务内落 `stock_movements` 流水：

| 动作 | 总库 | 入口 | 总库存 |
| --- | --- | --- | --- |
| 印厂交货 deliver | + | | **增加** |
| 前台分配 allocate_out/in | − | +（领入入口） | 不变 |
| 入口发放 issue | | −（发放入口） | **减少** |
| 入口调拨 transfer_out/in | | − / + | 不变（净额 0） |
| 报废 scrap（仅审批通过） | − 或入口 − | | **减少** |

## 交付演示看点

`./start.sh demo` 会自动演完一条完整业务链并输出 19 项核对结果：

1. **锁定后修改**：v1 锁定后改名 → 被 `VERSION_LOCKED` 拒绝；换角走「新建 v2」，v1 原名保留
2. **错版报废**：西门 5 份错版提交后待审批不扣账，主管批准才扣；另一笔被驳回库存不动
3. **入口调拨**：西门→东门 10 份，调出调入同时记账，调拨净额 = 0
4. **库存总数一致**：`交货 500 = 发放 405 + 报废 5 + 剩余 90`，全部版本账实平衡、无负库存

另含重复交货、超量发放、未锁定禁止下单、确认后禁止换版等拦截校验。

## 目录要点

- `server/app/Services/ProgramService.php` — 业务编排（锁定/交货/分配/发放/调拨/报废/盘点）
- `server/app/Services/InventoryService.php` — 流水账与核对恒等式
- `server/app/Services/PrinterService.php` — 印厂回执本地模拟适配器
- `server/app/Console/Commands/DemoRun.php` — 可重复执行的端到端演示/验收脚本
- `server/resources/js/pages/` — React 五个页面
- `server/database/migrations/` — SQLite 表结构
