/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './lib/auth.tsx';
import { Layout } from './components/Layout.tsx';
import { Loading } from './components/States.tsx';
import { Deposit } from './pages/Deposit.tsx';
import { Exchange } from './pages/Exchange.tsx';
import { GameDetail } from './pages/GameDetail.tsx';
import { Home } from './pages/Home.tsx';
import { Login } from './pages/Login.tsx';
import { Me } from './pages/Me.tsx';
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
            <Route path="*" element={<Navigate to="/" replace />} />
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
