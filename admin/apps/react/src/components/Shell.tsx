/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useEffect, useState, type ReactNode } from 'react';
import { NavLink, Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../lib/auth';
import { useSignOut } from '../lib/hooks';

/** tab=true 的进底部标签栏（移动端 5 个主入口）。 */
const NAV = [
  { to: '/', label: '概览', icon: '◔', tab: true },
  { to: '/analytics', label: '分析', icon: '◫', tab: true },
  { to: '/games', label: '游戏', icon: '◈', tab: true },
  { to: '/users', label: '用户', icon: '◉', tab: true },
  { to: '/withdrawals', label: '资金', icon: '◎', tab: false },
  { to: '/risk', label: '风控', icon: '⬡', tab: false },
  { to: '/profile', label: '我的', icon: '☰', tab: true },
];

export function RequireAuth({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const location = useLocation();
  if (!user) return <Navigate to="/login" state={{ from: location.pathname }} replace />;
  return <>{children}</>;
}

export function Shell() {
  const [drawer, setDrawer] = useState(false);
  const location = useLocation();
  const { user } = useAuth();
  const signOut = useSignOut();
  const current = NAV.find((item) => item.to === location.pathname);

  useEffect(() => {
    setDrawer(false);
  }, [location.pathname]);

  useEffect(() => {
    if (!drawer) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setDrawer(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [drawer]);

  return (
    <div className="shell">
      <aside className={`sidebar${drawer ? ' open' : ''}`}>
        <div className="brand">
          <span className="brand-mark" aria-hidden="true">
            游
          </span>
          <span className="brand-t">游戏运营台</span>
        </div>
        <nav className="nav">
          {NAV.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              className={({ isActive }) => `navlink${isActive ? ' on' : ''}`}
            >
              <i aria-hidden="true">{item.icon}</i>
              <span>{item.label}</span>
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-f">
          <span className="muted">{user?.real_name || user?.username || '—'}</span>
          <button type="button" className="btn btn-sm" onClick={() => void signOut()}>
            退出登录
          </button>
        </div>
      </aside>

      {drawer ? <div className="scrim" onClick={() => setDrawer(false)} role="presentation" /> : null}

      <div className="main">
        <header className="topbar">
          <button type="button" className="burger" onClick={() => setDrawer(true)} aria-label="打开菜单" aria-expanded={drawer}>
            ☰
          </button>
          <span className="topbar-t">{current?.label ?? '控制台'}</span>
          <span className="topbar-u muted">{user?.username ?? ''}</span>
        </header>
        <main className="content">
          <Outlet />
        </main>
      </div>

      <nav className="bottomtabs">
        {NAV.filter((item) => item.tab).map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === '/'}
            className={({ isActive }) => `btab${isActive ? ' on' : ''}`}
          >
            <i aria-hidden="true">{item.icon}</i>
            <span>{item.label}</span>
          </NavLink>
        ))}
      </nav>
    </div>
  );
}
