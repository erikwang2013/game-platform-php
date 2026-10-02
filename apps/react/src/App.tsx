/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Suspense, lazy, type ReactNode } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './lib/auth.tsx';
import { Layout } from './components/Layout.tsx';
import { Loading } from './components/States.tsx';
import { Home } from './pages/Home.tsx';
import { Login } from './pages/Login.tsx';

/**
 * 路由级切分：每个页面各自一个 chunk。切之前整站只有一个 378.60 kB 的 chunk（gzip 110.60 kB），
 * 连未登录时只看 `/login` 一屏也要先把它整份下完。
 *
 * **不切的两个**：`/login` 与 `/`（Home）—— 一个是匿名首屏、一个是登录后首屏，切了它省下的字节
 * 它自己一分都拿不到，反而多一次往返。
 *
 * 此处刻意**不**在 `<Routes>` 外面兜一个 `<Suspense>`：那样 chunk 到货前整壳（导航/底栏）都会
 * 被 fallback 换掉，来回跳页时布局直抖。改成每个路由各包一层，只换内容区 —— 与
 * `admin/apps/react` 的 `App.tsx` + `Shell.tsx` 同形（那边边界在 Shell 里，这里没有 Shell 可改，
 * 就地放在 App 内）。
 */
const Activities = lazy(() => import('./pages/Activities.tsx').then((m) => ({ default: m.Activities })));
const Announcements = lazy(() => import('./pages/Announcements.tsx').then((m) => ({ default: m.Announcements })));
const AnnouncementDetail = lazy(() => import('./pages/Announcements.tsx').then((m) => ({ default: m.AnnouncementDetail })));
const ChatList = lazy(() => import('./pages/Chat.tsx').then((m) => ({ default: m.ChatList })));
const ChatRoom = lazy(() => import('./pages/Chat.tsx').then((m) => ({ default: m.ChatRoom })));
const Deposit = lazy(() => import('./pages/Deposit.tsx').then((m) => ({ default: m.Deposit })));
const Exchange = lazy(() => import('./pages/Exchange.tsx').then((m) => ({ default: m.Exchange })));
const Friends = lazy(() => import('./pages/Friends.tsx').then((m) => ({ default: m.Friends })));
const GameDetail = lazy(() => import('./pages/GameDetail.tsx').then((m) => ({ default: m.GameDetail })));
const Invite = lazy(() => import('./pages/Invite.tsx').then((m) => ({ default: m.Invite })));
const Kyc = lazy(() => import('./pages/Kyc.tsx').then((m) => ({ default: m.Kyc })));
const Leaderboard = lazy(() => import('./pages/Leaderboard.tsx').then((m) => ({ default: m.Leaderboard })));
const Me = lazy(() => import('./pages/Me.tsx').then((m) => ({ default: m.Me })));
const MyGames = lazy(() => import('./pages/MyGames.tsx').then((m) => ({ default: m.MyGames })));
const Search = lazy(() => import('./pages/Search.tsx').then((m) => ({ default: m.Search })));
const Security = lazy(() => import('./pages/Security.tsx').then((m) => ({ default: m.Security })));
const TicketDetail = lazy(() => import('./pages/Tickets.tsx').then((m) => ({ default: m.TicketDetail })));
const TicketNew = lazy(() => import('./pages/Tickets.tsx').then((m) => ({ default: m.TicketNew })));
const Tickets = lazy(() => import('./pages/Tickets.tsx').then((m) => ({ default: m.Tickets })));
const Tournaments = lazy(() => import('./pages/Tournaments.tsx').then((m) => ({ default: m.Tournaments })));
const Wallet = lazy(() => import('./pages/Wallet.tsx').then((m) => ({ default: m.Wallet })));
const Withdraw = lazy(() => import('./pages/Withdraw.tsx').then((m) => ({ default: m.Withdraw })));

/** 需要登录的路由：鉴权态未就绪先加载，未登录跳登录页并记住来源。 */
function Secure({ children }: { children: ReactNode }) {
  const { user, ready } = useAuth();
  const { pathname } = useLocation();
  if (!ready) return <Loading />;
  if (!user) return <Navigate to="/login" state={{ from: pathname }} replace />;
  return <>{children}</>;
}

