import React, { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api.js';
import VersionCard, { mvTypeColor, mvTypes } from '../components/VersionCard.jsx';
import { Badge, Button, Card, Field, Modal, StatTile, Td, Th, inputCls, useToast } from '../components/ui.jsx';

const locLabel = (loc, entrances) => {
  if (loc === 'warehouse') return '前台总仓';
  if (loc === 'audience') return '已发放观众';
  if (loc === 'scrapped') return '报废';
  if (loc?.startsWith('entrance:')) {
    const id = Number(loc.split(':')[1]);
    const e = entrances.find((x) => x.id === id);
    return e ? `${e.code} ${e.name}` : `入口 ${id}`;
  }
  return loc;
};

export default function PerformanceDetailPage() {
  const { id } = useParams();
  const toast = useToast();
  const [p, setP] = useState(null);
  const [recon, setRecon] = useState(null);
  const [movements, setMovements] = useState([]);
  const [tab, setTab] = useState('ops');
  const [dlg, setDlg] = useState(null); // issue | transfer | scrap | counts
  const [form, setForm] = useState({});
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    const [detail, r, mv] = await Promise.all([
      api.get(`/api/performances/${id}`),
      api.get(`/api/performances/${id}/reconcile`),
      api.get(`/api/performances/${id}/movements`),
    ]);
    setP(detail);
    setRecon(r);
    setMovements(mv);
  }, [id]);

  useEffect(() => { load(); }, [load]);

  if (!p) return <div className="text-ink-3">加载中…</div>;

  const usableVersions = p.versions.filter((v) => v.status !== 'draft');
  const versionOptions = usableVersions.length
    ? usableVersions : p.versions;

  const openDlg = (kind, defaults = {}) => {
    const base = {
      by: '', note: '', reason: '',
      program_version_id: versionOptions[0]?.id,
      entrance_id: p.entrances[0]?.id,
      from_entrance_id: p.entrances[0]?.id,
      to_entrance_id: p.entrances[1]?.id || p.entrances[0]?.id,
      location: 'warehouse',
      quantity: 10,
      ...defaults,
    };
    setForm(base);
    setDlg(kind);
  };

  const submit = async () => {
    setBusy(true);
    try {
      if (dlg === 'issue') {
        await api.post(`/api/entrances/${form.entrance_id}/issue`, {
          program_version_id: Number(form.program_version_id), quantity: Number(form.quantity), by: form.by || undefined,
        });
        toast.push('已记录发放');
      } else if (dlg === 'transfer') {
        await api.post('/api/transfers', {
          program_version_id: Number(form.program_version_id),
          from_entrance_id: Number(form.from_entrance_id), to_entrance_id: Number(form.to_entrance_id),
          quantity: Number(form.quantity), reason: form.reason || undefined,
        });
        toast.push('调拨完成：调出与调入成对记录，总库存不变');
      } else if (dlg === 'scrap') {
        await api.post('/api/scraps', {
          program_version_id: Number(form.program_version_id), location: form.location,
          quantity: Number(form.quantity), reason: form.reason || '错版报废',
        });
        toast.push('报废申请已提交，等待审批（审批前库存不扣减）');
      } else if (dlg === 'counts') {
        const { counts } = await api.post(`/api/performances/${id}/counts/snapshot`, {});
        for (const c of counts) {
          await api.patch(`/api/counts/${c.id}`, { actual_qty: c.system_qty });
        }
        toast.push(`已完成 ${counts.length} 个库位的散场盘点（默认账实相符）`);
      }
      setDlg(null);
      load();
    } catch (e) {
      toast.fail(e);
    } finally {
      setBusy(false);
    }
  };

  const t = recon?.totals;
  const locationOptions = [{ value: 'warehouse', label: '前台总仓' },
    ...p.entrances.map((e) => ({ value: `entrance:${e.id}`, label: `${e.code} ${e.name}` }))];

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <Link to="/" className="text-sm text-ink-3 hover:text-ink">← 场次列表</Link>
        <Badge status={p.status} />
      </div>
      <div className="flex items-start justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-semibold">{p.title}</h1>
          <div className="text-sm text-ink-3 mt-1">
            {new Date(p.performed_at).toLocaleString('zh-CN', { hour12: false })} · {p.venue}
          </div>
        </div>
        <div className="flex gap-2 flex-wrap">
          <Button tone="ghost" onClick={() => openDlg('issue')}>入口发放</Button>
          <Button tone="ghost" onClick={() => openDlg('transfer')}>入口调拨</Button>
          <Button tone="ghost" onClick={() => openDlg('scrap')}>报废申请</Button>
          <Button tone="gold" onClick={() => openDlg('counts')}>散场盘点</Button>
          <Link to={`/board?performance_id=${id}`}><Button tone="ghost">查看入口看板</Button></Link>
        </div>
      </div>

      {t && (
        <div className="grid grid-cols-5 gap-3">
          <StatTile label="累计交货" value={t.delivered} />
          <StatTile label="在库结余（总仓+入口）" value={t.on_hand} tone="good" />
          <StatTile label="已发放观众" value={t.issued} />
          <StatTile label="已报废" value={t.scrapped} tone="critical" />
          <StatTile label="核对差异" value={t.diff} tone={t.balanced ? 'good' : 'critical'}
            sub={t.balanced ? '✓ 总数一致' : '✗ 账实不符'} />
        </div>
      )}

      <div className="space-y-4">
        {p.versions.map((v) => (
          <VersionCard key={v.id} v={v} entrances={p.entrances} performanceStatus={p.status} onChange={load} />
        ))}
      </div>

      <Card
        title={
          <div className="flex gap-4">
            <button className={tab === 'ops' ? 'text-theater-700 font-semibold' : 'text-ink-3'} onClick={() => setTab('ops')}>
              核对明细
            </button>
            <button className={tab === 'mv' ? 'text-theater-700 font-semibold' : 'text-ink-3'} onClick={() => setTab('mv')}>
              库存流水追溯（{movements.length}）
            </button>
            {p.counts?.length > 0 && (
              <button className={tab === 'counts' ? 'text-theater-700 font-semibold' : 'text-ink-3'} onClick={() => setTab('counts')}>
                散场盘点记录（{p.counts.length}）
              </button>
            )}
          </div>
        }
      >
        {tab === 'ops' && recon && (
          <table className="w-full">
            <thead>
              <tr><Th>版本</Th><Th>交货</Th><Th>总仓</Th><Th>入口</Th><Th>已发放</Th><Th>报废</Th><Th>去向合计</Th><Th>差异</Th></tr>
            </thead>
            <tbody>
              {recon.rows.map((r) => (
                <tr key={r.version_id}>
                  <Td>v{r.version_no} <code className="text-xs">{r.file_name}</code></Td>
                  <Td>{r.delivered}</Td><Td>{r.warehouse}</Td>
                  <Td>{r.entrance_total}
                    <div className="text-[11px] text-ink-3">
                      {Object.entries(r.entrances).map(([loc, q]) => `${locLabel(loc, p.entrances)} ${q}`).join(' · ')}
                    </div>
                  </Td>
                  <Td>{r.issued}</Td><Td>{r.scrapped}</Td><Td>{r.accounted}</Td>
                  <Td className={r.balanced ? 'text-good-text font-medium' : 'text-critical font-medium'}>
                    {r.diff === 0 ? '✓ 0' : `✗ ${r.diff}`}
                  </Td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {tab === 'mv' && (
          <table className="w-full">
            <thead>
              <tr><Th>时间</Th><Th>类型</Th><Th>版本</Th><Th>调出库位</Th><Th>调入库位</Th><Th>数量</Th><Th>操作人</Th></tr>
            </thead>
            <tbody>
              {movements.map((m) => (
                <tr key={m.id}>
                  <Td className="text-xs whitespace-nowrap">{m.at}</Td>
                  <Td><span className={'text-xs font-medium ' + (mvTypeColor[m.type] || '')}>{mvTypes[m.type] || m.type}</span></Td>
                  <Td>{p.versions.find((x) => x.id === m.version_id)?.version_no ? `v${p.versions.find((x) => x.id === m.version_id).version_no}` : m.version_id}</Td>
                  <Td className="text-xs">{m.from ? locLabel(m.from, p.entrances) : '—'}</Td>
                  <Td className="text-xs">{m.to ? locLabel(m.to, p.entrances) : '—'}</Td>
                  <Td className="tabular-nums">{m.quantity}</Td>
                  <Td className="text-xs">{m.operator}</Td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {tab === 'counts' && (
          <table className="w-full">
            <thead><tr><Th>版本</Th><Th>库位</Th><Th>账面</Th><Th>实点</Th><Th>差异</Th><Th>时间</Th><Th>备注</Th></tr></thead>
            <tbody>
              {p.counts.map((c) => {
                const v = p.versions.find((x) => x.id === c.program_version_id);
                const d = c.actual_qty - c.system_qty;
                return (
                  <tr key={c.id}>
                    <Td>{v ? `v${v.version_no}` : c.program_version_id}</Td>
                    <Td>{locLabel(c.location, p.entrances)}</Td>
                    <Td>{c.system_qty}</Td><Td>{c.actual_qty}</Td>
                    <Td className={d === 0 ? 'text-good-text' : 'text-critical'}>{d === 0 ? '✓' : (d > 0 ? `+${d}` : d)}</Td>
                    <Td className="text-xs">{c.counted_at}</Td>
                    <Td className="text-xs text-ink-3">{c.note || ''}</Td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </Card>

      {/* 操作弹窗 */}
      <Modal open={dlg !== null} onClose={() => setDlg(null)} title={
        { issue: '入口发放给观众', transfer: '入口之间调拨', scrap: '错版报废申请', counts: '散场盘点' }[dlg] || ''
      }>
        {dlg === 'issue' && (
          <>
            <Field label="入口">
              <select className={inputCls} value={form.entrance_id} onChange={(e) => setForm({ ...form, entrance_id: e.target.value })}>
                {p.entrances.map((e) => <option key={e.id} value={e.id}>{e.code} {e.name}</option>)}
              </select>
            </Field>
            <Field label="发放版本">
              <select className={inputCls} value={form.program_version_id} onChange={(e) => setForm({ ...form, program_version_id: e.target.value })}>
                {versionOptions.map((v) => <option key={v.id} value={v.id}>v{v.version_no} {v.file_name}（{v.status === 'locked' ? '当前有效' : '旧版'}）</option>)}
              </select>
            </Field>
            <Field label="本次发放数量（不能超过入口余量）"><input type="number" min={1} className={inputCls} value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} /></Field>
          </>
        )}
        {dlg === 'transfer' && (
          <>
            <Field label="节目单版本">
              <select className={inputCls} value={form.program_version_id} onChange={(e) => setForm({ ...form, program_version_id: e.target.value })}>
                {versionOptions.map((v) => <option key={v.id} value={v.id}>v{v.version_no} {v.file_name}</option>)}
              </select>
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="调出入口">
                <select className={inputCls} value={form.from_entrance_id} onChange={(e) => setForm({ ...form, from_entrance_id: e.target.value })}>
                  {p.entrances.map((e) => <option key={e.id} value={e.id}>{e.code} {e.name}</option>)}
                </select>
              </Field>
              <Field label="调入入口">
                <select className={inputCls} value={form.to_entrance_id} onChange={(e) => setForm({ ...form, to_entrance_id: e.target.value })}>
                  {p.entrances.map((e) => <option key={e.id} value={e.id}>{e.code} {e.name}</option>)}
                </select>
              </Field>
            </div>
            <Field label="调拨数量"><input type="number" min={1} className={inputCls} value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} /></Field>
            <Field label="原因"><input className={inputCls} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} placeholder="如：B 口人流超预期" /></Field>
            <p className="text-xs text-ink-3 mb-2">调拨同时生成「调出 + 调入」两条流水，不会增加总库存。</p>
          </>
        )}
        {dlg === 'scrap' && (
          <>
            <Field label="节目单版本">
              <select className={inputCls} value={form.program_version_id} onChange={(e) => setForm({ ...form, program_version_id: e.target.value })}>
                {versionOptions.map((v) => <option key={v.id} value={v.id}>v{v.version_no} {v.file_name}</option>)}
              </select>
            </Field>
            <Field label="报废库位">
              <select className={inputCls} value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })}>
                {locationOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
            </Field>
            <Field label="报废数量"><input type="number" min={1} className={inputCls} value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} /></Field>
            <Field label="原因（错版说明）"><input className={inputCls} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} placeholder="如：内页与锁定文件不符" /></Field>
            <p className="text-xs text-[#8a5a00] mb-2">提交后状态为「待审批」，仅审批通过才扣减库存。</p>
          </>
        )}
        {dlg === 'counts' && (
          <p className="text-sm text-ink-2">
            将为所有在仓/入口库位生成账面快照并按账实相符完成盘点；盘亏盘盈可在盘点记录表中体现。
            演示实际差异请使用命令 <code>php artisan theater:demo</code>。
          </p>
        )}
        <div className="flex justify-end gap-2 mt-3">
          <Button tone="ghost" onClick={() => setDlg(null)}>取消</Button>
          <Button tone="gold" disabled={busy} onClick={submit}>确认</Button>
        </div>
      </Modal>
    </div>
  );
}
