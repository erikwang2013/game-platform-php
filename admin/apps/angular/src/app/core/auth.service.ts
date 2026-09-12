/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';

/** 会话用户；id 是 hashid 字符串，禁止当数字用 */
export interface SessionUser {
  id: string;
  username: string;
  real_name: string;
}

const ACCESS = 'ga_access_token';
const REFRESH = 'ga_refresh_token';
const USER = 'ga_user';

function read<T>(key: string): T | null {
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : null;
  } catch {
    return null;
  }
}

/**
 * 会话状态（纯存储 + 信号，不碰 HTTP）。
 * 传输层在 Api —— Api 反向依赖本服务读写 token，本服务不依赖 Api，避免循环注入。
 */
@Injectable({ providedIn: 'root' })
export class Auth {
  private readonly router = inject(Router);

  readonly user = signal<SessionUser | null>(read<SessionUser>(USER));
  readonly authed = computed(() => this.user() !== null);

  get token(): string | null {
    return localStorage.getItem(ACCESS);
  }

  get refreshToken(): string | null {
    return localStorage.getItem(REFRESH);
  }

  set(data: { access_token: string; refresh_token?: string }, user?: SessionUser): void {
    localStorage.setItem(ACCESS, data.access_token);
    // 刷新接口可能不轮换 refresh_token，缺失时保留原值
    if (data.refresh_token) localStorage.setItem(REFRESH, data.refresh_token);
    if (user) {
      localStorage.setItem(USER, JSON.stringify(user));
      this.user.set(user);
    }
  }

  clear(): void {
    localStorage.removeItem(ACCESS);
    localStorage.removeItem(REFRESH);
    localStorage.removeItem(USER);
    this.user.set(null);
  }

  signOut(): void {
    this.clear();
    void this.router.navigate(['/login']);
  }
}
