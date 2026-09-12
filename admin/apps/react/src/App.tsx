/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { RequireAuth, Shell } from './components/Shell';
import { AuthProvider } from './lib/auth';
import { LoginPage } from './pages/LoginPage';
import { PAGES, TabPage } from './pages/TabPage';
// 侧边栏/登录页的布局样式都在 App.css，勿删此引入
import './App.css';

/** 路由与 Shell 的 NAV 一一对应；每个页面按标签切换端点。 */
export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route
            element={
              <RequireAuth>
                <Shell />
              </RequireAuth>
            }
          >
            <Route index element={<TabPage page={PAGES.dashboard} />} />
            <Route path="analytics" element={<TabPage page={PAGES.analytics} />} />
            <Route path="games" element={<TabPage page={PAGES.games} />} />
            <Route path="users" element={<TabPage page={PAGES.users} />} />
            <Route path="withdrawals" element={<TabPage page={PAGES.withdrawals} />} />
            <Route path="risk" element={<TabPage page={PAGES.risk} />} />
            <Route path="profile" element={<TabPage page={PAGES.profile} />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
