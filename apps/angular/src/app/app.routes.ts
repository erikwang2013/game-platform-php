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
  {
    path: 'wallet',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/wallet').then((m) => m.WalletPage),
  },
  {
    path: 'me',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/me').then((m) => m.MePage),
  },
  { path: '**', redirectTo: '' },
];
