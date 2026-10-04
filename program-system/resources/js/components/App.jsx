import React from 'react';
import { Link, NavLink, Route, Routes } from 'react-router-dom';
import { ToastProvider } from './ui.jsx';
import PerformancesPage from '../pages/PerformancesPage.jsx';
import PerformanceDetailPage from '../pages/PerformanceDetailPage.jsx';
import BoardPage from '../pages/BoardPage.jsx';
import ScrapsPage from '../pages/ScrapsPage.jsx';

function NavItem({ to, children }) {
  return (
    <NavLink
      to={to}
      end
      className={({ isActive }) =>
        'px-3 py-1.5 rounded-md text-sm transition ' +
        (isActive ? 'bg-theater-700 text-white' : 'text-white/80 hover:bg-white/10')
      }
    >
      {children}
    </NavLink>
  );
}

export default function App() {
  return (
    <ToastProvider>
      <div className="min-h-screen">
        <nav className="bg-theater-800 text-white px-6 py-3 flex items-center gap-4 sticky top-0 z-30">
          <Link to="/" className="font-semibold tracking-wide mr-2">
            🎭 节目单印刷与余量统计
          </Link>
          <NavItem to="/">场次管理</NavItem>
          <NavItem to="/board">入口看板</NavItem>
          <NavItem to="/scraps">报废审批</NavItem>
        </nav>
        <main className="max-w-6xl mx-auto p-6">
          <Routes>
            <Route path="/" element={<PerformancesPage />} />
            <Route path="/performances/:id" element={<PerformanceDetailPage />} />
            <Route path="/board" element={<BoardPage />} />
            <Route path="/scraps" element={<ScrapsPage />} />
          </Routes>
        </main>
      </div>
    </ToastProvider>
  );
}
