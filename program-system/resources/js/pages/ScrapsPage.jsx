import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Badge, Button, Card, Td, Th, useToast } from '../components/ui.jsx';

// 错版报废审批中心
export default function ScrapsPage() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const toast = useToast();

  const load = async () => {
    setRows(await api.get('/api/scraps'));
    setLoading(false);
  };
  useEffect(() => { load(); }, []);

  const act = async (s, action) => {
    try {
      await api.post(`/api/scraps/${s.id}/${action}`, {
        by: '运营主管',
        note: action === 'approve' ? '核实确为错版，同意报废' : '证据不足，驳回',
      });
      toast.push(action === 'approve' ? '已批准并扣减库存' : '已驳回，库存不变');
      load();
    } catch (e) {
      toast.fail(e);
    }
  };

  const pending = rows.filter((r) => r.status === 'pending').length;

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-xl font-semibold">错版报废审批</h1>
        <p className="text-sm text-ink-3 mt-0.5">待审批 {pending} 单 · 只有批准后才扣减库存；驳回不影响库存</p>
      </div>
      <Card>
        <table className="w-full">
          <thead>
            <tr><Th>单号</Th><Th>版本/文件</Th><Th>库位</Th><Th>数量</Th><Th>原因</Th><Th>申请人</Th><Th>状态</Th><Th>审批</Th></tr>
          </thead>
          <tbody>
            {loading && <tr><Td>加载中…</Td></tr>}
            {!loading && rows.length === 0 && <tr><Td className="text-ink-3">暂无报废申请。可在场次页面提交。</Td></tr>}
            {rows.map((s) => (
              <tr key={s.id}>
                <Td className="font-mono text-xs">{s.scrap_no}</Td>
                <Td>{s.version_label}</Td>
                <Td>{s.location_label}</Td>
                <Td className="tabular-nums">{s.quantity}</Td>
                <Td className="text-xs">{s.reason}</Td>
                <Td className="text-xs">{s.requested_by}</Td>
                <Td>
                  <Badge status={s.status} />
                  {s.approved_by && <div className="text-[11px] text-ink-3 mt-1">{s.approved_by} · {s.approved_at}</div>}
                  {s.approval_note && <div className="text-[11px] text-ink-3">{s.approval_note}</div>}
                </Td>
                <Td>
                  {s.status === 'pending' ? (
                    <div className="flex gap-2">
                      <Button size="sm" tone="good" onClick={() => act(s, 'approve')}>批准</Button>
                      <Button size="sm" tone="ghost" onClick={() => act(s, 'reject')}>驳回</Button>
                    </div>
                  ) : <span className="text-xs text-ink-3">已处理</span>}
                </Td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
