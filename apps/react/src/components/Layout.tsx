/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/auth.tsx';
import { useI18n } from '../i18n/useI18n.ts';
import { LANGUAGES, resolve, type MessageKey } from '../i18n/index.ts';

// 底部 tabbar 是 3 列固定栅格（见 index.css 的 .tabbar），保持不变；
// 次级入口（公告/排行榜/我的游戏）走顶栏与抽屉，避免挤爆小屏页签
//
// ⚠ 这里存的是**键**不是文案：模块顶层求值只发生一次，存文案会把它冻在首次加载的语言上
// （`admin/apps/react` 真机实测过：切语言后顶栏变了、页面里的按钮还是旧语言）。
const NAV: { to: string; label: MessageKey }[] = [
  { to: '/', label: 'nav.home' },
  { to: '/wallet', label: 'nav.wallet' },
  { to: '/me', label: 'nav.me' },
];

/**
 * ⚠ 这里**没有「优惠券」入口**，是有意的（2026-10-01 撤下）：
 * `user_coupon` 的唯一行写入方就是 `CouponController::claim()` 本身
 * （admin 侧 `CouponController.php:198` 只删不发），即用户拿券的唯一途径是在那个页面点领取；
 * 而 `status='used'` 与 `used_in_order` 全仓无写入方、`used_qty` 只随领取递增
 * ⇒ 领了**永远用不掉**。页面能提供的全部价值 = 「在这领一张券，然后它永远躺着」= 假价值。
 * 后端做出核销/抵扣再恢复入口。
 */
const MORE: { to: string; label: MessageKey }[] = [
  { to: '/search', label: 'app.search' },
  { to: '/games', label: 'nav.my_games' },
  { to: '/activities', label: 'nav.activities' },
  { to: '/tournaments', label: 'nav.tournaments' },
  { to: '/invite', label: 'nav.invite' },
  { to: '/chat', label: 'nav.messages' },
  { to: '/friends', label: 'nav.friends' },
  { to: '/tickets', label: 'nav.tickets' },
  { to: '/announcements', label: 'nav.announcements' },
  { to: '/leaderboard', label: 'nav.leaderboard' },
];

/**
 * 语言切换：13 项平铺，用**母语名**而不是译名 —— 菜单可用性的前提就是
 * 「用户还看不懂当前界面语言时也能选对自己那一项」。
 *
 * 桌面放顶栏、移动放抽屉（与 NAV/MORE 同一套：`.nav` 在 <900px 整块隐藏，
 * 抽屉在 ≥900px 打不开），两处渲染同一份 `LANGUAGES`。
 *
 * 切换必须走 `setCode`（`useI18n` 给的那个）：它同时落 `gp_language` 键 ——
 * 那正是 `lib/http.ts` 发 `X-Language` 读的键 ⇒ 界面与服务端文案同语言。
 * 只改界面不改这个键的症状是「界面切了、服务端文案没变」。
 */
function LanguageMenu({ code, setCode }: { code: string; setCode: (code: string) => void }) {
  return (
    <>
      {LANGUAGES.map((item) => (
        <button
          key={item.code}
          type="button"
          lang={item.code}
          className={`lang__a${item.code === code ? ' is-active' : ''}`}
          aria-current={item.code === code ? 'true' : undefined}
          onClick={() => setCode(item.code)}
        >
          {item.native}
        </button>
      ))}
    </>
  );
}

export function Layout() {
  const { user, logout } = useAuth();
  const { t, code, setCode } = useI18n();
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
              aria-label={t('app.open_menu')}
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

          <nav className="nav" aria-label={t('nav.main')}>
            {NAV.map((n) => (
              <NavLink
                key={n.to}
                to={n.to}
                end={n.to === '/'}
                className={({ isActive }) => `nav__a${isActive ? ' is-active' : ''}`}
              >
                {t(n.label)}
              </NavLink>
            ))}
            {/* 次级入口收进「更多」折叠面板：13 项平铺在顶栏会把标签挤成竖排。
                收起时链接**仍在 DOM 里**（display:none 不摘节点），端到端用例靠
                `nav a` 的文本找「活动 / 赛事 / 邀请好友」，别改成条件渲染。 */}
            <details className="nav__more">
              <summary>{t('nav.more')}</summary>
              <div className="nav__menu">
                {MORE.map((n) => (
                  <NavLink
                    key={n.to}
                    to={n.to}
                    className={({ isActive }) => `nav__a${isActive ? ' is-active' : ''}`}
                  >
                    {t(n.label)}
                  </NavLink>
                ))}
              </div>
            </details>
            <details className="nav__more">
              <summary aria-label={t('app.language')}>{resolve(code).native}</summary>
              <div className="nav__menu">
                <LanguageMenu code={code} setCode={setCode} />
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
                  {t('app.logout')}
                </button>
              </>
            ) : (
              <Link to="/login" className="btn btn--sm btn--primary">
                {t('app.sign_in')}
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
            aria-label={t('app.close_menu')}
            onClick={() => setOpen(false)}
          />
          <aside className="drawer" role="dialog" aria-modal="true" aria-label={t('nav.menu')}>
            <div className="drawer__head">
              <p className="logo" style={{ fontSize: 18 }}>
                Game<span>Platform</span>
              </p>
              <p className="small" style={{ margin: '6px 0 0', fontWeight: 600 }}>
                {user ? user.nickname || user.username : t('app.not_signed_in')}
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
                  {t(n.label)}
                </NavLink>
              ))}
              <p className="label" style={{ margin: '18px 0 6px' }}>
                {t('nav.more')}
              </p>
              {MORE.map((n) => (
                <NavLink
                  key={n.to}
                  to={n.to}
                  className={({ isActive }) => `drawer__a${isActive ? ' is-active' : ''}`}
                >
                  {t(n.label)}
                </NavLink>
              ))}
              <p className="label" style={{ margin: '18px 0 6px' }}>
                {t('app.language')}
              </p>
              <LanguageMenu code={code} setCode={setCode} />
              {user ? (
                <button type="button" className="drawer__a" onClick={onLogout}>
                  {t('app.logout_full')}
                </button>
              ) : (
                <Link to="/login" className="drawer__a is-active">
                  {t('app.sign_in_up')}
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

      <nav className="tabbar" aria-label={t('nav.bottom')}>
        {NAV.map((n) => (
          <NavLink
            key={n.to}
            to={n.to}
            end={n.to === '/'}
            className={({ isActive }) => `tabbar__a${isActive ? ' is-active' : ''}`}
          >
            {t(n.label)}
          </NavLink>
        ))}
      </nav>
    </>
  );
}
