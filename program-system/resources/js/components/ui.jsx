import React, { createContext, useCallback, useContext, useState } from 'react';

// ---------- Toast ----------
const ToastCtx = createContext(null);
export function useToast() {
  return useContext(ToastCtx);
}

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const push = useCallback((message, kind = 'ok') => {
    const id = Math.random().toString(36).slice(2);
    setToasts((t) => [...t, { id, message, kind }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 4200);
  }, []);
  const fail = useCallback((e) => push(e?.message || String(e), 'err'), [push]);

  return (
    <ToastCtx.Provider value={{ push, fail }}>
      {children}
      <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
        {toasts.map((t) => (
          <div
            key={t.id}
            role="status"
            className={
              'rounded-lg px-4 py-3 text-sm shadow-md border ' +
              (t.kind === 'err'
                ? 'bg-red-50 border-red-200 text-[#8f2020]'
                : 'bg-green-50 border-green-200 text-[#006300]')
            }
          >
            <span className="mr-2">{t.kind === 'err' ? '⚠️' : '✅'}</span>
            {t.message}
          </div>
        ))}
      </div>
    </ToastCtx.Provider>
  );
}

// ---------- 基础元素 ----------
export function Card({ title, extra, children, className = '' }) {
  return (
    <section className={'bg-surface border border-line rounded-xl shadow-sm ' + className}>
      {(title || extra) && (
        <header className="flex items-center justify-between px-5 py-3 border-b border-line">
          <h3 className="font-semibold text-ink">{title}</h3>
          {extra}
        </header>
      )}
      <div className="p-5">{children}</div>
    </section>
  );
}

const badgeTone = {
  draft: 'bg-gray-100 text-ink-2 border-gray-200',
  locked: 'bg-theater-50 text-theater-700 border-theater-200',
  superseded: 'bg-amber-50 text-[#8a5a00] border-amber-200',
  ordered: 'bg-blue-50 text-[#1d4e89] border-blue-200',
  delivered: 'bg-green-50 text-good-text border-green-200',
  pending: 'bg-amber-50 text-[#8a5a00] border-amber-200',
  approved: 'bg-green-50 text-good-text border-green-200',
  rejected: 'bg-red-50 text-[#8f2020] border-red-200',
  settled: 'bg-gray-100 text-ink-2 border-gray-200',
  confirmed: 'bg-theater-50 text-theater-700 border-theater-200',
};
const label = {
  draft: '草稿', locked: '已锁定', superseded: '已替代',
  ordered: '已下单', delivered: '已交货',
  pending: '待审批', approved: '已批准', rejected: '已驳回',
  settled: '已结算', confirmed: '已确认',
};

export function Badge({ status }) {
  return (
    <span className={`inline-block text-xs px-2 py-0.5 rounded-full border ${badgeTone[status] || 'bg-gray-100 text-ink-2 border-gray-200'}`}>
      {label[status] || status}
    </span>
  );
}

export function Button({ children, onClick, disabled, tone = 'primary', type = 'button', size = 'md' }) {
  const tones = {
    primary: 'bg-theater-600 hover:bg-theater-700 text-white',
    ghost: 'bg-white hover:bg-gray-50 text-ink border border-line',
    good: 'bg-good hover:brightness-95 text-white',
    danger: 'bg-critical hover:brightness-95 text-white',
    gold: 'bg-gold-500 hover:bg-gold-400 text-white',
  };
  const sizes = { sm: 'text-xs px-2.5 py-1', md: 'text-sm px-3.5 py-1.5' };
  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled}
      className={`rounded-md font-medium transition disabled:opacity-40 disabled:cursor-not-allowed ${tones[tone]} ${sizes[size]}`}
    >
      {children}
    </button>
  );
}

export function StatTile({ label: lbl, value, sub, tone = 'ink' }) {
  const valueColor = {
    ink: 'text-ink', good: 'text-good-text', warn: 'text-[#8a5a00]', critical: 'text-[#8f2020]',
  }[tone];
  return (
    <div className="bg-surface border border-line rounded-xl px-4 py-3">
      <div className="text-xs text-ink-3">{lbl}</div>
      <div className={`mt-1 text-2xl font-semibold tabular-nums ${valueColor}`}>{value}</div>
      {sub && <div className="mt-0.5 text-xs text-ink-3">{sub}</div>}
    </div>
  );
}

export function Th({ children, className = '' }) {
  return <th className={`text-left text-xs font-medium text-ink-3 px-3 py-2 border-b border-line ${className}`}>{children}</th>;
}
export function Td({ children, className = '' }) {
  return <td className={`text-sm px-3 py-2 border-b border-line align-top ${className}`}>{children}</td>;
}

export function Modal({ open, onClose, title, children }) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/30 p-4" onClick={onClose}>
      <div
        className="bg-surface rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="px-5 py-3 border-b border-line flex justify-between items-center">
          <h3 className="font-semibold">{title}</h3>
          <button onClick={onClose} className="text-ink-3 hover:text-ink" aria-label="关闭">✕</button>
        </header>
        <div className="p-5">{children}</div>
      </div>
    </div>
  );
}

export function Field({ label: lbl, children }) {
  return (
    <label className="block mb-3">
      <span className="block text-xs text-ink-2 mb-1">{lbl}</span>
      {children}
    </label>
  );
}

export const inputCls =
  'w-full rounded-md border border-line px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-theater-200';