/** 懒加载页面的边界：只把**内容区**换成骨架，外壳（导航/底栏）留在原地不抖。 */
function Page({ children }: { children: ReactNode }) {
  return <Suspense fallback={<Loading />}>{children}</Suspense>;
}

export default function App() {
  // 生产构建挂在子路径（build 脚本带 --base=/app-react/），BASE_URL 随之变化。
  // 必须去掉结尾斜杠：react-router 的 stripBasename 遇到以 "/" 结尾的 basename 会匹配不到子路径
  const basename = import.meta.env.BASE_URL.replace(/\/$/, '') || '/';
  return (
    <BrowserRouter basename={basename}>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route element={<Layout />}>
            <Route path="/" element={<Home />} />
            <Route path="/game/:hashid" element={<Page><GameDetail /></Page>} />
            {/* 以下公开接口（无鉴权）未登录也放行：公告、排行榜、全局搜索 */}
            <Route path="/announcements" element={<Page><Announcements /></Page>} />
            <Route path="/announcements/:hashid" element={<Page><AnnouncementDetail /></Page>} />
            <Route path="/leaderboard" element={<Page><Leaderboard /></Page>} />
            <Route path="/search" element={<Page><Search /></Page>} />
            {/* 优惠券页已撤下：券可领不可核销，唯一来源就是领取按钮本身（见 Layout 注释） */}
            <Route
              path="/games"
              element={
                <Secure>
                  <Page><MyGames /></Page>
                </Secure>
              }
            />
            <Route
              path="/wallet"
              element={
                <Secure>
                  <Page><Wallet /></Page>
                </Secure>
              }
            />
            <Route
              path="/wallet/deposit"
              element={
                <Secure>
                  <Page><Deposit /></Page>
                </Secure>
              }
            />
            <Route
              path="/wallet/withdraw"
              element={
                <Secure>
                  <Page><Withdraw /></Page>
                </Secure>
              }
            />
            <Route
              path="/wallet/exchange"
              element={
                <Secure>
                  <Page><Exchange /></Page>
                </Secure>
              }
            />
            <Route
              path="/me"
              element={
                <Secure>
                  <Page><Me /></Page>
                </Secure>
              }
            />
            <Route
              path="/security"
              element={
                <Secure>
                  <Page><Security /></Page>
                </Secure>
              }
            />
            {/* 实名认证：提现档位的唯一开关（WithdrawController::withdrawLevel 只认 approved） */}
            <Route
              path="/kyc"
              element={
                <Secure>
                  <Page><Kyc /></Page>
                </Secure>
              }
            />
            {/* 邀请：POST /shares 要登录态 */}
            <Route
              path="/invite"
              element={
                <Secure>
                  <Page><Invite /></Page>
                </Secure>
              }
            />
            <Route
              path="/activities"
              element={
                <Secure>
                  <Page><Activities /></Page>
                </Secure>
              }
            />
            <Route
              path="/tournaments"
              element={
                <Secure>
                  <Page><Tournaments /></Page>
                </Secure>
              }
            />
            <Route
              path="/tickets"
              element={
                <Secure>
                  <Page><Tickets /></Page>
                </Secure>
              }
            />
            {/* new 必须排在 :hashid 前面，否则 /tickets/new 会被当成 hashid=new */}
            <Route
              path="/tickets/new"
              element={
                <Secure>
                  <Page><TicketNew /></Page>
                </Secure>
              }
            />
            <Route
              path="/tickets/:hashid"
              element={
                <Secure>
                  <Page><TicketDetail /></Page>
                </Secure>
              }
            />
            <Route
              path="/friends"
              element={
                <Secure>
                  <Page><Friends /></Page>
                </Secure>
              }
            />
            <Route
              path="/chat"
              element={
                <Secure>
                  <Page><ChatList /></Page>
                </Secure>
              }
            />
            <Route
              path="/chat/:hashid"
              element={
                <Secure>
                  <Page><ChatRoom /></Page>
                </Secure>
              }
            />
            {/* /groups/:hashid 2026-10-01 撤下（死路由：全树无导航入口，且无列表端点
                ⇒ hashid 无从获得）。理由见 types.ts 的组队/公会墓碑注释。 */}
            <Route path="*" element={<Navigate to="/" replace />} />
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
