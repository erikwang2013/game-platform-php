/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { Auth } from './auth.service';

/** 无会话 → 去登录页，并带上回跳地址 */
export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(Auth);
  const router = inject(Router);
  if (auth.authed()) return true;
  return router.createUrlTree(['/login'], { queryParams: { redirect: state.url } });
};
