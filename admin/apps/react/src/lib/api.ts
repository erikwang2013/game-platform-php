/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 统一 API 客户端：信封解包（code === 0 为成功）、Bearer 注入、`X-Language` 注入、
 * 401 单次刷新重试、错误上抛。令牌存于 localStorage。
 */
import { currentCode, t } from '../i18n/index.ts';

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

type Options = { method?: 'GET' | 'POST' | 'PUT' | 'DELETE'; query?: Query; body?: unknown; auth?: boolean };

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
      headers: { 'Content-Type': 'application/json', 'X-Language': currentCode() },
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

/** 读 JSON；非 JSON（网关 5xx 的 HTML 页）按 HTTP 状态报。 */
async function readJson(r: Response): Promise<Record<string, unknown>> {
  try {
    return (await r.json()) as Record<string, unknown>;
  } catch {
    throw new ApiError(r.status, t('app.service_error', { status: r.status }));
  }
}

/**
 * 401 的统一处置：并发请求共用同一次刷新；刷新不成即清会话跳登录。返回是否已换到新令牌。
 * 鉴权失败是 HTTP 200 + body.code 401（两个后端中间件都不设 HTTP 状态），按信封 code 判定。
 */
async function reauth(): Promise<boolean> {
  if (!refreshing) refreshing = refreshAccessToken();
  const ok = (await refreshing) ?? false;
  refreshing = null;
  if (!ok) {
    session.clear();
    if (window.location.pathname !== '/login') window.location.replace('/login');
  }
  return ok;
}

/**
 * 发请求并交出整个信封。资金动作（打款执行/同步、审核）要显示**服务端的原话**，
 * 而 `api()` 只回 data，那句话在解包时就丢了 —— 于是拆出这一层，两者共用同一条请求/刷新链路。
 */
export async function apiEnvelope<T>(path: string, options: Options = {}): Promise<Envelope<T>> {
  const useAuth = options.auth !== false;

  const send = (): Promise<Response> => {
    // 语言必须随每个业务请求发出去：后端 `LanguageMiddleware` 按 X-Language → Accept-Language
    // → 默认 zh 选翻译表，不发这个头界面切了语言、服务端 message 也永远是中文。
    const headers: Record<string, string> = { 'X-Language': currentCode() };
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
      throw new ApiError(r.status, t('app.service_error', { status: r.status }));
    }
  };

  let res = await send();
  let payload = await parse(res);

  if (payload.code === 401 && useAuth) {
    if (await reauth()) {
      res = await send();
      payload = await parse(res);
    } else {
      throw new ApiError(401, t('app.session_expired'));
    }
  }

  // 服务端 message 已是翻译后的原话（后端按 X-Language 选表）；只有缺 message 时才用本地兜底
  if (payload.code !== 0) throw new ApiError(payload.code, payload.message || t('app.request_failed'));
  return payload;
}

/**
 * 不走信封的 POST：aetherupload 插件直接回 `{error, savedPath}`（没有 code/message/data），
 * 走 apiEnvelope 会因「无 code」被当成失败。鉴权头与 401 刷新沿用同一条链路。
 * Content-Type 交给 fetch 自己定：URLSearchParams = 普通表单、FormData = multipart（浏览器补 boundary）。
 */
export async function rawPost(path: string, body: URLSearchParams | FormData): Promise<Record<string, unknown>> {
  const send = (): Promise<Response> => {
    const headers: Record<string, string> = { 'X-Language': currentCode() };
    const access = session.accessToken;
    if (access) headers.Authorization = `Bearer ${access}`;
    return fetch(path, { method: 'POST', headers, body });
  };

  let payload = await readJson(await send());
  if (Number(payload.code) === 401) {
    if (!(await reauth())) throw new ApiError(401, t('app.session_expired'));
    payload = await readJson(await send());
  }
  return payload;
}

/** 只要 data 的常规用法（列表/详情的绝大多数调用）。 */
export async function api<T>(path: string, options: Options = {}): Promise<T> {
  return (await apiEnvelope<T>(path, options)).data;
}
