/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { lazy } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { ErrorBoundary } from './components/ErrorBoundary';
import { RequireAuth, Shell } from './components/Shell';
import { AuthProvider } from './lib/auth';
import { LoginPage } from './pages/LoginPage';
// 侧边栏/登录页的布局样式都在 App.css，勿删此引入
import './App.css';

/**
 * 页面级切分：整页各自一个 chunk，**不进口未登录时的那一屏**。
 *
 * 未切之前全树只有一个 1,158.56 kB 的 chunk（gzip 346.60 kB），连 `/login` 都要先下完它；
 * 而 `TabPage` 一个人就拖着 1535 行的 `modules.ts` 与它挂的十来个页面模块。
 *
 * 登录页**不切**：它就是未登录时的首屏，切了首屏省下的字节它一分拿不到，反而多一次往返。
 *
 * 为什么给 TabPage 传字符串 `id` 而不是 `page={PAGES.dashboard}`：`PAGES` 是 TabPage.tsx 里的
 * 模块级常量，在 App 里静态引用它 = 把整个 TabPage 模块图拉回首屏，切分白做。查表留在页面内。
 */
const TabPage = lazy(() => import('./pages/TabPage').then((module) => ({ default: module.TabPage })));
const LogsPage = lazy(() => import('./pages/logs').then((module) => ({ default: module.LogsPage })));
const SearchPage = lazy(() => import('./pages/search').then((module) => ({ default: module.SearchPage })));

/** 路由与 Shell 的 NAV 一一对应；每个页面按标签切换端点。 */
export default function App() {
  // 生产构建挂在子路径（build 脚本带 --base=/admin-react/），BASE_URL 随之变化。
  // 必须去掉结尾斜杠：react-router 的 stripBasename 遇到以 "/" 结尾的 basename 会匹配不到子路径
  const basename = import.meta.env.BASE_URL.replace(/\/$/, '') || '/';
  return (
    // 最外层的最后一道护栏：路由/鉴权/布局层自己抛异常时也降级，而不是整站白屏
    <ErrorBoundary>
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
              <Route index element={<TabPage id="dashboard" />} />
              <Route path="analytics" element={<TabPage id="analytics" />} />
              <Route path="games" element={<TabPage id="games" />} />
              <Route path="users" element={<TabPage id="users" />} />
              <Route path="admins" element={<TabPage id="admins" />} />
              <Route path="withdrawals" element={<TabPage id="withdrawals" />} />
              <Route path="risk" element={<TabPage id="risk" />} />
              {/* 社群（群组 + 分享统计）与操作日志各自成页：都带页面级筛选，塞不进「一个标签一个端点」的 TabPage */}
              <Route path="community" element={<TabPage id="community" />} />
              <Route path="logs" element={<LogsPage />} />
              {/* 全局搜索的结果页：关键词在 URL 里（顶栏搜索框写、这里读） */}
              <Route path="search" element={<SearchPage />} />
              <Route path="profile" element={<TabPage id="profile" />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </ErrorBoundary>
  );
}
