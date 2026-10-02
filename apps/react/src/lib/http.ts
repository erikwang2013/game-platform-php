/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 传输层：统一拆封后端信封 { code, message, data }（成功 code === 0）。
 * 401 时尝试 refresh 一次，失败则清 token 并回调登出。
 *
 * 端点目录在 `api.ts`（那里 re-export 本文件的 ApiError / tokens / language / …，
 * 所以既有的 `from './api.ts'` 一行都不用改）。
 *
 * 唯一的 import 是 `i18n/index.ts` —— 那一层**刻意不引 React**（见该文件 :5 注释），
 * 所以传输层仍然与 React 无关。除它之外只用全局的 fetch / Headers / localStorage。
 * **别从这里 import `i18n/useI18n.ts`**：那会把 React 拖进请求路径。
 */
import { t } from '../i18n/index.ts';


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

/**
 * 语言。`get()` 的兜底**必须是 `'zh'`** —— 本树生产代码从不写 `K_LANG`（`set()` 的调用点
 * 全在测试里），所以兜底值**就是**实际发出去的 `X-Language` 值。
 *
 * 留 `'en'` 不行：它不是「用户没选」的中性表示，而是把服务端 `LanguageMiddleware::detectLocale`
 * 的默认（zh，见该文件 :58 注释「保持中文」）主动压掉 ⇒ 全中文界面里弹英文响应文案。
 * 改成「没选过就不发这个头」也不行：会掉到那条链路的第 2 步 Accept-Language，
 * 非中文 locale 的浏览器（`Locale::normalize` 取主语言子标签、`en` 在白名单里）同样拿到英文。
 * 要的是**默认两边同语言**，那就只能显式给 zh。
 */
export const language = {
  get: () => localStorage.getItem(K_LANG) || 'zh',
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

/** 上传侧（lib/upload.ts）也复用这一支：那两个端点的 401 同样是信封，刷新后重试一次 */
export function refreshOnce(): Promise<boolean> {
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

export async function request<T>(path: string, init: RequestInit = {}, retry = true): Promise<T> {
  const headers = new Headers(init.headers);
  if (init.body) headers.set('Content-Type', 'application/json');
  headers.set('X-Language', language.get());
  const at = tokens.access();
  if (at) headers.set('Authorization', `Bearer ${at}`);

  let res: Response;
  try {
    res = await fetch(`${BASE}${path}`, { ...init, headers });
  } catch {
    throw new ApiError(-1, t('error.network_connection'));
  }

  const body = await res.json().catch(() => null);

  // 鉴权失败是 HTTP 200 + body.code 401（两个后端中间件都不设 HTTP 状态），按信封 code 判定。
  // 只在**本次确实带了 access token** 时才当会话过期处理：登录页密码错误、2FA 票据失效这类
  // 未登录请求的 401 是业务错误，走刷新+登出分支会把服务端原因（"用户名或密码错误"）
  // 换成"登录状态已过期"，用户按提示重登还是错。
  if (body?.code === 401 && retry && at) {
    if (await refreshOnce()) return request<T>(path, init, false);
    tokens.clear();
    onUnauthorized();
    throw new ApiError(401, t('error.session_expired'));
  }

  if (body && typeof body.code === 'number') {
    if (body.code !== 0) throw new ApiError(body.code, body.message || t('error.request_failed'));
    return body.data as T;
  }
  if (!res.ok) throw new ApiError(res.status, t('error.request_failed_http', { status: res.status }));
  throw new ApiError(-1, t('error.bad_response'));
}

export const get = <T,>(path: string) => request<T>(path);
export const post = <T,>(path: string, data: unknown) =>
  request<T>(path, { method: 'POST', body: JSON.stringify(data) });
export const put = <T,>(path: string, data: unknown) =>
  request<T>(path, { method: 'PUT', body: JSON.stringify(data) });

export const qs = (params: Record<string, string | number | undefined>) => {
  const s = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== '') s.set(k, String(v));
  }
  const str = s.toString();
  return str ? `?${str}` : '';
};

export type PageQuery = { page?: number; per_page?: number };
