import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api.js';
import { Button, Card, Field, StatTile, inputCls, useToast } from '../components/ui.jsx';

// 入口看板：清楚显示本场允许发放的版本及剩余数量
export default function BoardPage() {
  const [performances, setPerformances] = useState([]);
  const [entrances, setEntrances] = useState([]);
  const [board, setBoard] = useState(null);
  const [params, setParams] = useSearchParams();
  const [sel, setSel] = useState({ performance_id: params.get('performance_id') || '', entrance_id: params.get('entrance_id') || '' });
  const [qty, setQty] = useState(10);
  const [busy, setBusy] = useState(false);
  const toast = useToast();

  useEffect(() => {
    (async () => {
      const [ps, es] = await Promise.all([api.get('/api/performances'), api.get('/api/entrances')]);
      setPerformances(ps);
      setEntrances(es);
      const next = {
        performance_id: sel.performance_id || String(ps[0]?.id || ''),
        entrance_id: sel.entrance_id || String(es[0]?.id || ''),
      };
      setSel(next);
    })();
  }, []);

  const load = async (pfId, enId) => {
    if (!pfId || !enId) return;
    setBoard(await api.get(`/api/performances/${pfId}/entrances/${enId}/board`));
  };
  useEffect(() => { load(sel.performance_id, sel.entrance_id); }, [sel.performance_id, sel.entrance_id]);

  const choose = (patch) => {
    const next = { ...sel, ...patch };
    setSel(next);
    setParams(next);
  };

  const issue = async (versionId, remaining) => {
    const n = Math.min(Number(qty), remaining);
    if (n <= 0) return;
    setBusy(true);
    try {
      await api.post(`/api/entrances/${sel.entrance_id}/issue`, {
        program_version_id: versionId, quantity: n,
      });
      toast.push(`已发放 ${n} 份`);
      await load(sel.performance_id, sel.entrance_id);
    } catch (e) {
      toast.fail(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-5">
      <h1 className="text-xl font-semibold">入口看板</h1>

      <Card>
        <div className="grid md:grid-cols-3 gap-4 items-end">
          <Field label="选择场次">
            <select className={inputCls} value={sel.performance_id} onChange={(e) => choose({ performance_id: e.target.value })}>
              {performances.map((p) => <option key={p.id} value={p.id}>{p.title} · {new Date(p.performed_at).toLocaleString('zh-CN', { hour12: false })}</option>)}
            </select>
          </Field>
          <Field label="选择入口">
            <select className={inputCls} value={sel.entrance_id} onChange={(e) => choose({ entrance_id: e.target.value })}>
              {entrances.map((e) => <option key={e.id} value={e.id}>{e.code} {e.name}</option>)}
            </select>
          </Field>
          <Field label="快捷发放数量">
            <input type="number" min={1} className={inputCls} value={qty} onChange={(e) => setQty(e.target.value)} />
          </Field>
        </div>
      </Card>

      {board && (
        <>
          <div className="grid grid-cols-3 gap-3">
            <StatTile label="本场" value={board.performance.title} sub={`${board.entrance.code} ${board.entrance.name}`} />
            <StatTile label="可发放版本数" value={board.allowed_versions.filter((v) => v.allowed_to_issue).length}
              sub={`共 ${board.allowed_versions.length} 个版本曾入场`} />
            <StatTile label="本口剩余总数" value={board.total_remaining} tone={board.total_remaining > 0 ? 'good' : 'critical'}
              sub={board.total_remaining > 0 ? '可以继续发放' : '已发完，可申请调拨'} />
          </div>

          <div className="space-y-3">
            {board.allowed_versions.length === 0 && (
              <Card><p className="text-sm text-ink-3">该入口尚无任何已入场版本。请先由前台在场次页面分配。</p></Card>
            )}
            {board.allowed_versions.map((v) => (
              <Card key={v.version_id}>
                <div className="flex flex-wrap items-center gap-4">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-lg font-semibold text-theater-700">v{v.version_no}</span>
                      <code className="text-sm">{v.file_name}</code>
                    </div>
                    <div className="text-xs text-ink-3 mt-1">{v.status_label}</div>
                    {v.print_batches?.length > 0 && (
                      <div className="text-[11px] text-ink-3 mt-1">
                        来自批次：{v.print_batches.map((b) => b.batch_no).join('、')}
                      </div>
                    )}
                  </div>
                  <div className="ml-auto flex items-center gap-4">
                    <div className="text-right">
                      <div className="text-xs text-ink-3">剩余可发</div>
                      <div className={`text-3xl font-semibold tabular-nums ${v.remaining > 0 ? 'text-good-text' : 'text-critical'}`}>
                        {v.remaining}
                      </div>
                    </div>
                    <Button tone="gold" disabled={busy || v.remaining <= 0}
                      onClick={() => issue(v.version_id, v.remaining)}>
                      发放 {Math.min(Number(qty) || 0, v.remaining) || ''}
                    </Button>
                  </div>
                </div>
                {v.remaining <= 0 && (
                  <div className="mt-3 text-xs text-[#8a5a00]">该版本在本入口已无库存：不得用旧节目单改名顶替，请从前台调拨或使用新版本。</div>
                )}
              </Card>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
