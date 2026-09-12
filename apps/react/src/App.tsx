/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './lib/auth.tsx';
import { Layout } from './components/Layout.tsx';
import { Loading } from './components/States.tsx';
import { GameDetail } from './pages/GameDetail.tsx';
import { Home } from './pages/Home.tsx';
import { Login } from './pages/Login.tsx';
import { Me } from './pages/Me.tsx';
import { Wallet } from './pages/Wallet.tsx';

/** 需要登录的路由：鉴权态未就绪先加载，未登录跳登录页并记住来源。 */
function Secure({ children }: { children: ReactNode }) {
  const { user, ready } = useAuth();
  const { pathname } = useLocation();
  if (!ready) return <Loading />;
  if (!user) return <Navigate to="/login" state={{ from: pathname }} replace />;
  return <>{children}</>;
}

export default function App() {
  return (
    <BrowserRouter>
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
