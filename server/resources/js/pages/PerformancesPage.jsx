import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { Badge, Button, Card, Field, Modal, inputCls, useToast } from '../components/ui.jsx';

const statusMap = {
    scheduled: { label: '待确认', tone: 'amber' },
    confirmed: { label: '已确认/进行中', tone: 'sky' },
    closed: { label: '已散场盘点', tone: 'slate' },
};

export default function PerformancesPage() {
    const [shows, setShows] = useState([]);
    const [productions, setProductions] = useState([]);
    const [createOpen, setCreateOpen] = useState(false);
    const [form, setForm] = useState({ production_id: '', name: '', performed_at: '' });
    const toast = useToast();

    const load = async () => {
        const [s, p] = await Promise.all([api.get('/performances'), api.get('/productions')]);
        setShows(s);
        setProductions(p);
        if (!form.production_id && p[0]) setForm((f) => ({ ...f, production_id: p[0].id }));
    };
    useEffect(() => { load(); }, []);

    const create = async () => {
        try {
            await api.post('/performances', form);
            setCreateOpen(false);
            setForm((f) => ({ ...f, name: '', performed_at: '' }));
            toast('场次已创建');
            load();
        } catch (e) { toast(e.message, 'error'); }
    };

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold">场次安排</h2>
                <Button onClick={() => setCreateOpen(true)}>+ 新建场次</Button>
            </div>

            <Card>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-slate-500 border-b border-slate-100">
                            <th className="py-2 pr-4 font-medium">场次</th>
                            <th className="py-2 pr-4 font-medium">剧目</th>
                            <th className="py-2 pr-4 font-medium">演出时间</th>
                            <th className="py-2 pr-4 font-medium">允许发放版本</th>
                            <th className="py-2 pr-4 font-medium">状态</th>
                            <th className="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {shows.map((s) => {
                            const st = statusMap[s.status];
                            return (
                                <tr key={s.id} className="border-b border-slate-50 hover:bg-slate-50">
                                    <td className="py-3 pr-4 font-medium">{s.name}</td>
                                    <td className="py-3 pr-4">{s.production?.name}</td>
                                    <td className="py-3 pr-4">{new Date(s.performed_at).toLocaleString('zh-CN')}</td>
                                    <td className="py-3 pr-4">
                                        {s.program_version
                                            ? `v${s.program_version.version_no} ${s.program_version.label}`
                                            : <span className="text-slate-400">未确认</span>}
                                    </td>
                                    <td className="py-3 pr-4"><Badge tone={st.tone}>{st.label}</Badge></td>
                                    <td className="py-3 text-right">
                                        <Link to={`/performances/${s.id}`}>
                                            <Button tone="ghost" size="sm">进入场次</Button>
                                        </Link>
                                    </td>
                                </tr>
                            );
                        })}
                        {shows.length === 0 && (
                            <tr><td colSpan={6} className="py-8 text-center text-slate-400">暂无场次</td></tr>
                        )}
                    </tbody>
                </table>
            </Card>

            <Modal open={createOpen} title="新建场次（增加场次不影响旧版本）" onClose={() => setCreateOpen(false)}
                footer={<><Button tone="ghost" onClick={() => setCreateOpen(false)}>取消</Button><Button onClick={create}>创建</Button></>}>
                <Field label="剧目">
                    <select className={inputCls} value={form.production_id}
                        onChange={(e) => setForm({ ...form, production_id: Number(e.target.value) })}>
                        {productions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                </Field>
                <Field label="场次名称">
                    <input className={inputCls} value={form.name} placeholder="如 10月6日加演场"
                        onChange={(e) => setForm({ ...form, name: e.target.value })} />
                </Field>
                <Field label="演出时间">
                    <input type="datetime-local" className={inputCls} value={form.performed_at}
                        onChange={(e) => setForm({ ...form, performed_at: e.target.value })} />
                </Field>
            </Modal>
        </div>
    );
}
