/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/auth.tsx';

// 底部 tabbar 是 3 列固定栅格（见 index.css 的 .tabbar），保持不变；
// 次级入口（公告/排行榜/我的游戏）走顶栏与抽屉，避免挤爆小屏页签
const NAV = [
  { to: '/', label: '首页' },
  { to: '/wallet', label: '钱包' },
  { to: '/me', label: '我的' },
];

/**
 * ⚠ 这里**没有「优惠券」入口**，是有意的（2026-10-01 撤下）：
 * `user_coupon` 的唯一行写入方就是 `CouponController::claim()` 本身
 * （admin 侧 `CouponController.php:198` 只删不发），即用户拿券的唯一途径是在那个页面点领取；
 * 而 `status='used'` 与 `used_in_order` 全仓无写入方、`used_qty` 只随领取递增
 * ⇒ 领了**永远用不掉**。页面能提供的全部价值 = 「在这领一张券，然后它永远躺着」= 假价值。
 * 后端做出核销/抵扣再恢复入口。
 */
const MORE = [
  { to: '/search', label: '搜索' },
  { to: '/games', label: '我的游戏' },
  { to: '/activities', label: '活动' },
  { to: '/tournaments', label: '赛事' },
  { to: '/invite', label: '邀请好友' },
  { to: '/chat', label: '消息' },
  { to: '/friends', label: '好友' },
  { to: '/tickets', label: '工单' },
  { to: '/announcements', label: '公告' },
  { to: '/leaderboard', label: '排行榜' },
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
              <img className="logo-mascot" src={`${import.meta.env.BASE_URL}mascot.svg`} alt="" />
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
            {/* 次级入口收进「更多」折叠面板：13 项平铺在顶栏会把标签挤成竖排。
                收起时链接**仍在 DOM 里**（display:none 不摘节点），端到端用例靠
                `nav a` 的文本找「活动 / 赛事 / 邀请好友」，别改成条件渲染。 */}
            <details className="nav__more">
              <summary>更多</summary>
              <div className="nav__menu">
                {MORE.map((n) => (
                  <NavLink
                    key={n.to}
                    to={n.to}
                    className={({ isActive }) => `nav__a${isActive ? ' is-active' : ''}`}
                  >
                    {n.label}
                  </NavLink>
                ))}
              </div>
            </details>
          </nav>

          <div className="acct">
            {user ? (
              <>
                <Link to="/me" className="acct__name">
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
              <p className="label" style={{ margin: '18px 0 6px' }}>
                更多
              </p>
              {MORE.map((n) => (
                <NavLink
                  key={n.to}
                  to={n.to}
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
