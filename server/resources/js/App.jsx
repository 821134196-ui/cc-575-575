import { NavLink, Route, Routes, Navigate } from 'react-router-dom';
import { ToastProvider } from './components/ui.jsx';
import PerformancesPage from './pages/PerformancesPage.jsx';
import PerformanceDetailPage from './pages/PerformanceDetailPage.jsx';
import ProductionPage from './pages/ProductionPage.jsx';
import BoardPage from './pages/BoardPage.jsx';
import ScrapsPage from './pages/ScrapsPage.jsx';
import ReconciliationPage from './pages/ReconciliationPage.jsx';

const nav = [
    { to: '/performances', label: '场次' },
    { to: '/productions', label: '节目单版本' },
    { to: '/board', label: '入口看板' },
    { to: '/scraps', label: '报废审批' },
    { to: '/reconciliation', label: '库存核对' },
];

export default function App() {
    return (
        <ToastProvider>
            <div className="min-h-screen bg-slate-100 text-slate-900">
                <header className="bg-slate-900 text-white">
                    <div className="max-w-7xl mx-auto px-6 h-14 flex items-center gap-8">
                        <div className="font-bold tracking-wide">🎭 节目单印制与余量系统</div>
                        <nav className="flex gap-1">
                            {nav.map((n) => (
                                <NavLink key={n.to} to={n.to}
                                    className={({ isActive }) =>
                                        'px-3 py-1.5 rounded-md text-sm transition ' +
                                        (isActive ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800')
                                    }>
                                    {n.label}
                                </NavLink>
                            ))}
                        </nav>
                    </div>
                </header>
                <main className="max-w-7xl mx-auto px-6 py-6">
                    <Routes>
                        <Route path="/" element={<Navigate to="/performances" replace />} />
                        <Route path="/performances" element={<PerformancesPage />} />
                        <Route path="/performances/:id" element={<PerformanceDetailPage />} />
                        <Route path="/productions" element={<ProductionPage />} />
                        <Route path="/board" element={<BoardPage />} />
                        <Route path="/scraps" element={<ScrapsPage />} />
                        <Route path="/reconciliation" element={<ReconciliationPage />} />
                    </Routes>
                </main>
            </div>
        </ToastProvider>
    );
}
