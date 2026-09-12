/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * C端 API 客户端 — 信封解包 (code===0) / Bearer 头 / 401 刷新一次 / 错误透出
 * 所有 ID 均为 Hashid 字符串。
 */
import { HttpClient, HttpErrorResponse, HttpInterceptorFn, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, from, lastValueFrom, map, of, switchMap, throwError } from 'rxjs';

/* ---------------- 后端契约类型 ---------------- */

export interface Envelope<T> {
  code: number;
  message: string;
  data: T;
}

export interface Paged<T> {
  items: T[];
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

/** 金额字段后端可能返回 string(decimal) 或 number，仅用于展示。 */
type Num = number | string;

export interface UserBrief {
  id: string;
  username: string;
  nickname: string;
  avatar: string;
}

export interface UserProfile extends UserBrief {
  email: string;
  phone: string;
  country: string;
  language: string;
  last_login_at: string;
  created_at: string;
}

export interface AuthResult {
  access_token: string;
  refresh_token: string;
  user?: UserBrief;
  require_2fa?: boolean;
  pending_2fa_token?: string;
}

export interface PlatformStats {
  total_games: number;
  total_users: number;
  today_game_plays: number;
  active_users_7d: number;
}

export interface Currency {
  id: string;
  name: string;
  symbol: string;
  exchange_rate: Num;
  min_exchange?: Num;
  max_exchange?: Num;
  spread_pct?: Num;
}

export interface GameCategory {
  name: string;
  slug: string;
}

export interface Game {
  id: string;
  name: string;
  slug: string;
  type: string;
  description: string;
  cover_image: string;
  sdk_version: string;
  platform: string;
  region: string;
  currencies: Currency[];
  categories: GameCategory[];
}

export interface GameDetail extends Omit<Game, 'categories'> {
  api_endpoint: string;
}

export interface Suggestion {
  id: string;
  name: string;
  slug: string;
}

export interface WalletInfo {
  id: string;
  balance: Num;
  frozen_balance: Num;
  total_earned: Num;
  total_spent: Num;
}

export interface Transaction {
  id: string;
  type: string;
  amount: Num;
  balance_after: Num;
  ref_type: string;
  ref_id: string;
  remark: string;
  created_at: string;
}

export interface DepositOrder {
  id: string;
  order_no: string;
  amount: Num;
  currency: string;
  platform_amount: Num;
  status: string;
  paid_at: string;
  created_at: string;
}

export interface WithdrawOrder {
  id: string;
  order_no: string;
  platform_amount: Num;
  method: string;
  status: string;
  review_note: string;
  created_at: string;
}

export interface Notify {
  id: string;
  type: string;
  title: string;
  content: string;
  is_read: boolean;
  ref_type: string;
  ref_id: string;
  created_at: string;
}

export interface LaunchResult {
  id: string;
  name: string;
  slug: string;
  type: string;
  api_endpoint: string;
  session_id: string;
}

/* ---------------- token 存取 ---------------- */

const K_ACCESS = 'gp_access_token';
const K_REFRESH = 'gp_refresh_token';

const store = {
  get: (k: string): string => {
    try {
      return localStorage.getItem(k) ?? '';
    } catch {
      return '';
    }
  },
  set: (k: string, v: string): void => {
    try {
      localStorage.setItem(k, v);
    } catch {
      /* 隐私模式下写入失败，静默降级为未登录 */
    }
  },
  del: (k: string): void => {
    try {
      localStorage.removeItem(k);
    } catch {
      /* ignore */
    }
  },
};

export const tokens = {
  access: (): string => store.get(K_ACCESS),
  refresh: (): string => store.get(K_REFRESH),
  save(access: string, refresh: string): void {
    store.set(K_ACCESS, access);
    if (refresh) store.set(K_REFRESH, refresh);
  },
  clear(): void {
    store.del(K_ACCESS);
    store.del(K_REFRESH);
  },
};

export const isAuthed = (): boolean => !!tokens.access();

/** 展示用格式化；不做金额运算，故允许 Number() 转换。 */
export function money(v: Num | null | undefined): string {
  const n = Number(v ?? 0);
  return Number.isFinite(n)
    ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : String(v);
}

/** 兼容 MySQL "YYYY-MM-DD HH:MM:SS"（Safari 需替换空格为 T） */
export function dt(s: string): string {
  if (!s) return '—';
  const d = new Date(s.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? s : d.toLocaleString();
}

/* ---------------- 错误 ---------------- */

export class ApiError extends Error {
  constructor(
    message: string,
    readonly code: number,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

/* ---------------- 拦截器：Bearer ---------------- */

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = tokens.access();
  return next(token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req);
};

/* ---------------- 客户端 ---------------- */

type Query = Record<string, string | number | boolean | undefined | null>;

const BASE = '/api/v1';

@Injectable({ providedIn: 'root' })
export class Api {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private refreshing: Promise<boolean> | null = null;

  /* ---- 基础设施 ---- */

  private request<T>(
    method: string,
    path: string,
    query?: Query,
    body?: unknown,
    retry = true,
  ): Observable<T> {
    return this.http.request<Envelope<T>>(method, path, { params: this.qs(query), body }).pipe(
      map((res) => {
        if (res && res.code === 0) return res.data;
        throw new ApiError(res?.message || '请求失败', res?.code ?? -1);
      }),
      catchError((err: unknown) => {
        const e = err instanceof ApiError ? err : this.wrap(err);
        // 后端 401 走 HTTP 200 + body.code=401，必须按 code 判定
        if (e.code === 401 && retry && tokens.refresh()) {
          return from(this.refresh()).pipe(
            switchMap((ok) =>
              ok ? this.request<T>(method, path, query, body, false) : this.expire(e),
            ),
          );
        }
        return e.code === 401 ? this.expire(e) : throwError(() => e);
      }),
    );
  }

  private qs(query?: Query): HttpParams {
    let p = new HttpParams();
    for (const [k, v] of Object.entries(query ?? {})) {
      if (v !== undefined && v !== null && v !== '') p = p.set(k, String(v));
    }
    return p;
  }

  private wrap(err: unknown): ApiError {
    if (err instanceof HttpErrorResponse) {
      const body: unknown = err.error;
      const msg =
        body && typeof body === 'object' && 'message' in body
          ? String((body as { message: unknown }).message)
          : '';
      const code =
        body && typeof body === 'object' && 'code' in body
          ? Number((body as { code: unknown }).code)
          : err.status;
      if (msg) return new ApiError(msg, code || err.status);
      if (err.status === 0) return new ApiError('无法连接服务器，请稍后重试', 0);
      return new ApiError(`请求失败 (HTTP ${err.status})`, err.status);
    }
    return new ApiError('请求失败', -1);
  }

  /** 单飞刷新：并发 401 只发一次 refresh */
  private refresh(): Promise<boolean> {
    if (!this.refreshing) {
      this.refreshing = lastValueFrom(
        this.http
          .post<Envelope<AuthResult>>(`${BASE}/auth/refresh`, {
            refresh_token: tokens.refresh(),
          })
          .pipe(
            map((r) => {
              const d = r.data;
              if (r.code !== 0 || !d || !d.access_token) return false;
              tokens.save(d.access_token, d.refresh_token || '');
              return true;
            }),
            catchError(() => of(false)),
          ),
      ).finally(() => {
        this.refreshing = null;
      });
    }
    return this.refreshing;
  }

  private expire(e: ApiError): Observable<never> {
    tokens.clear();
    void this.router.navigate(['/login'], {
      queryParams: { redirect: this.router.url },
    });
    return throwError(() => e);
  }

  /* ---- 公开接口 ---- */

  platformStats(): Observable<PlatformStats> {
    return this.request<PlatformStats>('GET', `${BASE}/platform/stats`);
  }

  gameList(query: Query = { page: 1, per_page: 24 }): Observable<Paged<Game>> {
    return this.request<Paged<Game>>('GET', `${BASE}/game/list`, query);
  }

  gameSuggest(q: string): Observable<{ suggestions: Suggestion[] }> {
    return this.request<{ suggestions: Suggestion[] }>('GET', `${BASE}/game/suggest`, { q });
  }

  gameDetail(hashid: string): Observable<GameDetail> {
    return this.request<GameDetail>('GET', `${BASE}/game/detail/${hashid}`);
  }

  /* ---- 认证 ---- */

  login(username: string, password: string): Observable<AuthResult> {
    return this.request<AuthResult>('POST', `${BASE}/auth/login`, undefined, {
      username,
      password,
    });
  }

  register(payload: {
    username: string;
    password: string;
    email?: string;
    nickname?: string;
  }): Observable<AuthResult> {
    return this.request<AuthResult>('POST', `${BASE}/auth/register`, undefined, payload);
  }

  logout(): void {
    tokens.clear();
  }

  /* ---- 需登录 ---- */

  walletInfo(): Observable<WalletInfo> {
    return this.request<WalletInfo>('GET', `${BASE}/wallet/info`);
  }

  walletTransactions(page = 1, perPage = 20): Observable<Paged<Transaction>> {
    return this.request<Paged<Transaction>>('GET', `${BASE}/wallet/transactions`, {
      page,
      per_page: perPage,
    });
  }

  depositOrders(page = 1, perPage = 20): Observable<Paged<DepositOrder>> {
    return this.request<Paged<DepositOrder>>('GET', `${BASE}/deposit/orders`, {
      page,
      per_page: perPage,
    });
  }

  withdrawOrders(page = 1, perPage = 20): Observable<Paged<WithdrawOrder>> {
    return this.request<Paged<WithdrawOrder>>('GET', `${BASE}/withdraw/orders`, {
      page,
      per_page: perPage,
    });
  }

  profile(): Observable<UserProfile> {
    return this.request<UserProfile>('GET', `${BASE}/user/profile`);
  }

  notifications(page = 1, perPage = 20): Observable<Paged<Notify>> {
    return this.request<Paged<Notify>>('GET', `${BASE}/notification/list`, {
      page,
      per_page: perPage,
    });
  }

  unreadCount(): Observable<{ count: number }> {
    return this.request<{ count: number }>('GET', `${BASE}/notification/unread-count`);
  }

  markRead(id?: string): Observable<unknown> {
    return this.request<unknown>('POST', `${BASE}/notification/read`, undefined, { id });
  }

  launchGame(gameId: string): Observable<LaunchResult> {
    return this.request<LaunchResult>('POST', `${BASE}/game/launch`, undefined, {
      game_id: gameId,
    });
  }
}
