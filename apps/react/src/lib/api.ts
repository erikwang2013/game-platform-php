/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 单一 API 客户端：统一拆封后端信封 { code, message, data }（成功 code === 0）。
 * 401 时尝试 refresh 一次，失败则清 token 并回调登出。
 */

const BASE = '/api/v1';
const K_ACCESS = 'gp_access_token';
const K_REFRESH = 'gp_refresh_token';
const K_LANG = 'gp_language';

export class ApiError extends Error {
  code: number;
  constructor(code: number, message: string) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
  }
}

/* ---------------- types ---------------- */

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  expires_in?: number;
}

export interface Profile {
  id: string;
  username: string;
  nickname: string | null;
  avatar: string | null;
  email?: string | null;
  created_at?: string;
}

export interface Currency {
  id: string;
  name: string;
  symbol: string;
  exchange_rate?: string;
  spread_pct?: string;
  min_exchange?: string;
  max_exchange?: string;
}

export interface Game {
  id: string;
  name: string;
  slug: string;
  type: string;
  description: string | null;
  cover_image: string | null;
  sdk_version?: string;
  platform?: string;
  region?: string;
  currencies?: Currency[];
  // 后端只回 name/slug（无分类 hashid），故不支持按分类筛选
  categories?: { name: string; slug: string }[];
  api_endpoint?: string | null;
}

export interface Paged<T> {
  items: T[];
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

export interface PlatformStats {
  total_games: number;
  total_users: number;
  today_game_plays: number;
  active_users_7d: number;
}

export interface WalletInfo {
  id: string;
  balance: string;
  frozen_balance: string;
  total_earned: string;
  total_spent: string;
}

export interface Transaction {
  id: string;
  type: string;
  amount: string;
  balance_after: string;
  ref_type: string | null;
  ref_id: string | null;
  remark: string | null;
  created_at: string;
}

export interface DepositOrder {
  order_no: string;
  amount: string;
  currency: string;
  platform_amount: string;
  status: string;
  paid_at: string | null;
  created_at: string;
}

export interface WithdrawOrder {
  order_no: string;
  platform_amount: string;
  method?: string | null;
  review_note?: string | null;
  status: string;
  created_at: string;
}

export interface Notice {
  id: string;
  type: string;
  title: string;
  content: string;
  is_read: number;
  ref_type: string | null;
  ref_id: string | null;
  created_at: string;
}

/* ---------------- token store ---------------- */

export const tokens = {
  access: () => localStorage.getItem(K_ACCESS),
  set(a: string, r: string) {
    localStorage.setItem(K_ACCESS, a);
    localStorage.setItem(K_REFRESH, r);
  },
  clear() {
    localStorage.removeItem(K_ACCESS);
    localStorage.removeItem(K_REFRESH);
  },
};

export const language = {
  get: () => localStorage.getItem(K_LANG) || 'en',
  set(code: string) {
    localStorage.setItem(K_LANG, code);
  },
};

let onUnauthorized: () => void = () => {};
export function setUnauthorizedHandler(fn: () => void) {
  onUnauthorized = fn;
}

/* ---------------- transport ---------------- */

let refreshing: Promise<boolean> | null = null;

function refreshOnce(): Promise<boolean> {
  if (refreshing) return refreshing;
  const rt = localStorage.getItem(K_REFRESH);
  if (!rt) return Promise.resolve(false);
  refreshing = (async () => {
    try {
      const res = await fetch(`${BASE}/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: rt }),
      });
      const body = await res.json().catch(() => null);
      if (!res.ok || !body || body.code !== 0) {
        tokens.clear();
        return false;
      }
      tokens.set(body.data.access_token, body.data.refresh_token || rt);
      return true;
    } catch {
      // 网络故障：保留 token，交由调用方提示重试
      return false;
    } finally {
      refreshing = null;
    }
  })();
  return refreshing;
}

async function request<T>(path: string, init: RequestInit = {}, retry = true): Promise<T> {
  const headers = new Headers(init.headers);
  if (init.body) headers.set('Content-Type', 'application/json');
  headers.set('X-Language', language.get());
  const at = tokens.access();
  if (at) headers.set('Authorization', `Bearer ${at}`);

  let res: Response;
  try {
    res = await fetch(`${BASE}${path}`, { ...init, headers });
  } catch {
    throw new ApiError(-1, '网络连接失败，请检查网络后重试');
  }

  const body = await res.json().catch(() => null);

  // 鉴权失败是 HTTP 200 + body.code 401（两个后端中间件都不设 HTTP 状态），按信封 code 判定
  if (body?.code === 401 && retry) {
    if (await refreshOnce()) return request<T>(path, init, false);
    tokens.clear();
    onUnauthorized();
    throw new ApiError(401, '登录状态已过期，请重新登录');
  }

  if (body && typeof body.code === 'number') {
    if (body.code !== 0) throw new ApiError(body.code, body.message || '请求失败');
    return body.data as T;
  }
  if (!res.ok) throw new ApiError(res.status, `请求失败（HTTP ${res.status}）`);
  throw new ApiError(-1, '响应格式异常');
}

const get = <T,>(path: string) => request<T>(path);
const post = <T,>(path: string, data: unknown) =>
  request<T>(path, { method: 'POST', body: JSON.stringify(data) });

const qs = (params: Record<string, string | number | undefined>) => {
  const s = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== '') s.set(k, String(v));
  }
  const str = s.toString();
  return str ? `?${str}` : '';
};

export type PageQuery = { page?: number; per_page?: number };

/* ---------------- endpoints ---------------- */

export const api = {
  // auth
  login: (username: string, password: string) =>
    post<AuthTokens | { pending_2fa_token: string }>('/auth/login', { username, password }),
  register: (username: string, password: string, email: string) =>
    post<AuthTokens>('/auth/register', { username, password, email }),

  // public
  stats: () => get<PlatformStats>('/platform/stats'),
  games: (p: PageQuery & { keyword?: string } = {}) => get<Paged<Game>>(`/game/list${qs(p)}`),
  suggest: (q: string) =>
    get<{ suggestions: Array<{ id: string; name: string; slug: string }> }>(
      `/game/suggest${qs({ q })}`,
    ),
  gameDetail: (hashid: string) => get<Game>(`/game/detail/${hashid}`),
  launch: (gameId: string) => post<{ session_id: string; api_endpoint: string | null }>(
    '/game/launch',
    { game_id: gameId },
  ),

  // account
  profile: () => get<Profile>('/user/profile'),
  wallet: () => get<WalletInfo>('/wallet/info'),
  transactions: (p: PageQuery = {}) => get<Paged<Transaction>>(`/wallet/transactions${qs(p)}`),
  deposits: (p: PageQuery = {}) => get<Paged<DepositOrder>>(`/deposit/orders${qs(p)}`),
  withdraws: (p: PageQuery = {}) => get<Paged<WithdrawOrder>>(`/withdraw/orders${qs(p)}`),

  // notifications
  notices: (p: PageQuery = {}) => get<Paged<Notice>>(`/notification/list${qs(p)}`),
  unreadCount: () => get<{ count: number }>('/notification/unread-count'),
  markRead: (id?: string) => post<unknown>('/notification/read', id ? { id } : {}),
};
