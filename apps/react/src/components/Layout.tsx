/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/auth.tsx';

const NAV = [
  { to: '/', label: '首页' },
  { to: '/wallet', label: '钱包' },
  { to: '/me', label: '我的' },
];

export function Layout() {
  const { user, logout } = useAuth();
  const [open, setOpen] = useState(false);
  const { pathname } = useLocation();
  const navigate = useNavigate();

  // 路由变化时关闭移动端抽屉
  useEffect(() => setOpen(false), [pathname]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open]);

  const onLogout = () => {
    logout();
    navigate('/');
  };

  return (
    <>
      <header className="masthead">
        <div className="masthead__in">
          <div className="row" style={{ gap: 12 }}>
            <button
              type="button"
              className="burger"
              aria-label="打开菜单"
              aria-expanded={open}
              onClick={() => setOpen(true)}
            >
              <span aria-hidden="true" style={{ fontSize: 20, lineHeight: 1 }}>
                ≡
              </span>
            </button>
            <Link to="/" className="logo">
              Game<span>Platform</span>
            </Link>
          </div>

          <nav className="nav" aria-label="主导航">
            {NAV.map((n) => (
              <NavLink
                key={n.to}
                to={n.to}
                end={n.to === '/'}
                className={({ isActive }) => `nav__a${isActive ? ' is-active' : ''}`}
              >
                {n.label}
              </NavLink>
            ))}
          </nav>

          <div className="acct">
            {user ? (
              <>
                <Link to="/me" className="small" style={{ fontWeight: 700 }}>
                  {user.nickname || user.username}
                </Link>
                <button type="button" className="btn btn--sm" onClick={onLogout}>
                  退出
                </button>
              </>
            ) : (
              <Link to="/login" className="btn btn--sm btn--primary">
                登录
              </Link>
            )}
          </div>
        </div>
      </header>

      {open && (
        <>
          <button
            type="button"
            className="scrim"
            aria-label="关闭菜单"
            onClick={() => setOpen(false)}
          />
          <aside className="drawer" role="dialog" aria-modal="true" aria-label="导航菜单">
            <div className="drawer__head">
              <p className="logo" style={{ fontSize: 18 }}>
                Game<span>Platform</span>
              </p>
              <p className="small" style={{ margin: '6px 0 0', fontWeight: 600 }}>
                {user ? user.nickname || user.username : '未登录'}
              </p>
            </div>
            <nav className="drawer__nav">
              {NAV.map((n) => (
                <NavLink
                  key={n.to}
                  to={n.to}
                  end={n.to === '/'}
                  className={({ isActive }) => `drawer__a${isActive ? ' is-active' : ''}`}
                >
                  {n.label}
                </NavLink>
              ))}
              {user ? (
                <button type="button" className="drawer__a" onClick={onLogout}>
                  退出登录
                </button>
              ) : (
                <Link to="/login" className="drawer__a is-active">
                  登录 / 注册
                </Link>
              )}
            </nav>
          </aside>
        </>
      )}

      <main className="shell">
        <div className="stack-lg">
          <Outlet />
        </div>
      </main>

      <nav className="tabbar" aria-label="底部导航">
        {NAV.map((n) => (
          <NavLink
            key={n.to}
            to={n.to}
            end={n.to === '/'}
            className={({ isActive }) => `tabbar__a${isActive ? ' is-active' : ''}`}
          >
            {n.label}
          </NavLink>
        ))}
      </nav>
    </>
  );
}
