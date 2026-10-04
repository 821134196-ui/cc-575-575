import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Badge, Card } from '../components/ui.jsx';

export default function BoardPage() {
    const [cards, setCards] = useState([]);
    const [receipts, setReceipts] = useState([]);

    useEffect(() => {
        api.get('/board').then((d) => setCards(d.cards));
        api.get('/mock-printer/receipts').then((d) => setReceipts(d.receipts));
        const timer = setInterval(async () => {
            const d = await api.get('/board');
            setCards(d.cards);
        }, 5000);
        return () => clearInterval(timer);
    }, []);

    return (
        <div className="space-y-5">
            <div>
                <h2 className="text-xl font-bold">入口看板</h2>
                <p className="text-sm text-slate-500 mt-1">
                    每张卡片为一场已确认演出：本场只允许发放指定版本节目单，数字为各入口当前剩余可发数量（每 5 秒刷新）。
                </p>
            </div>

            {cards.length === 0 && <Card><p className="text-sm text-slate-400">暂无已确认场次。</p></Card>}

            <div className="grid lg:grid-cols-2 gap-5">
                {cards.map((c) => (
                    <Card key={c.performance_id}>
                        <div className="flex items-start justify-between">
                            <div>
                                <div className="font-bold text-lg">{c.performance_name}</div>
                                <div className="text-sm text-slate-500">{c.production} · {new Date(c.performed_at).toLocaleString('zh-CN')}</div>
                            </div>
                            <Badge tone={c.status === 'closed' ? 'slate' : 'sky'}>
                                {c.status === 'closed' ? '已散场' : '发放中'}
                            </Badge>
                        </div>

                        <div className="mt-4 bg-indigo-50 border border-indigo-100 rounded-lg p-3">
                            <div className="text-xs text-indigo-500 font-medium">✅ 本场允许发放的唯一版本</div>
                            <div className="font-semibold text-indigo-900 mt-0.5">
                                v{c.allowed_version.label}
                            </div>
                            <div className="text-xs text-indigo-700 mt-0.5">
                                文件 {c.allowed_version.file_name || '—'} · 指纹 {c.allowed_version.file_hash || '—'}
                                {c.allowed_version.locked_at && ` · ${new Date(c.allowed_version.locked_at).toLocaleString('zh-CN')} 锁定`}
                            </div>
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-3">
                            {c.entrances.map((e) => (
                                <div key={e.id} className="border border-slate-200 rounded-lg px-4 py-3 flex items-center justify-between">
                                    <span className="text-sm text-slate-600">{e.name}</span>
                                    <span className={'text-xl font-bold ' + (e.remaining === 0 ? 'text-rose-500' : 'text-emerald-600')}>
                                        {e.remaining}
                                        <span className="text-xs font-normal text-slate-400 ml-1">份</span>
                                    </span>
                                </div>
                            ))}
                        </div>
                        <div className="text-xs text-slate-400 mt-3">前台总库剩余：{c.front_remaining} 份（可继续分配/调拨）</div>
                    </Card>
                ))}
            </div>

            <Card title="印厂回执（本地模拟接口 /api/mock-printer/receipts）">
                {receipts.length === 0 ? <p className="text-sm text-slate-400">暂无回执。</p> : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-slate-500 border-b border-slate-100">
                                <th className="py-2 pr-3 font-medium">回执单号</th>
                                <th className="py-2 pr-3 font-medium">批次</th>
                                <th className="py-2 pr-3 font-medium">版本</th>
                                <th className="py-2 pr-3 font-medium">订货</th>
                                <th className="py-2 pr-3 font-medium">实交</th>
                                <th className="py-2 pr-3 font-medium">短交</th>
                                <th className="py-2 font-medium">交货时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipts.map((r) => (
                                <tr key={r.receipt_no} className="border-b border-slate-50">
                                    <td className="py-2 pr-3 font-mono text-xs">{r.receipt_no}</td>
                                    <td className="py-2 pr-3">{r.batch_no}</td>
                                    <td className="py-2 pr-3">{r.version}</td>
                                    <td className="py-2 pr-3">{r.ordered_qty}</td>
                                    <td className="py-2 pr-3 text-emerald-600 font-medium">{r.delivered_qty}</td>
                                    <td className={'py-2 pr-3 ' + (r.short_qty ? 'text-rose-500' : 'text-slate-400')}>{r.short_qty}</td>
                                    <td className="py-2 text-slate-500">{r.delivered_at ? new Date(r.delivered_at).toLocaleString('zh-CN') : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>
        </div>
    );
}
