/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { RequireAuth, Shell } from './components/Shell';
import { AuthProvider } from './lib/auth';
import { LoginPage } from './pages/LoginPage';
import { PAGES, TabPage } from './pages/TabPage';
import { LogsPage } from './pages/logs';
import { SearchPage } from './pages/search';
// 侧边栏/登录页的布局样式都在 App.css，勿删此引入
import './App.css';

/** 路由与 Shell 的 NAV 一一对应；每个页面按标签切换端点。 */
export default function App() {
  // 生产构建挂在子路径（build 脚本带 --base=/admin-react/），BASE_URL 随之变化。
  // 必须去掉结尾斜杠：react-router 的 stripBasename 遇到以 "/" 结尾的 basename 会匹配不到子路径
  const basename = import.meta.env.BASE_URL.replace(/\/$/, '') || '/';
  return (
    <BrowserRouter basename={basename}>
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
            <Route path="admins" element={<TabPage page={PAGES.admins} />} />
            <Route path="withdrawals" element={<TabPage page={PAGES.withdrawals} />} />
            <Route path="risk" element={<TabPage page={PAGES.risk} />} />
            {/* 社群（群组 + 分享统计）与操作日志各自成页：都带页面级筛选，塞不进「一个标签一个端点」的 TabPage */}
            <Route path="community" element={<TabPage page={PAGES.community} />} />
            <Route path="logs" element={<LogsPage />} />
            {/* 全局搜索的结果页：关键词在 URL 里（顶栏搜索框写、这里读） */}
            <Route path="search" element={<SearchPage />} />
            <Route path="profile" element={<TabPage page={PAGES.profile} />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
