/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * C端 API 客户端 — 信封解包 (code===0) / Bearer 头 / 401 刷新一次 / 错误透出
 * 所有 ID 均为 Hashid 字符串。
 */
import { HttpClient, HttpErrorResponse, HttpInterceptorFn, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, from, lastValueFrom, map, of, switchMap, throwError } from 'rxjs';

/* 契约类型集中在 api.types.ts，此处 re-export 保持既有 import 路径可用 */
import type {
  AuthResult,
  DepositCreated,
  DepositOrder,
  Envelope,
  ExchangeDone,
  ExchangePayload,
  ExchangeQuote,
  Game,
  GameDetail,
  LaunchResult,
  Notify,
  Num,
  Paged,
  PaymentMethodInfo,
  PlatformStats,
  Suggestion,
  Transaction,
  UserProfile,
  WalletInfo,
  WithdrawApplied,
  WithdrawOrder,
} from './api.types';

export * from './api.types';

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

/** 充值零小数币种（与后端 DepositController 的精度校验一致） */
const ZERO_DECIMAL = ['JPY', 'KRW'];

/**
 * 充值金额精度预检，与后端同规则：JPY/KRW 零小数，其余最多 2 位小数。
 * 纯字符串格式校验，不做任何金额换算或舍入；后端仍会二次校验。
 */
export function depositAmountOk(amount: string, currency: string): boolean {
  const max = ZERO_DECIMAL.includes(currency.toUpperCase()) ? 0 : 2;
  return max === 0 ? /^\d+$/.test(amount) : new RegExp(`^\\d+(\\.\\d{1,${max}})?$`).test(amount);
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

  /** 支付方式列表（公开接口）；createDeposit 的 payment_method_id 取自此处 */
  paymentMethods(): Observable<{ list: PaymentMethodInfo[] }> {
    return this.request<{ list: PaymentMethodInfo[] }>('GET', `${BASE}/payment/methods`);
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

  /* ---- 资金写操作（金额一律传字符串原文，不得经过 float） ---- */

  /** 创建充值订单；502 表示支付网关不可用，可提示重试 */
  createDeposit(payload: {
    amount: string;
    currency: string;
    payment_method_id: string;
  }): Observable<DepositCreated> {
    return this.request<DepositCreated>('POST', `${BASE}/deposit/create`, undefined, payload);
  }

  /** 申请提现（服务端可能因全局开关/风控/限额/审核锁返回 403/400/429/503） */
  applyWithdraw(payload: {
    platform_amount: string;
    method: string;
    account_info: string;
  }): Observable<WithdrawApplied> {
    return this.request<WithdrawApplied>('POST', `${BASE}/withdraw/apply`, undefined, payload);
  }

  /** 兑换询价；不产生订单，仅用于展示汇率/点差/预计到账 */
  quoteExchange(payload: ExchangePayload): Observable<ExchangeQuote> {
    return this.request<ExchangeQuote>('POST', `${BASE}/exchange/quote`, undefined, payload);
  }

  /** 买入：平台币 → 游戏币（服务端按路径固定 direction='in'，请求体里的 direction 被忽略） */
  exchangeBuy(payload: ExchangePayload): Observable<ExchangeDone> {
    return this.request<ExchangeDone>('POST', `${BASE}/exchange/buy`, undefined, payload);
  }

  /** 卖出：游戏币 → 平台币（服务端按路径固定 direction='out'） */
  exchangeSell(payload: ExchangePayload): Observable<ExchangeDone> {
    return this.request<ExchangeDone>('POST', `${BASE}/exchange/sell`, undefined, payload);
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
