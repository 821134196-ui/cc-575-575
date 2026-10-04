import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Badge, Card, Stat } from '../components/ui.jsx';

export default function ReconciliationPage() {
    const [data, setData] = useState(null);

    useEffect(() => {
        const load = () => api.get('/reconciliation').then(setData);
        load();
        const timer = setInterval(load, 5000);
        return () => clearInterval(timer);
    }, []);

    if (!data) return <div className="text-slate-400">核对中…</div>;
    const g = data.grand;

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-bold">库存核对</h2>
                    <p className="text-sm text-slate-500 mt-1">
                        以库存流水为唯一事实来源，核对恒等式：<b>交货总数 = 发放 + 报废 + 当前剩余</b>；
                        分配与调拨只在库位间移动，净额必须为 0。
                    </p>
                </div>
                <Badge tone={data.all_balanced ? 'emerald' : 'rose'}>
                    {data.all_balanced ? '✓ 总账平衡' : '✗ 账目异常'}
                </Badge>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <Stat label="印厂交货总数" value={g.delivered} tone="blue" />
                <Stat label="发放总数" value={g.issued} tone="amber" />
                <Stat label="报废总数" value={g.scrapped} tone="rose" />
                <Stat label="当前剩余" value={g.on_hand} hint={`总库 ${g.front_stock} / 入口 ${g.entrance_stock}`} tone="emerald" />
            </div>

            <Card>
                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <span className="font-mono bg-slate-100 px-3 py-1.5 rounded-md">{g.delivered}</span>
                    <span className="text-slate-400">=</span>
                    <span className="font-mono bg-amber-50 px-3 py-1.5 rounded-md">{g.issued} 发放</span>
                    <span className="text-slate-400">+</span>
                    <span className="font-mono bg-rose-50 px-3 py-1.5 rounded-md">{g.scrapped} 报废</span>
                    <span className="text-slate-400">+</span>
                    <span className="font-mono bg-emerald-50 px-3 py-1.5 rounded-md">{g.on_hand} 剩余</span>
                    <span className={'ml-2 font-semibold ' + (g.balanced ? 'text-emerald-600' : 'text-rose-600')}>
                        {g.balanced ? '✓ 恒等式成立' : '✗ 恒等式不成立'}
                    </span>
                </div>
                <div className="mt-3 flex gap-6 text-sm">
                    <span>分配流水净额：
                        <b className={data.allocate_net === 0 ? 'text-emerald-600' : 'text-rose-600'}> {data.allocate_net}</b>
                        <span className="text-xs text-slate-400">（总库↔入口，应为 0）</span>
                    </span>
                    <span>调拨流水净额：
                        <b className={data.transfer_net === 0 ? 'text-emerald-600' : 'text-rose-600'}> {data.transfer_net}</b>
                        <span className="text-xs text-slate-400">（入口↔入口，应为 0）</span>
                    </span>
                </div>
            </Card>

            <Card title="分版本核对明细">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-slate-500 border-b border-slate-100">
                            <th className="py-2 pr-3 font-medium">剧目</th>
                            <th className="py-2 pr-3 font-medium">版本</th>
                            <th className="py-2 pr-3 font-medium">交货</th>
                            <th className="py-2 pr-3 font-medium">发放</th>
                            <th className="py-2 pr-3 font-medium">报废</th>
                            <th className="py-2 pr-3 font-medium">总库</th>
                            <th className="py-2 pr-3 font-medium">入口</th>
                            <th className="py-2 pr-3 font-medium">剩余合计</th>
                            <th className="py-2 font-medium">核对</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.per_version.map((r) => (
                            <tr key={r.version_id} className="border-b border-slate-50">
                                <td className="py-2 pr-3">{r.production}</td>
                                <td className="py-2 pr-3">{r.version_label}</td>
                                <td className="py-2 pr-3">{r.delivered}</td>
                                <td className="py-2 pr-3">{r.issued}</td>
                                <td className="py-2 pr-3">{r.scrapped}</td>
                                <td className="py-2 pr-3">{r.front_stock}</td>
                                <td className="py-2 pr-3">{r.entrance_stock}</td>
                                <td className="py-2 pr-3 font-medium">{r.on_hand}</td>
                                <td className="py-2">
                                    {r.balanced && !r.has_negative
                                        ? <Badge tone="emerald">平衡</Badge>
                                        : <Badge tone="rose">{r.has_negative ? '负库存' : '不平'}</Badge>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </div>
    );
}
