import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Badge, Button, Card, Field, Modal, inputCls, useToast } from '../components/ui.jsx';

export default function ProductionPage() {
    const toast = useToast();
    const [productions, setProductions] = useState([]);
    const [detail, setDetail] = useState(null);
    const [newVersionOpen, setNewVersionOpen] = useState(false);
    const [form, setForm] = useState({ label: '', change_reason: '' });
    const [editTarget, setEditTarget] = useState(null);
    const [editForm, setEditForm] = useState({ file_name: '' });
    const [busy, setBusy] = useState(false);

    const load = async () => {
        const list = await api.get('/productions');
        setProductions(list);
        if (list[0]) {
            const d = await api.get(`/productions/${list[0].id}`);
            setDetail(d);
        }
    };
    useEffect(() => { load(); }, []);

    const createVersion = async () => {
        try {
            await api.post(`/productions/${detail.id}/versions`, form);
            setNewVersionOpen(false);
            setForm({ label: '', change_reason: '' });
            toast('新版本已创建（旧版本保留原名，不受影响）');
            load();
        } catch (e) { toast(e.message, 'error'); }
    };

    /** 锁定：锁完刷新 */
    const lock = async (v) => {
        try {
            await api.post(`/versions/${v.id}/lock`, { by: '编辑' });
            toast(`v${v.version_no} 待印文件已锁定`);
            load();
        } catch (e) { toast(e.message, 'error'); }
    };

    /**
     * 修改已锁定版本的文件 —— 后端必然拒绝（VERSION_LOCKED）。
     * 弹窗中明确提示：旧节目单已发出，不能改名顶替，必须新建版本。
     */
    const tryEditLocked = async () => {
        setBusy(true);
        try {
            await api.post(`/versions/${editTarget.id}/file`, { file_name: editForm.file_name });
            toast('意外：锁定版本被修改成功（不应发生）', 'error');
        } catch (e) {
            if (e.errorCode === 'VERSION_LOCKED') {
                toast('⛔ ' + e.message, 'error');
            } else {
                toast(e.message, 'error');
            }
        } finally {
            setBusy(false);
            setEditTarget(null);
        }
    };

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-bold">节目单版本管理</h2>
                    <p className="text-sm text-slate-500 mt-1">
                        演员名单临时变化或增加场次时<em>新建版本</em>；待印文件锁定后不可修改，已发出的旧节目单不能改名顶替。
                    </p>
                </div>
                {detail && <Button onClick={() => setNewVersionOpen(true)}>+ 新建版本</Button>}
            </div>

            {detail && (
                <Card title={`${detail.name}（${detail.code}）的版本时间线`}>
                    <ol className="relative border-l-2 border-slate-200 ml-2 space-y-5">
                        {detail.versions.map((v) => (
                            <li key={v.id} className="ml-5">
                                <span className={'absolute -left-[9px] mt-1.5 w-4 h-4 rounded-full ring-4 ring-white ' +
                                    (v.status === 'locked' ? 'bg-emerald-500' : 'bg-amber-400')} />
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-semibold">v{v.version_no} {v.label}</span>
                                    <Badge tone={v.status === 'locked' ? 'emerald' : 'amber'}>
                                        {v.status === 'locked' ? '🔒 已锁定' : '草稿'}
                                    </Badge>
                                    {v.change_reason && <span className="text-xs text-slate-500">变更原因：{v.change_reason}</span>}
                                    <span className="ml-auto flex gap-2">
                                        {v.status !== 'locked' && (
                                            <Button size="sm" tone="success" onClick={() => lock(v)} disabled={!v.file_name}>
                                                {v.file_name ? '锁定待印文件' : '缺少文件，不能锁定'}
                                            </Button>
                                        )}
                                        <Button size="sm" tone="ghost"
                                            onClick={() => { setEditTarget(v); setEditForm({ file_name: v.file_name || '' }); }}>
                                            {v.status === 'locked' ? '尝试改名/修改' : '修改文件'}
                                        </Button>
                                    </span>
                                </div>
                                <div className="text-sm text-slate-600 mt-1">
                                    待印文件：{v.file_name || <span className="text-slate-400">未上传</span>}
                                    {v.file_hash && <span className="text-xs text-slate-400">（SHA-256 {v.file_hash.slice(0, 12)}…）</span>}
                                </div>
                                {v.status === 'locked' && (
                                    <div className="text-xs text-slate-500 mt-1">
                                        锁定人 {v.locked_by} · {new Date(v.locked_at).toLocaleString('zh-CN')}
                                        {v.print_batches?.length ? ` · 印刷批次 ${v.print_batches.length} 个` : ''}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ol>
                </Card>
            )}

            <Modal open={newVersionOpen} title="新建节目单版本" onClose={() => setNewVersionOpen(false)}
                footer={<><Button tone="ghost" onClick={() => setNewVersionOpen(false)}>取消</Button>
                    <Button onClick={createVersion} disabled={!form.label}>创建版本</Button></>}>
                <Field label="版本标签">
                    <input className={inputCls} value={form.label} placeholder="如 加演修订版"
                        onChange={(e) => setForm({ ...form, label: e.target.value })} />
                </Field>
                <Field label="新建原因" hint="如：演员名单临时变化 / 增加场次">
                    <input className={inputCls} value={form.change_reason}
                        onChange={(e) => setForm({ ...form, change_reason: e.target.value })} />
                </Field>
                <p className="text-xs text-slate-400">新版本为草稿，上传待印文件并锁定后才能安排印刷。旧版本保持原样。</p>
            </Modal>

            <Modal open={!!editTarget} title={editTarget?.status === 'locked'
                    ? `尝试修改已锁定版本 v${editTarget?.version_no}`
                    : `修改 v${editTarget?.version_no} 的待印文件`}
                onClose={() => setEditTarget(null)}
                footer={<><Button tone="ghost" onClick={() => setEditTarget(null)}>取消</Button>
                    {editTarget?.status === 'locked'
                        ? <Button tone="danger" onClick={tryEditLocked} disabled={busy}>仍然提交（验证拦截）</Button>
                        : <Button onClick={tryEditLocked} disabled={busy}>保存修改</Button>}
                </>}>
                {editTarget?.status === 'locked' && (
                    <div className="bg-rose-50 border border-rose-200 text-rose-700 rounded-md px-3 py-2 text-sm">
                        该版本已锁定并可能已发出。按制度不能改名顶替，提交将被系统拒绝；如需变更请新建版本。
                    </div>
                )}
                <Field label="待印文件名">
                    <input className={inputCls} value={editForm.file_name}
                        onChange={(e) => setEditForm({ file_name: e.target.value })} />
                </Field>
            </Modal>
        </div>
    );
}
