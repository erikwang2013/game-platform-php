/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Routes } from '@angular/router';
import { authGuard } from './core/auth.guard';

export const routes: Routes = [
  { path: 'login', loadComponent: () => import('./pages/login').then((m) => m.Login) },
  {
    path: '',
    canActivateChild: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      {
        path: 'dashboard',
        loadComponent: () => import('./pages/dashboard').then((m) => m.Dashboard),
      },
      {
        path: 'analytics',
        loadComponent: () => import('./pages/analytics').then((m) => m.Analytics),
      },
      { path: 'users', loadComponent: () => import('./pages/users').then((m) => m.Users) },
      { path: 'games', loadComponent: () => import('./pages/games').then((m) => m.Games) },
      { path: 'finance', loadComponent: () => import('./pages/finance').then((m) => m.Finance) },
      { path: 'risk', loadComponent: () => import('./pages/risk').then((m) => m.Risk) },
      { path: 'content', loadComponent: () => import('./pages/content').then((m) => m.Content) },
      {
        path: 'marketing',
        loadComponent: () => import('./pages/marketing').then((m) => m.Marketing),
      },
      // 社群：组队/公会 + 分享裂变统计（GET /groups、/groups/{hashid}/audit、/share/stats）
      {
        path: 'community',
        loadComponent: () => import('./pages/community').then((m) => m.Community),
      },
      { path: 'support', loadComponent: () => import('./pages/support').then((m) => m.Support) },
      { path: 'infra', loadComponent: () => import('./pages/infra').then((m) => m.Infra) },
      // 后台账号（/admin/v1/user）；与 pages/users.ts 的 C 端平台用户是两回事
      { path: 'admins', loadComponent: () => import('./pages/admins').then((m) => m.Admins) },
      { path: 'settings', loadComponent: () => import('./pages/settings').then((m) => m.Settings) },
      { path: '**', redirectTo: 'dashboard' },
    ],
  },
];
