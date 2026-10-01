/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Suspense, useEffect, useState, type ReactNode } from 'react';
import { NavLink, Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { ErrorBoundary } from './ErrorBoundary';
import { Loading } from './ui';
import { useAuth } from '../lib/auth';
import { useSignOut } from '../lib/hooks';
import { LANGUAGES, useI18n, type MessageKey } from '../i18n/index.ts';

/** tab=true 的进底部标签栏（移动端 5 个主入口）。 */
const NAV: { to: string; label: MessageKey; icon: string; tab: boolean }[] = [
  { to: '/', label: 'nav.dashboard', icon: '◔', tab: true },
  { to: '/analytics', label: 'nav.analytics', icon: '◫', tab: true },
  { to: '/games', label: 'nav.games', icon: '◈', tab: true },
  { to: '/users', label: 'nav.users', icon: '◉', tab: true },
  // 后台账号（/admin/v1/user）与「用户」页的平台用户（C 端玩家）是两回事，故各自一个顶层入口
  { to: '/admins', label: 'nav.admins', icon: '⚙', tab: false },
  { to: '/withdrawals', label: 'nav.withdrawals', icon: '◎', tab: false },
  { to: '/risk', label: 'nav.risk', icon: '⬡', tab: false },
  // 社群：组队/公会与分享统计（后端 M4 那组端点，本树此前没有入口）
  { to: '/community', label: 'nav.community', icon: '⬢', tab: false },
  { to: '/logs', label: 'nav.logs', icon: '▤', tab: false },
  // 全局搜索：顶栏那个框是快捷键，窄屏顶栏把框收起来了，故侧栏也要有一项（否则手机上没法进）
  { to: '/search', label: 'nav.search', icon: '⌕', tab: false },
  { to: '/profile', label: 'nav.profile', icon: '☰', tab: true },
];

export function RequireAuth({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const location = useLocation();
  if (!user) return <Navigate to="/login" state={{ from: location.pathname }} replace />;
  return <>{children}</>;
}

export function Shell() {
  const [drawer, setDrawer] = useState(false);
  const [langOpen, setLangOpen] = useState(false);
  // 顶栏搜索框：只存输入，不存结果 —— 结果页自己从 URL 的 q 读（见 pages/search.tsx）
  const [keyword, setKeyword] = useState('');
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuth();
  const signOut = useSignOut();
  // 订阅语言：本组件是整棵树的布局层，换语言时它重渲染、<Outlet/> 下的页面随之重渲染，
  // 各页面在渲染期现调 t() 就拿到新语言（模块顶层的 NAV 只存键、不存译文，见 i18n/index.ts）
  const { code, setCode, t } = useI18n();
  const current = NAV.find((item) => item.to === location.pathname);
  const active = LANGUAGES.find((item) => item.code === code) ?? LANGUAGES[0];

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

  useEffect(() => {
    if (!langOpen) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setLangOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [langOpen]);

  return (
    <div className="shell">
      <aside className={`sidebar${drawer ? ' open' : ''}`}>
        <div className="brand">
          <span className="brand-mark" aria-hidden="true">
            <img src={`${import.meta.env.BASE_URL}mascot.svg`} alt="" />
          </span>
          <span className="brand-t">{t('app.title')}</span>
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
              <span>{t(item.label)}</span>
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-f">
          <span className="muted">{user?.real_name || user?.username || '—'}</span>
          <button type="button" className="btn btn-sm" onClick={() => void signOut()}>
            {t('app.logout')}
          </button>
        </div>
      </aside>

      {drawer ? <div className="scrim" onClick={() => setDrawer(false)} role="presentation" /> : null}

      <div className="main">
        <header className="topbar">
          <button
            type="button"
            className="burger"
            onClick={() => setDrawer(true)}
            aria-label={t('app.open_menu')}
            aria-expanded={drawer}
          >
            ☰
          </button>
          <span className="topbar-t">{current ? t(current.label) : t('app.console')}</span>
          {/* 全局搜索（GET /admin/v1/search 一直没有入口）：回车跳结果页，关键词落在 URL 上。
              不做「边打边搜」——那会把每个字母都变成一次请求，且结果页的分页/回退就没了锚点 */}
          <form
            className="topsearch"
            onSubmit={(event) => {
              event.preventDefault();
              navigate(`/search?type=game&q=${encodeURIComponent(keyword.trim())}`);
            }}
          >
            <input
              className="input"
              type="search"
              value={keyword}
              onChange={(event) => setKeyword(event.target.value)}
              placeholder={t('search.placeholder')}
              aria-label={t('nav.search')}
            />
          </form>
          <span className="topbar-u muted">{user?.username ?? ''}</span>

          {/* 13 种语言平铺 + 当前项打点。母语名（不是译名）：用户看不懂当前界面语言时也要能选对。
              失焦即收起（relatedTarget 在框外 / 为 null 都算失焦），不必铺全局监听 */}
          <div
            className="lang"
            onBlur={(event) => {
              if (!event.currentTarget.contains(event.relatedTarget)) setLangOpen(false);
            }}
          >
            <button
              type="button"
              className="btn btn-sm"
              aria-haspopup="listbox"
              aria-expanded={langOpen}
              aria-label={t('lang.label')}
              onClick={() => setLangOpen((open) => !open)}
            >
              {active.native}
            </button>
            {langOpen ? (
              <ul className="langmenu" role="listbox" aria-label={t('lang.label')}>
                {LANGUAGES.map((item) => (
                  <li key={item.code}>
                    <button
                      type="button"
                      role="option"
                      aria-selected={item.code === code}
                      className={`langitem${item.code === code ? ' on' : ''}`}
                      onClick={() => {
                        setCode(item.code);
                        setLangOpen(false);
                      }}
                    >
                      <span className="langitem-n">{item.native}</span>
                      <span className="langitem-d" aria-hidden="true">
                        {item.code === code ? '●' : ''}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        </header>
        <main className="content">
          {/* 页面级两道护栏，都在布局层之内 ⇒ 页面炸了顶栏/侧栏/底部标签还在，用户点得走。
              ① ErrorBoundary：渲染异常降级成「这一屏挂了 + 重试」，不白屏；`resetKey` 传路径，
                 换页面自动复位（边界不会自己复位，不复位就等于把用户锁死在降级 UI 上）。
              ② Suspense：路由是 `React.lazy` 切的，chunk 到货前拿骨架撑住这一块（布局不抖）。 */}
          <ErrorBoundary resetKey={location.pathname}>
            <Suspense fallback={<Loading />}>
              <Outlet />
            </Suspense>
          </ErrorBoundary>
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
            <span>{t(item.label)}</span>
          </NavLink>
        ))}
      </nav>
    </div>
  );
}
