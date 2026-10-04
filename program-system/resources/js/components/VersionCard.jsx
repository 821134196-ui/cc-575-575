import React, { useState } from 'react';
import { api } from '../api.js';
import { Badge, Button, Field, Modal, Td, Th, inputCls, useToast } from './ui.jsx';

const mvTypes = {
  print_in: '交货入库', allocate: '分配入口', issue: '入口发放',
  transfer_out: '调出', transfer_in: '调入', scrap: '报废', count_adjust: '盘点调整',
};
const mvTypeColor = {
  print_in: 'text-good-text', allocate: 'text-[#1d4e89]', issue: 'text-ink',
  transfer_out: 'text-serious', transfer_in: 'text-[#1d4e89]',
  scrap: 'text-critical', count_adjust: 'text-[#8a5a00]',
};

export default function VersionCard({ v, entrances, performanceStatus, onChange }) {
  const toast = useToast();
  const [editOpen, setEditOpen] = useState(false);
  const [revOpen, setRevOpen] = useState(false);
  const [printOpen, setPrintOpen] = useState(false);
  const [allocFor, setAllocFor] = useState(null);
  const [editForm, setEditForm] = useState({ file_name: v.file_name, cast_list: v.cast_list });
  const [revForm, setRevForm] = useState({ reason: '', cast_list: v.cast_list });
  const [qty, setQty] = useState(50);
  const [allocForm, setAllocForm] = useState({ entrance_id: entrances[0]?.id, quantity: 50 });
  const [busy, setBusy] = useState(false);

  const s = v.stock;
  const run = async (fn, okMsg) => {
    setBusy(true);
    try {
      await fn();
      toast.push(okMsg);
      onChange();
    } catch (e) {
      toast.fail(e);
    } finally {
      setBusy(false);
    }
  };

  const saveEdit = () => run(
    async () => api.patch(`/api/versions/${v.id}`, editForm),
    v.status === 'draft' ? '草稿已更新' : '修改结果见提示',
  );
  const lock = () => run(() => api.post(`/api/versions/${v.id}/lock`, { by: '编辑' }), '版本已锁定，待印文件固化');
  const revise = () => run(
    async () => api.post(`/api/versions/${v.id}/revision`, revForm),
    '已基于当前版本新建草稿版本，旧节目单保持原名',
  );
  const order = () => run(
    async () => api.post(`/api/versions/${v.id}/print-batches`, { quantity: Number(qty) }),
    `印刷批次已下单（${qty} 份）`,
  );
  const deliver = (b) => run(
    async () => api.post(`/api/print-batches/${b.id}/deliver`),
    '印厂交货回执已登记，数量入库',
  );
  const allocate = () => run(
    async () => api.post(`/api/print-batches/${allocFor.id}/allocate`, {
      entrance_id: Number(allocForm.entrance_id), quantity: Number(allocForm.quantity),
    }),
    '前台已分配到入口',
  );

  const delivered = v.batches.filter((b) => b.status === 'delivered').length;

  return (
    <section className="border border-line rounded-xl bg-surface overflow-hidden">
      <header className="flex flex-wrap items-center gap-3 px-5 py-3 bg-plane border-b border-line">
        <span className="text-lg font-semibold text-theater-700">v{v.version_no}</span>
        <Badge status={v.status} />
        <code className="text-sm bg-white border border-line rounded px-2 py-0.5">{v.file_name}</code>
        {v.file_hash && <span className="text-xs text-ink-3">hash: {v.file_hash}</span>}
        <div className="ml-auto flex flex-wrap gap-2">
          <Button size="sm" tone="ghost" onClick={() => { setEditForm({ file_name: v.file_name, cast_list: v.cast_list }); setEditOpen(true); }}>
            {v.status === 'draft' ? '编辑内容' : '尝试修改（锁定后应拒绝）'}
          </Button>
          {v.status === 'draft' && <Button size="sm" tone="gold" disabled={busy} onClick={lock}>🔒 锁定待印文件</Button>}
          {v.status === 'locked' && (
            <Button size="sm" tone="ghost" onClick={() => { setRevForm({ reason: '', cast_list: v.cast_list }); setRevOpen(true); }}>
              ⎘ 新建版本（名单变化/加场）
            </Button>
          )}
          {v.status === 'locked' && (
            <Button size="sm" onClick={() => { setQty(100); setPrintOpen(true); }} disabled={busy}>＋ 下单印刷</Button>
          )}
        </div>
      </header>

      <div className="grid md:grid-cols-2 gap-4 p-5">
        <div>
          <div className="text-xs text-ink-3 mb-1">{v.change_reason ? `新建原因：${v.change_reason}` : '初版'}</div>
          <pre className="whitespace-pre-wrap text-sm bg-plane border border-line rounded-lg p-3">{v.cast_list}</pre>
          {v.locked_at && <div className="text-xs text-ink-3 mt-2">锁定时间 {v.locked_at} · {v.locked_by}</div>}
        </div>

        <div>
          <div className="text-xs text-ink-3 mb-2">库存分布（核对口径）</div>
          <div className="grid grid-cols-3 gap-2 text-center">
            <Box label="总仓" value={s.warehouse} />
            <Box label="入口合计" value={s.entrance_total} />
            <Box label="已发放" value={s.issued} />
            <Box label="已报废" value={s.scrapped} tone="critical" />
            <Box label="在库结余" value={s.on_hand} tone="good" />
            <Box label="交货总数" value={s.delivered} />
          </div>
          <div className={'mt-2 text-sm text-center rounded-md py-1.5 ' + (s.balanced ? 'bg-green-50 text-good-text' : 'bg-red-50 text-critical')}>
            {s.balanced ? `✓ 账实守恒：交货 ${s.delivered} = 去向合计 ${s.accounted}` : `✗ 差异 ${s.diff}`}
          </div>
        </div>
      </div>

      {v.batches.length > 0 && (
        <div className="px-5 pb-5">
          <div className="text-xs text-ink-3 mb-2">印刷批次与入口领用（{delivered}/{v.batches.length} 已交货）</div>
          <table className="w-full">
            <thead>
              <tr><Th>批次号</Th><Th>下单/交货</Th><Th>回执</Th><Th>状态</Th><Th>入口领用</Th><Th></Th></tr>
            </thead>
            <tbody>
              {v.batches.map((b) => (
                <tr key={b.id}>
                  <Td className="font-mono text-xs">{b.batch_no}</Td>
                  <Td>{b.quantity_ordered} / {b.status === 'delivered' ? b.quantity_delivered : '—'}</Td>
                  <Td className="text-xs text-ink-3">{b.printer_receipt_no || '—'}{b.delivered_at ? <div>{b.delivered_at}</div> : null}</Td>
                  <Td><Badge status={b.status} /></Td>
                  <Td>
                    {b.allocations.length === 0 ? <span className="text-xs text-ink-3">未分配</span> : (
                      <ul className="text-xs space-y-0.5">
                        {b.allocations.map((a) => (
                          <li key={a.id}>{a.entrance.code} {a.entrance.name}：<b>{a.quantity}</b> 份</li>
                        ))}
                      </ul>
                    )}
                  </Td>
                  <Td className="text-right whitespace-nowrap">
                    {b.status === 'ordered' && <Button size="sm" tone="good" disabled={busy} onClick={() => deliver(b)}>登记交货</Button>}
                    {b.status === 'delivered' && (
                      <Button size="sm" tone="ghost" onClick={() => {
                        setAllocFor(b);
                        setAllocForm({ entrance_id: entrances[0]?.id, quantity: Math.min(50, b.quantity_delivered) });
                      }}>分配入口</Button>
                    )}
                  </Td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* 编辑/尝试修改 */}
      <Modal open={editOpen} onClose={() => setEditOpen(false)}
        title={v.status === 'draft' ? '编辑草稿内容' : '锁定后修改 —— 预期被系统拒绝'}>
        <Field label="待印文件名">
          <input className={inputCls} value={editForm.file_name} onChange={(e) => setEditForm({ ...editForm, file_name: e.target.value })} />
        </Field>
        <Field label="演员名单">
          <textarea rows={5} className={inputCls} value={editForm.cast_list} onChange={(e) => setEditForm({ ...editForm, cast_list: e.target.value })} />
        </Field>
        {v.status !== 'draft' && (
          <p className="text-xs text-critical mb-2">
            ⚠ 该版本已{ v.status === 'locked' ? '锁定' : '被替代' }：旧节目单已发出，不能改名顶替。系统将返回 423 拒绝。
          </p>
        )}
        <div className="flex justify-end"><Button tone={v.status === 'draft' ? 'gold' : 'danger'} disabled={busy} onClick={saveEdit}>保存</Button></div>
      </Modal>

      {/* 新建版本 */}
      <Modal open={revOpen} onClose={() => setRevOpen(false)} title="新建节目单版本（旧版保持原名）">
        <Field label="原因（演员临时变化 / 增加场次）">
          <input className={inputCls} value={revForm.reason} onChange={(e) => setRevForm({ ...revForm, reason: e.target.value })}
            placeholder="如：繁漪改由李梅饰演" />
        </Field>
        <Field label="新版演员名单">
          <textarea rows={5} className={inputCls} value={revForm.cast_list} onChange={(e) => setRevForm({ ...revForm, cast_list: e.target.value })} />
        </Field>
        <div className="flex justify-end">
          <Button tone="gold" disabled={busy || !revForm.reason} onClick={revise}>创建 v{v.version_no + 1} 草稿</Button>
        </div>
      </Modal>

      {/* 下单 */}
      <Modal open={printOpen} onClose={() => setPrintOpen(false)} title="向印厂下单">
        <Field label={`印量（当前版本 v${v.version_no}）`}>
          <input type="number" min={1} className={inputCls} value={qty} onChange={(e) => setQty(e.target.value)} />
        </Field>
        <div className="flex justify-end"><Button tone="gold" disabled={busy} onClick={order}>确认下单</Button></div>
      </Modal>

      {/* 分配 */}
      <Modal open={!!allocFor} onClose={() => setAllocFor(null)} title={`前台分配 · 批次 ${allocFor?.batch_no || ''}`}>
        <Field label="入口">
          <select className={inputCls} value={allocForm.entrance_id}
            onChange={(e) => setAllocForm({ ...allocForm, entrance_id: e.target.value })}>
            {entrances.map((en) => <option key={en.id} value={en.id}>{en.code} {en.name}</option>)}
          </select>
        </Field>
        <Field label="分配数量">
          <input type="number" min={1} className={inputCls} value={allocForm.quantity}
            onChange={(e) => setAllocForm({ ...allocForm, quantity: e.target.value })} />
        </Field>
        <div className="flex justify-end"><Button tone="gold" disabled={busy} onClick={allocate}>确认分配</Button></div>
      </Modal>
    </section>
  );
}

function Box({ label, value, tone = 'ink' }) {
  const color = { ink: 'text-ink', good: 'text-good-text', critical: 'text-critical' }[tone];
  return (
    <div className="border border-line rounded-lg py-2 bg-plane">
      <div className="text-[11px] text-ink-3">{label}</div>
      <div className={`text-lg font-semibold tabular-nums ${color}`}>{value}</div>
    </div>
  );
}

export { mvTypes, mvTypeColor };
