import React, { createContext, useCallback, useContext, useState } from 'react';

/* ---------------- Toast ---------------- */
const ToastCtx = createContext(null);
export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const push = useCallback((message, type = 'success') => {
        const id = Math.random().toString(36).slice(2);
        setToasts((t) => [...t, { id, message, type }]);
        setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 4000);
    }, []);
    return (
        <ToastCtx.Provider value={push}>
            {children}
            <div className="fixed bottom-5 right-5 z-50 space-y-2">
                {toasts.map((t) => (
                    <div key={t.id} className={
                        'px-4 py-3 rounded-lg shadow-lg text-sm text-white max-w-sm ' +
                        (t.type === 'success' ? 'bg-emerald-600' : t.type === 'error' ? 'bg-rose-600' : 'bg-slate-700')
                    }>
                        {t.message}
                    </div>
                ))}
            </div>
        </ToastCtx.Provider>
    );
}
export const useToast = () => useContext(ToastCtx);

/* ---------------- Card / Stat / Badge ---------------- */
export function Card({ title, extra, children, className = '' }) {
    return (
        <section className={'bg-white rounded-xl border border-slate-200 shadow-sm ' + className}>
            {(title || extra) && (
                <header className="flex items-center justify-between px-5 py-3 border-b border-slate-100">
                    <h3 className="font-semibold text-slate-800">{title}</h3>
                    {extra}
                </header>
            )}
            <div className="p-5">{children}</div>
        </section>
    );
}

export function Stat({ label, value, hint, tone = 'slate' }) {
    const tones = {
        slate: 'text-slate-800',
        emerald: 'text-emerald-600',
        rose: 'text-rose-600',
        amber: 'text-amber-600',
        blue: 'text-sky-600',
    };
    return (
        <div className="bg-white rounded-xl border border-slate-200 px-5 py-4">
            <div className="text-xs text-slate-500">{label}</div>
            <div className={'text-2xl font-bold mt-1 ' + tones[tone]}>{value}</div>
            {hint && <div className="text-xs text-slate-400 mt-1">{hint}</div>}
        </div>
    );
}

export function Badge({ children, tone = 'slate' }) {
    const tones = {
        slate: 'bg-slate-100 text-slate-600',
        emerald: 'bg-emerald-100 text-emerald-700',
        rose: 'bg-rose-100 text-rose-700',
        amber: 'bg-amber-100 text-amber-700',
        sky: 'bg-sky-100 text-sky-700',
        indigo: 'bg-indigo-100 text-indigo-700',
    };
    return <span className={'inline-block px-2 py-0.5 rounded-full text-xs font-medium ' + tones[tone]}>{children}</span>;
}

/* ---------------- Buttons / Inputs ---------------- */
export function Button({ children, onClick, type = 'button', tone = 'primary', disabled, size = 'md', className = '' }) {
    const tones = {
        primary: 'bg-indigo-600 hover:bg-indigo-700 text-white',
        ghost: 'bg-white border border-slate-300 hover:bg-slate-50 text-slate-700',
        danger: 'bg-rose-600 hover:bg-rose-700 text-white',
        success: 'bg-emerald-600 hover:bg-emerald-700 text-white',
        warn: 'bg-amber-500 hover:bg-amber-600 text-white',
    };
    const sizes = { sm: 'px-2.5 py-1 text-xs', md: 'px-4 py-2 text-sm' };
    return (
        <button type={type} onClick={onClick} disabled={disabled}
            className={'rounded-lg font-medium transition disabled:opacity-40 disabled:cursor-not-allowed ' + tones[tone] + ' ' + sizes[size] + ' ' + className}>
            {children}
        </button>
    );
}

export function Field({ label, children, hint }) {
    return (
        <label className="block">
            <span className="text-sm font-medium text-slate-700">{label}</span>
            <div className="mt-1">{children}</div>
            {hint && <span className="text-xs text-slate-400">{hint}</span>}
        </label>
    );
}

export const inputCls = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400';

/* ---------------- Modal ---------------- */
export function Modal({ open, title, onClose, children, footer }) {
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
            <div className="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-auto">
                <header className="flex items-center justify-between px-5 py-3 border-b border-slate-100">
                    <h3 className="font-semibold text-slate-800">{title}</h3>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
                </header>
                <div className="p-5 space-y-4">{children}</div>
                {footer && <footer className="px-5 py-3 border-t border-slate-100 flex justify-end gap-2">{footer}</footer>}
            </div>
        </div>
    );
}

/* ---------------- Errors ---------------- */
export function ErrorText({ error, field }) {
    const list = error?.errors?.[field] || [];
    if (!list.length) return null;
    return <p className="text-xs text-rose-600 mt-1">{list.join('；')}</p>;
}
