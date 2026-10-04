import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { Badge, Button, Card, Field, Modal, StatTile, Td, Th, inputCls } from '../components/ui.jsx';
import { useToast } from '../components/ui.jsx';

export default function PerformancesPage() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({
    title: '', performed_at: '', venue: '主剧场', file_name: '', cast_list: '',
  });
  const toast = useToast();

  const load = async () => {
    setLoading(true);
    setRows(await api.get('/api/performances'));
    setLoading(false);
  };
  useEffect(() => { load(); }, []);

  const create = async () => {
    try {
      await api.post('/api/performances', form);
      toast.push('演出与 v1 草稿已创建');
      setOpen(false);
      setForm({ title: '', performed_at: '', venue: '主剧场', file_name: '', cast_list: '' });
      load();
    } catch (e) {
      toast.fail(e);
    }
  };

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold">场次管理</h1>
          <p className="text-sm text-ink-3 mt-0.5">编辑确定节目单版本 · 印厂交货 · 前台分配 · 散场盘点</p>
        </div>
        <Button tone="gold" onClick={() => setOpen(true)}>+ 新建场次</Button>
      </div>

      <div className="grid grid-cols-4 gap-3">
        <StatTile label="场次数" value={rows.length} />
        <StatTile label="草稿/待确认" value={rows.filter((r) => r.status === 'draft').length} tone="warn" />
        <StatTile label="已确认（可发放）" value={rows.filter((r) => r.status === 'confirmed').length} />
        <StatTile label="已散场结算" value={rows.filter((r) => r.status === 'settled').length} tone="good" />
      </div>

      <Card>
        <table className="w-full">
          <thead>
            <tr><Th>演出</Th><Th>时间</Th><Th>剧场</Th><Th>版本数</Th><Th>状态</Th><Th></Th></tr>
          </thead>
          <tbody>
            {loading && <tr><Td>加载中…</Td></tr>}
            {!loading && rows.map((p) => (
              <tr key={p.id} className="hover:bg-plane">
                <Td className="font-medium">{p.title}</Td>
                <Td>{new Date(p.performed_at).toLocaleString('zh-CN', { hour12: false })}</Td>
                <Td>{p.venue}</Td>
                <Td>{p.versions_count}</Td>
                <Td><Badge status={p.status} /></Td>
                <Td className="text-right">
                  <Link to={`/performances/${p.id}`} className="text-theater-600 text-sm hover:underline">
                    进入场次 →
                  </Link>
                </Td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Modal open={open} onClose={() => setOpen(false)} title="新建场次与 v1 节目单草稿">
        <Field label="演出名称">
          <input className={inputCls} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        </Field>
        <Field label="演出时间">
          <input type="datetime-local" className={inputCls} value={form.performed_at}
            onChange={(e) => setForm({ ...form, performed_at: e.target.value })} />
        </Field>
        <Field label="剧场">
          <input className={inputCls} value={form.venue} onChange={(e) => setForm({ ...form, venue: e.target.value })} />
        </Field>
        <Field label="待印文件名">
          <input className={inputCls} placeholder="如 leiyu-program-v1.pdf" value={form.file_name}
            onChange={(e) => setForm({ ...form, file_name: e.target.value })} />
        </Field>
        <Field label="演员名单（每行：角色：演员）">
          <textarea rows={4} className={inputCls} value={form.cast_list}
            onChange={(e) => setForm({ ...form, cast_list: e.target.value })} />
        </Field>
        <div className="flex justify-end gap-2 mt-2">
          <Button tone="ghost" onClick={() => setOpen(false)}>取消</Button>
          <Button tone="gold" onClick={create}>创建（草稿）</Button>
        </div>
      </Modal>
    </div>
  );
}
