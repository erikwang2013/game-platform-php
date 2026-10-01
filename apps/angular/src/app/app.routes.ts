/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { inject } from '@angular/core';
import { CanActivateFn, Router, Routes } from '@angular/router';
import { isAuthed } from './core/api.service';

/** 未登录直接跳登录页，并带回跳地址 */
const authGuard: CanActivateFn = (_route, state) =>
  isAuthed()
    ? true
    : inject(Router).createUrlTree(['/login'], {
        queryParams: { redirect: state.url },
      });

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./pages/login').then((m) => m.LoginPage),
  },
  {
    path: '',
    loadComponent: () => import('./pages/home').then((m) => m.HomePage),
  },
  {
    path: 'game/:hashid',
    loadComponent: () => import('./pages/game').then((m) => m.GamePage),
  },
  // 公开页：/api/v1/search 在公开组，未登录也能搜（同 announcements/leaderboard）
  {
    path: 'search',
    loadComponent: () => import('./pages/search').then((m) => m.SearchPage),
  },
  {
    path: 'wallet',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/wallet').then((m) => m.WalletPage),
  },
  {
    path: 'wallet/deposit',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/deposit').then((m) => m.DepositPage),
  },
  {
    path: 'wallet/withdraw',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/withdraw').then((m) => m.WithdrawPage),
  },
  {
    path: 'wallet/exchange',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/exchange').then((m) => m.ExchangePage),
  },
  {
    path: 'me',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/me').then((m) => m.MePage),
  },
  {
    path: 'security',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/security').then((m) => m.SecurityPage),
  },
  {
    path: 'activities',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/activities').then((m) => m.ActivitiesPage),
  },
  // 赛事三端点整个挂在 UserAuth 组里（route.php:261），未登录进不去
  {
    path: 'tournaments',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/tournaments').then((m) => m.TournamentsPage),
  },
  {
    path: 'wallet/records',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/playlogs').then((m) => m.PlaylogsPage),
  },
  {
    path: 'kyc',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/kyc').then((m) => m.KycPage),
  },
  {
    path: 'tickets',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/tickets').then((m) => m.TicketsPage),
  },
  // 好友：/friend/* 七个端点整组挂 UserAuth
  {
    path: 'friends',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/friends').then((m) => m.FriendsPage),
  },
  // 消息：/chat/* 三端点整组挂 UserAuth。**无 WS 版** —— 顶部写明「新消息到达后刷新」
  {
    path: 'chat',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/chat').then((m) => m.ChatPage),
  },
  {
    path: 'chat/:hashid',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/chat-room').then((m) => m.ChatRoomPage),
  },
  // 分享短码：注册前的邀请链接（生成 + `/login?code=` 落地页上报）
  {
    path: 'invite',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/invite').then((m) => m.InvitePage),
  },
  // 公开页：公告与排行榜后端不带鉴权，未登录也能看
  {
    path: 'announcements',
    loadComponent: () => import('./pages/announcements').then((m) => m.AnnouncementsPage),
  },
  {
    path: 'leaderboard',
    loadComponent: () => import('./pages/leaderboard').then((m) => m.LeaderboardPage),
  },
  { path: '**', redirectTo: '' },
];
