/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './lib/auth.tsx';
import { Layout } from './components/Layout.tsx';
import { Loading } from './components/States.tsx';
import { Activities } from './pages/Activities.tsx';
import { AnnouncementDetail, Announcements } from './pages/Announcements.tsx';
import { ChatList, ChatRoom } from './pages/Chat.tsx';
import { Deposit } from './pages/Deposit.tsx';
import { Exchange } from './pages/Exchange.tsx';
import { Friends } from './pages/Friends.tsx';
import { GameDetail } from './pages/GameDetail.tsx';
import { Home } from './pages/Home.tsx';
import { Invite } from './pages/Invite.tsx';
import { Kyc } from './pages/Kyc.tsx';
import { Leaderboard } from './pages/Leaderboard.tsx';
import { Login } from './pages/Login.tsx';
import { Me } from './pages/Me.tsx';
import { MyGames } from './pages/MyGames.tsx';
import { Search } from './pages/Search.tsx';
import { Security } from './pages/Security.tsx';
import { TicketDetail, TicketNew, Tickets } from './pages/Tickets.tsx';
import { Tournaments } from './pages/Tournaments.tsx';
import { Wallet } from './pages/Wallet.tsx';
import { Withdraw } from './pages/Withdraw.tsx';

/** 需要登录的路由：鉴权态未就绪先加载，未登录跳登录页并记住来源。 */
function Secure({ children }: { children: ReactNode }) {
  const { user, ready } = useAuth();
  const { pathname } = useLocation();
  if (!ready) return <Loading />;
  if (!user) return <Navigate to="/login" state={{ from: pathname }} replace />;
  return <>{children}</>;
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
            <Route path="/game/:hashid" element={<GameDetail />} />
            {/* 以下公开接口（无鉴权）未登录也放行：公告、排行榜、全局搜索 */}
            <Route path="/announcements" element={<Announcements />} />
            <Route path="/announcements/:hashid" element={<AnnouncementDetail />} />
            <Route path="/leaderboard" element={<Leaderboard />} />
            <Route path="/search" element={<Search />} />
            {/* 优惠券页已撤下：券可领不可核销，唯一来源就是领取按钮本身（见 Layout 注释） */}
            <Route
              path="/games"
              element={
                <Secure>
                  <MyGames />
                </Secure>
              }
            />
            <Route
              path="/wallet"
              element={
                <Secure>
                  <Wallet />
                </Secure>
              }
            />
            <Route
              path="/wallet/deposit"
              element={
                <Secure>
                  <Deposit />
                </Secure>
              }
            />
            <Route
              path="/wallet/withdraw"
              element={
                <Secure>
                  <Withdraw />
                </Secure>
              }
            />
            <Route
              path="/wallet/exchange"
              element={
                <Secure>
                  <Exchange />
                </Secure>
              }
            />
            <Route
              path="/me"
              element={
                <Secure>
                  <Me />
                </Secure>
              }
            />
            <Route
              path="/security"
              element={
                <Secure>
                  <Security />
                </Secure>
              }
            />
            {/* 实名认证：提现档位的唯一开关（WithdrawController::withdrawLevel 只认 approved） */}
            <Route
              path="/kyc"
              element={
                <Secure>
                  <Kyc />
                </Secure>
              }
            />
            {/* 邀请：POST /shares 要登录态 */}
            <Route
              path="/invite"
              element={
                <Secure>
                  <Invite />
                </Secure>
              }
            />
            <Route
              path="/activities"
              element={
                <Secure>
                  <Activities />
                </Secure>
              }
            />
            <Route
              path="/tournaments"
              element={
                <Secure>
                  <Tournaments />
                </Secure>
              }
            />
            <Route
              path="/tickets"
              element={
                <Secure>
                  <Tickets />
                </Secure>
              }
            />
            {/* new 必须排在 :hashid 前面，否则 /tickets/new 会被当成 hashid=new */}
            <Route
              path="/tickets/new"
              element={
                <Secure>
                  <TicketNew />
                </Secure>
              }
            />
            <Route
              path="/tickets/:hashid"
              element={
                <Secure>
                  <TicketDetail />
                </Secure>
              }
            />
            <Route
              path="/friends"
              element={
                <Secure>
                  <Friends />
                </Secure>
              }
            />
            <Route
              path="/chat"
              element={
                <Secure>
                  <ChatList />
                </Secure>
              }
            />
            <Route
              path="/chat/:hashid"
              element={
                <Secure>
                  <ChatRoom />
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
