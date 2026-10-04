import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Badge, Button, Card, Field, Modal, inputCls, useToast } from '../components/ui.jsx';

const toneMap = { pending: 'amber', approved: 'emerald', rejected: 'rose' };
const labelMap = { pending: '待审批', approved: '已批准', rejected: '已驳回' };

export default function ScrapsPage() {
    const toast = useToast();
    const [scraps, setScraps] = useState([]);
    const [versions, setVersions] = useState([]);
    const [entrances, setEntrances] = useState([]);
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({ version_id: '', quantity: '', reason: '', entrance_id: '' });
    const [busy, setBusy] = useState(false);

    const load = async () => setScraps(await api.get('/scraps'));
    useEffect(() => {
        load();
        api.get('/productions').then((ps) =>
            setVersions(ps.flatMap((p) => p.versions.map((v) => ({
                ...v, production_name: p.name,
            })))));
        api.get('/entrances').then(setEntrances);
    }, []);

    const submit = async () => {
        setBusy(true);
        try {
            const payload = {
                quantity: Number(form.quantity),
                reason: form.reason,
            };
            if (form.entrance_id) payload.entrance_id = Number(form.entrance_id);
            await api.post(`/versions/${form.version_id}/scraps`, payload);
            setOpen(false);
            setForm({ version_id: '', quantity: '', reason: '', entrance_id: '' });
            toast('报废申请已提交，等待审批（审批前不扣库存）');
            load();
        } catch (e) {
            toast(e.message + ' ' + Object.values(e.errors || {}).flat().join('；'), 'error');
        } finally { setBusy(false); }
    };

    const review = async (s, approve) => {
        try {
            await api.post(`/scraps/${s.id}/review`, { approve });
            toast(approve ? '已批准，库存已扣减' : '已驳回，库存不变');
            load();
        } catch (e) { toast(e.message, 'error'); }
    };

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-bold">错版报废审批</h2>
                    <p className="text-sm text-slate-500 mt-1">报废必须经主管审批：批准后才扣减库存；待审批与已驳回均不动账。</p>
                </div>
                <Button onClick={() => setOpen(true)}>+ 提交报废申请</Button>
            </div>

            <Card>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-slate-500 border-b border-slate-100">
                            <th className="py-2 pr-3 font-medium">版本</th>
                            <th className="py-2 pr-3 font-medium">位置</th>
                            <th className="py-2 pr-3 font-medium">数量</th>
                            <th className="py-2 pr-3 font-medium">原因</th>
                            <th className="py-2 pr-3 font-medium">申请人</th>
                            <th className="py-2 pr-3 font-medium">状态</th>
                            <th className="py-2 pr-3 font-medium">审批</th>
                            <th className="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {scraps.map((s) => (
                            <tr key={s.id} className="border-b border-slate-50">
                                <td className="py-2 pr-3">
                                    <div>{s.program_version?.production?.name}</div>
                                    <div className="text-xs text-slate-400">v{s.program_version?.version_no} {s.program_version?.label}</div>
                                </td>
                                <td className="py-2 pr-3">{s.entrance ? s.entrance.name : '前台总库'}</td>
                                <td className="py-2 pr-3 font-semibold text-rose-600">{s.quantity}</td>
                                <td className="py-2 pr-3">{s.reason}</td>
                                <td className="py-2 pr-3 text-slate-500">{s.requested_by}</td>
                                <td className="py-2 pr-3"><Badge tone={toneMap[s.status]}>{labelMap[s.status]}</Badge></td>
                                <td className="py-2 pr-3 text-xs text-slate-500">
                                    {s.reviewed_by && <>{s.reviewed_by}{s.review_note ? `：${s.review_note}` : ''}</>}
                                </td>
                                <td className="py-2 text-right whitespace-nowrap">
                                    {s.status === 'pending' && (
                                        <span className="flex gap-2 justify-end">
                                            <Button size="sm" tone="success" onClick={() => review(s, true)}>批准</Button>
                                            <Button size="sm" tone="ghost" onClick={() => review(s, false)}>驳回</Button>
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {scraps.length === 0 && (
                            <tr><td colSpan={8} className="py-8 text-center text-slate-400">暂无报废申请</td></tr>
                        )}
                    </tbody>
                </table>
            </Card>

            <Modal open={open} title="提交报废申请" onClose={() => setOpen(false)}
                footer={<><Button tone="ghost" onClick={() => setOpen(false)}>取消</Button>
                    <Button tone="danger" onClick={submit} disabled={busy || !form.version_id || !form.quantity || !form.reason}>
                        提交审批
                    </Button></>}>
                <Field label="节目单版本">
                    <select className={inputCls} value={form.version_id}
                        onChange={(e) => setForm({ ...form, version_id: Number(e.target.value) })}>
                        <option value="">请选择</option>
                        {versions.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.production_name} - v{v.version_no} {v.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="报废位置" hint="不选表示前台总库">
                    <select className={inputCls} value={form.entrance_id}
                        onChange={(e) => setForm({ ...form, entrance_id: Number(e.target.value) })}>
                        <option value="">前台总库</option>
                        {entrances.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                </Field>
                <Field label="数量">
                    <input type="number" min={1} className={inputCls} value={form.quantity}
                        onChange={(e) => setForm({ ...form, quantity: e.target.value })} />
                </Field>
                <Field label="原因（如：错版）">
                    <input className={inputCls} value={form.reason} placeholder="错版：误印旧版演员名单"
                        onChange={(e) => setForm({ ...form, reason: e.target.value })} />
                </Field>
            </Modal>
        </div>
    );
}
