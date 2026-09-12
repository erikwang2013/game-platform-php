/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 统一 API 客户端：信封解包（code === 0 为成功）、Bearer 注入、
 * 401 单次刷新重试、错误上抛。令牌存于 localStorage。
 */

const ACCESS_KEY = 'react_admin_access_token';
const REFRESH_KEY = 'react_admin_refresh_token';
const USER_KEY = 'react_admin_user';

export type AdminUser = { id: string; username: string; real_name: string };

export type Envelope<T> = { code: number; message: string; data: T };

export class ApiError extends Error {
  readonly code: number;

  constructor(code: number, message: string) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
  }
}

export const session = {
  get user(): AdminUser | null {
    const raw = localStorage.getItem(USER_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as AdminUser;
    } catch {
      return null;
    }
  },
  get accessToken(): string | null {
    return localStorage.getItem(ACCESS_KEY);
  },
  save(accessToken: string, refreshToken: string, user?: AdminUser): void {
    localStorage.setItem(ACCESS_KEY, accessToken);
    localStorage.setItem(REFRESH_KEY, refreshToken);
    if (user) localStorage.setItem(USER_KEY, JSON.stringify(user));
  },
  clear(): void {
    localStorage.removeItem(ACCESS_KEY);
    localStorage.removeItem(REFRESH_KEY);
    localStorage.removeItem(USER_KEY);
  },
};

export type Query = Record<string, string | number | null | undefined>;

type Options = { method?: 'GET' | 'POST'; query?: Query; body?: unknown; auth?: boolean };

let refreshing: Promise<boolean> | null = null;

function buildUrl(path: string, query?: Query): string {
  if (!query) return path;
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value !== null && value !== undefined && value !== '') search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `${path}?${qs}` : path;
}

async function refreshAccessToken(): Promise<boolean> {
  const token = localStorage.getItem(REFRESH_KEY);
  if (!token) return false;
  try {
    const res = await fetch('/api/v1/auth/refresh', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: token }),
    });
    const json = (await res.json()) as Envelope<{ access_token: string; refresh_token: string }>;
    if (json.code !== 0) return false;
    session.save(json.data.access_token, json.data.refresh_token);
    return true;
  } catch {
    return false;
  }
}

export async function api<T>(path: string, options: Options = {}): Promise<T> {
  const useAuth = options.auth !== false;

  const send = (): Promise<Response> => {
    const headers: Record<string, string> = {};
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    const access = session.accessToken;
    if (useAuth && access) headers.Authorization = `Bearer ${access}`;
    return fetch(buildUrl(path, options.query), {
      method: options.method ?? 'GET',
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
    });
  };

  const parse = async (r: Response): Promise<Envelope<T>> => {
    try {
      return (await r.json()) as Envelope<T>;
    } catch {
      throw new ApiError(r.status, `服务异常（HTTP ${r.status}）`);
    }
  };

  let res = await send();
  let payload = await parse(res);

  // 鉴权失败是 HTTP 200 + body.code 401（两个后端中间件都不设 HTTP 状态），按信封 code 判定
  if (payload.code === 401 && useAuth) {
    // 并发 401 共用同一次刷新
    if (!refreshing) refreshing = refreshAccessToken();
    const ok = (await refreshing) ?? false;
    refreshing = null;
    if (ok) {
      res = await send();
      payload = await parse(res);
    } else {
      session.clear();
      if (window.location.pathname !== '/login') window.location.replace('/login');
      throw new ApiError(401, '登录已过期，请重新登录');
    }
  }

  if (payload.code !== 0) throw new ApiError(payload.code, payload.message || '请求失败');
  return payload.data;
}
