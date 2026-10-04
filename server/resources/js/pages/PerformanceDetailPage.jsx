import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api.js';
import { Badge, Button, Card, Field, Modal, Stat, inputCls, ErrorText, useToast } from '../components/ui.jsx';

export default function PerformanceDetailPage() {
    const { id } = useParams();
    const toast = useToast();
    const [data, setData] = useState(null);
    const [productions, setProductions] = useState(null);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [versionId, setVersionId] = useState('');
    const [receipt, setReceipt] = useState(null);
    const [error, setError] = useState(null);
    const [closeResult, setCloseResult] = useState(null);

    const load = async () => {
        const d = await api.get(`/performances/${id}`);
        setData(d);
        if (!d.performance.program_version_id) {
            const p = await api.get(`/productions/${d.performance.production_id}`);
            setProductions(p);
            setVersionId(p.versions[0]?.id || '');
        }
    };
    useEffect(() => { load(); }, [id]);

    const guard = (fn) => async () => {
        setError(null);
        try { await fn(); await load(); }
        catch (e) { setError(e); toast(e.message + (e.errorCode === 'VERSION_LOCKED' ? '（需新建版本）' : ''), 'error'); }
    };

    if (!data) return <div className="text-slate-400">加载中…</div>;
    const { performance: show, entrance_ledger: ledger, inventory } = data;
    const version = show.program_version;
    const batches = version?.print_batches || [];

    const confirmShow = guard(async () => {
        await api.post(`/performances/${id}/confirm`, { program_version_id: Number(versionId) });
        setConfirmOpen(false);
        toast('场次已确认，待印文件已锁定，本场只允许发放该版本');
    });

    const orderPrint = guard(async (qty) => {
        await api.post(`/versions/${version.id}/print-orders`, { ordered_qty: qty });
        toast(`已向印厂下单 ${qty} 份`);
    });

    const deliver = guard(async (batchId, qty) => {
        const res = await api.post(`/batches/${batchId}/deliver`, { delivered_qty: qty });
        setReceipt(res.receipt);
        toast(`印厂交货 ${qty} 份，回执 ${res.receipt.receipt_no}`);
    });

    const allocate = guard(async (batchId, entranceId, qty) => {
        await api.post(`/batches/${batchId}/allocations`, { entrance_id: entranceId, quantity: qty });
        toast(`已从总库分配 ${qty} 份至入口`);
    });

    const issue = guard(async (entranceId, qty) => {
        await api.post(`/performances/${id}/issues`, { entrance_id: entranceId, quantity: qty });
        toast(`已发放 ${qty} 份`);
    });

    const transfer = guard(async (fromId, toId, qty) => {
        await api.post(`/versions/${version.id}/transfers`, {
            from_entrance_id: fromId, to_entrance_id: toId, quantity: qty,
        });
        toast('调拨完成：调出与调入已同时记账，总库存不变');
    });

    const count = guard(async (entranceId, qty) => {
        const r = await api.post(`/performances/${id}/counts`, { entrance_id: entranceId, remaining_qty: qty });
        toast(`已录入结余 ${qty}（账面 ${r.book_remaining}，差异 ${r.variance}）`);
    });

    const close = guard(async () => {
        const r = await api.post(`/performances/${id}/close`, {});
        setCloseResult(r);
        toast('场次已关闭');
    });

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <Link to="/performances" className="text-sm text-slate-500 hover:text-indigo-600">← 返回场次列表</Link>
                    <h2 className="text-xl font-bold mt-1">{show.name}</h2>
                    <div className="text-sm text-slate-500 mt-0.5">
                        {show.production?.name} · {new Date(show.performed_at).toLocaleString('zh-CN')} ·{' '}
                        <Badge tone={show.status === 'scheduled' ? 'amber' : show.status === 'confirmed' ? 'sky' : 'slate'}>
                            {show.status === 'scheduled' ? '待确认' : show.status === 'confirmed' ? '已确认' : '已散场盘点'}
                        </Badge>
                    </div>
                </div>
                {show.status === 'scheduled' && <Button onClick={() => setConfirmOpen(true)}>确认演出信息并锁定文件</Button>}
                {show.status === 'confirmed' && <Button tone="warn" onClick={close}>完成全部盘点并关闭场次</Button>}
            </div>

            {error && <div className="bg-rose-50 border border-rose-200 text-rose-700 rounded-lg px-4 py-3 text-sm">
                {error.message}
                {Object.values(error.errors || {}).flat().map((m, i) => <div key={i} className="text-xs">· {m}</div>)}
            </div>}

            {!version && (
                <Card>
                    <p className="text-sm text-slate-500">本场尚未确认允许发放的节目单版本。确认后待印文件将锁定，不可再修改。</p>
                </Card>
            )}

            {version && (
                <>
                    {/* 版本与待印文件 */}
                    <Card title="允许发放版本 · 待印文件" extra={<Badge tone={version.status === 'locked' ? 'emerald' : 'amber'}>
                        {version.status === 'locked' ? '已锁定' : '草稿'}
                    </Badge>}>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <Info label="版本" value={`v${version.version_no} ${version.label}`} />
                            <Info label="变更原因" value={version.change_reason || '—'} />
                            <Info label="待印文件" value={version.file_name || '未上传'} />
                            <Info label="文件指纹 SHA-256" value={version.file_hash ? version.file_hash.slice(0, 16) + '…' : '—'} />
                        </div>
                        {version.status === 'locked' && (
                            <p className="text-xs text-emerald-700 bg-emerald-50 rounded-md px-3 py-2 mt-3">
                                🔒 已锁定（{version.locked_by} · {new Date(version.locked_at).toLocaleString('zh-CN')}）：
                                文件不可修改、不可改名顶替；演员名单变化请到「节目单版本」新建版本。
                            </p>
                        )}
                    </Card>

                    {/* 库存汇总 */}
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <Stat label="印厂交货" value={inventory.totals.delivered} tone="blue" />
                        <Stat label="前台总库" value={inventory.totals.front_stock} />
                        <Stat label="各入口库存" value={inventory.totals.entrance_stock} />
                        <Stat label="已发放" value={inventory.totals.issued} tone="amber" />
                        <Stat label="已报废" value={inventory.totals.scrapped} tone="rose" />
                    </div>

                    {/* 印刷批次与分配 */}
                    <Card title="印刷批次与前台分配">
                        <BatchTable
                            batches={batches}
                            entrances={ledger}
                            onOrder={orderPrint}
                            onDeliver={deliver}
                            onAllocate={allocate}
                            closed={show.status !== 'confirmed'}
                        />
                    </Card>

                    {/* 入口作业 */}
                    <Card title="入口发放与调拨">
                        <EntranceOps
                            ledger={ledger}
                            closed={show.status !== 'confirmed'}
                            onIssue={issue}
                            onTransfer={transfer}
                        />
                    </Card>

                    {/* 散场盘点 */}
                    <Card title="散场盘点（实际结余 → 补印依据）">
                        <CountTable ledger={ledger} disabled={show.status === 'scheduled'} done={show.status === 'closed'} onCount={count} />
                    </Card>
                </>
            )}

            {/* 确认版本弹窗 */}
            <Modal open={confirmOpen} title="确认本场允许发放的版本" onClose={() => setConfirmOpen(false)}
                footer={<><Button tone="ghost" onClick={() => setConfirmOpen(false)}>取消</Button>
                    <Button onClick={confirmShow}>确认并锁定</Button></>}>
                {productions && (
                    <>
                        <p className="text-sm text-slate-500">确认后所选版本的待印文件立即锁定，本场只允许发放该版本。</p>
                        <Field label="节目单版本">
                            <select className={inputCls} value={versionId} onChange={(e) => setVersionId(e.target.value)}>
                                {productions.versions.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        v{v.version_no} {v.label}（{v.status === 'locked' ? '已锁定' : '草稿'}）{v.file_name ? '' : ' · 无文件'}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <ErrorText error={error} field="file" />
                    </>
                )}
            </Modal>

            {/* 印厂回执弹窗 */}
            <Modal open={!!receipt} title="印厂交货回执（本地模拟接口）" onClose={() => setReceipt(null)}
                footer={<Button onClick={() => setReceipt(null)}>知道了</Button>}>
                {receipt && <div className="text-sm space-y-2">
                    <Info label="回执单号" value={receipt.receipt_no} />
                    <Info label="交货数量" value={`${receipt.delivered_qty} 份`} />
                    <Info label="交货时间" value={new Date(receipt.delivered_at).toLocaleString('zh-CN')} />
                    <Info label="数据来源" value={receipt.source} />
                </div>}
            </Modal>

            {/* 关闭场次/补印建议弹窗 */}
            <Modal open={!!closeResult} title="散场汇总与补印建议" onClose={() => setCloseResult(null)}
                footer={<Button onClick={() => setCloseResult(null)}>知道了</Button>}>
                {closeResult && (
                    <div className="space-y-3 text-sm">
                        <div className="grid grid-cols-2 gap-3">
                            <Info label="交货总数" value={closeResult.delivered_total} />
                            <Info label="发放总数" value={closeResult.issued_total} />
                            <Info label="报废总数" value={closeResult.scrapped_total} />
                            <Info label="入口实点结余" value={closeResult.actual_remaining_at_entrances} />
                            <Info label="前台总库结余" value={closeResult.front_stock} />
                            <Info label="总结余" value={closeResult.total_remaining} />
                        </div>
                        <p className="bg-slate-50 rounded-md px-3 py-2 font-medium">
                            核对：{closeResult.delivered_total} = {closeResult.issued_total} 发放 + {closeResult.scrapped_total} 报废 + {closeResult.total_remaining} 剩余
                        </p>
                        <p className={'rounded-md px-3 py-2 font-medium ' +
                            (closeResult.reprint_suggested ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700')}>
                            {closeResult.reprint_suggested ? '🖨 ' : '✓ '}{closeResult.reprint_reason}
                        </p>
                    </div>
                )}
            </Modal>
        </div>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <div className="text-xs text-slate-400">{label}</div>
            <div className="text-slate-800 mt-0.5 break-all">{value}</div>
        </div>
    );
}

function BatchTable({ batches, entrances, onOrder, onDeliver, onAllocate, closed }) {
    const [qty, setQty] = useState({});
    const num = (k) => { const v = parseInt(qty[k], 10); return Number.isFinite(v) && v > 0 ? v : null; };

    return (
        <div className="space-y-4">
            {batches.length === 0 && <p className="text-sm text-slate-400">尚无印刷批次。</p>}
            {batches.map((b) => {
                const allocated = b.allocations.reduce((s, a) => s + a.quantity, 0);
                return (
                    <div key={b.id} className="border border-slate-200 rounded-lg p-4">
                        <div className="flex flex-wrap items-center gap-3 text-sm">
                            <span className="font-semibold">批次 {b.batch_no}</span>
                            <Badge tone={b.status === 'delivered' ? 'emerald' : 'amber'}>
                                {b.status === 'delivered' ? '已交货' : '已订货'}
                            </Badge>
                            <span className="text-slate-500">订货 {b.ordered_qty} · 实交 {b.delivered_qty}</span>
                            {b.receipt_no && <span className="text-slate-500">回执 {b.receipt_no}</span>}
                            <span className="ml-auto">
                                {b.status === 'ordered' && !closed && (
                                    <>
                                        <input type="number" min={0} max={b.ordered_qty} placeholder={`实交(≤${b.ordered_qty})`}
                                            className={inputCls + ' w-32 inline-block mr-2'}
                                            onChange={(e) => setQty({ ...qty, ['d' + b.id]: e.target.value })} />
                                        <Button size="sm" tone="success"
                                            disabled={!num('d' + b.id) && num('d' + b.id) !== 0}
                                            onClick={() => onDeliver(b.id, num('d' + b.id) ?? b.ordered_qty)}>
                                            印厂交货登记
                                        </Button>
                                    </>
                                )}
                            </span>
                        </div>
                        {b.status === 'delivered' && (
                            <div className="mt-3">
                                <div className="text-xs text-slate-400 mb-2">
                                    已从总库分配 {allocated}，本批总库留存 {b.delivered_qty - allocated}
                                </div>
                                <div className="grid md:grid-cols-3 gap-2">
                                    {entrances.map((e) => (
                                        <div key={e.id} className="flex items-center gap-2">
                                            <span className="text-sm w-16 shrink-0">{e.name}</span>
                                            <input type="number" min={1} placeholder="数量"
                                                className={inputCls + ' py-1.5'} disabled={closed}
                                                onChange={(ev) => setQty({ ...qty, [`a${b.id}_${e.id}`]: ev.target.value })} />
                                            <Button size="sm" tone="ghost" disabled={closed}
                                                onClick={() => onAllocate(b.id, e.id, num(`a${b.id}_${e.id}`))}>
                                                分配
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                                {b.allocations.length > 0 && (
                                    <div className="mt-2 text-xs text-slate-500">
                                        领用记录：{b.allocations.map((a) => `${a.entrance.name} ${a.quantity}`).join('；')}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                );
            })}
            {!closed && batches.length >= 0 && (
                <QuickAction label="新批次订货" placeholder="订货数量" onSubmit={(v) => onOrder(v)} disabled={batches.length === 0 && false} />
            )}
        </div>
    );
}

function QuickAction({ label, placeholder, onSubmit }) {
    const [v, setV] = useState('');
    return (
        <div className="flex items-center gap-2 pt-2">
            <span className="text-sm text-slate-600">{label}</span>
            <input type="number" min={1} placeholder={placeholder} className={inputCls + ' w-40 py-1.5'}
                value={v} onChange={(e) => setV(e.target.value)} />
            <Button size="sm" disabled={!(parseInt(v, 10) > 0)}
                onClick={async () => { await onSubmit(parseInt(v, 10)); setV(''); }}>
                提交
            </Button>
        </div>
    );
}

function EntranceOps({ ledger, closed, onIssue, onTransfer }) {
    const [vals, setVals] = useState({});
    const [t, setT] = useState({ from: ledger[0]?.id, to: ledger[1]?.id, qty: '' });

    return (
        <div className="space-y-4">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-slate-500 border-b border-slate-100">
                        <th className="py-2 pr-3 font-medium">入口</th>
                        <th className="py-2 pr-3 font-medium">累计领/调入</th>
                        <th className="py-2 pr-3 font-medium">调出</th>
                        <th className="py-2 pr-3 font-medium">报废</th>
                        <th className="py-2 pr-3 font-medium">本场已发放</th>
                        <th className="py-2 pr-3 font-medium">当前剩余可发</th>
                        <th className="py-2 font-medium">发放操作</th>
                    </tr>
                </thead>
                <tbody>
                    {ledger.map((e) => (
                        <tr key={e.id} className="border-b border-slate-50">
                            <td className="py-2 pr-3 font-medium">{e.name}</td>
                            <td className="py-2 pr-3">{e.total_in ?? '—'}</td>
                            <td className="py-2 pr-3">{e.transfer_out ?? '—'}</td>
                            <td className="py-2 pr-3">{e.scrapped ?? '—'}</td>
                            <td className="py-2 pr-3">{e.issued_for_performance}</td>
                            <td className="py-2 pr-3 font-semibold text-indigo-600">{e.current_stock ?? '—'}</td>
                            <td className="py-2">
                                <div className="flex gap-2">
                                    <input type="number" min={1} placeholder="发放数" disabled={closed}
                                        className={inputCls + ' w-24 py-1.5'}
                                        value={vals[e.id] || ''}
                                        onChange={(ev) => setVals({ ...vals, [e.id]: ev.target.value })} />
                                    <Button size="sm" disabled={closed || !(parseInt(vals[e.id], 10) > 0)}
                                        onClick={async () => { await onIssue(e.id, parseInt(vals[e.id], 10)); setVals({ ...vals, [e.id]: '' }); }}>
                                        发放
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="bg-slate-50 rounded-lg p-4">
                <div className="text-sm font-medium text-slate-700 mb-2">入口之间调拨（调出与调入同时记录，不增加总库存）</div>
                <div className="flex flex-wrap items-center gap-2">
                    <select className={inputCls + ' w-32 py-1.5'} value={t.from}
                        onChange={(e) => setT({ ...t, from: Number(e.target.value) })} disabled={closed}>
                        {ledger.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <span className="text-slate-400">→</span>
                    <select className={inputCls + ' w-32 py-1.5'} value={t.to}
                        onChange={(e) => setT({ ...t, to: Number(e.target.value) })} disabled={closed}>
                        {ledger.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <input type="number" min={1} placeholder="数量" disabled={closed}
                        className={inputCls + ' w-28 py-1.5'} value={t.qty}
                        onChange={(e) => setT({ ...t, qty: e.target.value })} />
                    <Button size="sm" tone="ghost" disabled={closed || !(parseInt(t.qty, 10) > 0) || t.from === t.to}
                        onClick={async () => { await onTransfer(t.from, t.to, parseInt(t.qty, 10)); setT({ ...t, qty: '' }); }}>
                        调拨
                    </Button>
                    {t.from === t.to && <span className="text-xs text-rose-500">出入入口不能相同</span>}
                </div>
            </div>
        </div>
    );
}

function CountTable({ ledger, disabled, done, onCount }) {
    const [vals, setVals] = useState({});
    return (
        <div>
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-slate-500 border-b border-slate-100">
                        <th className="py-2 pr-3 font-medium">入口</th>
                        <th className="py-2 pr-3 font-medium">账面剩余</th>
                        <th className="py-2 pr-3 font-medium">散场实点结余</th>
                        <th className="py-2 font-medium">盘点录入</th>
                    </tr>
                </thead>
                <tbody>
                    {ledger.map((e) => (
                        <tr key={e.id} className="border-b border-slate-50">
                            <td className="py-2 pr-3 font-medium">{e.name}</td>
                            <td className="py-2 pr-3">{e.current_stock ?? '—'}</td>
                            <td className="py-2 pr-3">
                                {e.counted_remaining != null
                                    ? <Badge tone="emerald">{e.counted_remaining}</Badge>
                                    : <span className="text-slate-400">未盘点</span>}
                            </td>
                            <td className="py-2">
                                <div className="flex gap-2">
                                    <input type="number" min={0} placeholder="实点数"
                                        disabled={disabled || done}
                                        className={inputCls + ' w-28 py-1.5'} value={vals[e.id] ?? ''}
                                        onChange={(ev) => setVals({ ...vals, [e.id]: ev.target.value })} />
                                    <Button size="sm" disabled={disabled || done || vals[e.id] === '' || vals[e.id] === undefined}
                                        onClick={async () => { await onCount(e.id, parseInt(vals[e.id], 10)); }}>
                                        提交盘点
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {done && <p className="text-xs text-slate-400 mt-2">本场已关闭，盘点数据不可修改。</p>}
        </div>
    );
}
