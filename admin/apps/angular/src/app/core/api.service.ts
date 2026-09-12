/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { Auth, SessionUser } from './auth.service';
import { num } from './util';

export type { SessionUser };

/**
 * 后端统一信封：成功 code === 0。
 * 注意 —— 业务失败（401/403/429）也随 HTTP 200 返回，所以拦截器判状态码没有意义，
 * 只能在信封层判；HTTP 层非 2xx（含 422 校验失败）body 同样是信封。
 */
interface Envelope<T> {
  code: number;
  message: string;
  data: T;
}

export class ApiError extends Error {
  constructor(
    readonly code: number,
    message: string,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export interface Page<T> {
  list: T[];
  total: number;
  page: number;
  limit: number;
}

export interface Click {
  x: number;
  y: number;
}

export interface CaptchaChallenge {
  key: string;
  image: string;
  texts: string[];
}

/** 未识别的后端结构一律走 Record，模板侧用 dash()/rowsOf() 防御性取值 */
export type Row = Record<string, unknown>;
export type Params = Record<string, string | number | undefined>;
type Method = 'GET' | 'POST' | 'PUT';

const REFRESH = 'ga_refresh_token';

function query(params: Params): string {
  const parts = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== '')
    .map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`);
  return parts.length ? `?${parts.join('&')}` : '';
}

@Injectable({ providedIn: 'root' })
export class Api {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(Auth);
  private refreshing: Promise<boolean> | null = null;

  /** 会话用户（转发 Auth 的信号，页面直接绑定） */
  readonly user = this.auth.user;

  // ---------- 请求 ----------
  async request<T>(method: Method, url: string, body?: unknown, params?: Params): Promise<T> {
    const full = url + query(params ?? {});
    try {
      return await this.once<T>(method, full, body);
    } catch (e) {
      // 401 有两条来路：信封里 code=401（HTTP 200），或 HTTP 401 —— 都在这条 catch 上
      if (e instanceof ApiError && e.code === 401 && !url.startsWith('/api/v1/auth/')) {
        if (await this.refreshOnce()) return await this.once<T>(method, full, body);
        this.auth.clear();
        throw new ApiError(401, '登录状态已失效，请重新登录');
      }
      throw e;
    }
  }

  get<T>(url: string, params?: Params): Promise<T> {
    return this.request<T>('GET', url, undefined, params);
  }

  post<T>(url: string, body?: unknown): Promise<T> {
    return this.request<T>('POST', url, body);
  }

  /** GET 原始文本（Prometheus /metrics 之类非 JSON 响应） */
  async getText(url: string): Promise<string> {
    return firstValueFrom(this.http.get(url, { responseType: 'text' }));
  }

  /** 列表通用取数：后端 page_size / limit 命名不确定，读不出来就退回请求参数 */
  async list<T>(url: string, params?: Params): Promise<Page<T>> {
    // 后端读分页参数的口径不统一：limit（15 个控制器，默认 15）、size（7 个风控类，
    // 默认 20）、per_page（SearchController）。只发 page_size 谁都不认识，服务端就退回
    // 自己的默认值；而 list-base 的 pageSize 恒为 20，于是 pages=ceil(total/20) 偏小，
    // 尾页永远取不到（total=100 时第 76~100 条不可达）。别名全发，各控制器读它认识的那个。
    const send: Params = { page: 1, ...params };
    const n = send['page_size'];
    if (n !== undefined) {
      send['limit'] = n;
      send['size'] = n;
      send['per_page'] = n;
    }
    const raw = await this.get<unknown>(url, send);
    if (Array.isArray(raw)) {
      return { list: raw as T[], total: raw.length, page: 1, limit: raw.length };
    }
    const o = (raw ?? {}) as Record<string, unknown>;
    const rows = o['list'] ?? o['items'] ?? o['rows'] ?? o['data'];
    const list = Array.isArray(rows) ? (rows as T[]) : [];
    return {
      list,
      total: num(o['total'] ?? o['count'] ?? o['total_count'] ?? list.length),
      page: num(o['page'] ?? o['current_page'] ?? params?.['page'] ?? 1),
      limit: num(o['limit'] ?? o['page_size'] ?? o['per_page'] ?? list.length),
    };
  }

  private async once<T>(method: Method, url: string, body?: unknown): Promise<T> {
    let env = await this.send<T>(method, url, body);
    if (env.code === 401 && !url.startsWith('/api/v1/auth/')) {
      if (await this.refreshOnce()) env = await this.send<T>(method, url, body);
      if (env.code === 401) throw new ApiError(401, env.message || '登录状态已失效，请重新登录');
    }
    if (env.code !== 0) throw new ApiError(env.code, env.message || `请求失败（${env.code}）`);
    return env.data;
  }

  private async send<T>(method: Method, url: string, body?: unknown): Promise<Envelope<T>> {
    const token = this.auth.token;
    try {
      return await firstValueFrom(
        this.http.request<Envelope<T>>(method, url, {
          body,
          headers: token ? { Authorization: `Bearer ${token}` } : undefined,
        }),
      );
    } catch (e) {
      if (e instanceof HttpErrorResponse) {
        // 422 校验失败 / 404 路由不存在：body 仍是信封时优先用它的 message
        const env = e.error as Envelope<unknown> | null;
        if (env && typeof env.code === 'number') {
          throw new ApiError(env.code, env.message || `请求失败（${env.code}）`);
        }
        throw new ApiError(e.status, e.status === 0 ? '无法连接服务器' : `HTTP ${e.status}`);
      }
      throw new ApiError(0, '网络异常');
    }
  }

  /** 并发 401 共享同一个刷新 promise，避免刷新风暴 */
  private refreshOnce(): Promise<boolean> {
    this.refreshing ??= this.doRefresh().finally(() => {
      this.refreshing = null;
    });
    return this.refreshing;
  }

  private async doRefresh(): Promise<boolean> {
    const rt = localStorage.getItem(REFRESH);
    if (!rt) return false;
    try {
      const env = await this.send<{ access_token: string; refresh_token?: string }>(
        'POST',
        '/api/v1/auth/refresh',
        { refresh_token: rt },
      );
      if (env.code !== 0 || !env.data?.access_token) return false;
      this.auth.set(env.data);
      return true;
    } catch {
      return false;
    }
  }

  // ---------- 认证 ----------
  /** 点击验证码：POST 取图（route.php 注册为 POST，GET 会被 SecurityFilter 判 405） */
  async captcha(): Promise<CaptchaChallenge> {
    const raw = await this.post<Row>('/api/v1/captcha/generate');
    const key = String(raw['captcha_key'] ?? raw['key'] ?? '');
    const image = String(raw['image'] ?? raw['captcha_image'] ?? '');
    const extra = (raw['extra'] ?? {}) as { texts?: { order?: number; text?: string }[] };
    const texts = (extra.texts ?? [])
      .slice()
      .sort((a, b) => num(a.order) - num(b.order))
      .map((t) => String(t.text ?? ''));
    return { key, image, texts };
  }

  login(payload: {
    username: string;
    password: string;
    captcha_key: string;
    clicks: Click[];
  }): Promise<{ access_token: string; refresh_token: string; user: SessionUser }> {
    return this.post('/api/v1/auth/login', payload);
  }

  logout(): Promise<void> {
    this.auth.clear();
    return Promise.resolve();
  }

  // ---------- 常用管理端接口（其余走 get/post/list 泛型） ----------
  dashboard<T = Row>(): Promise<T> {
    return this.get<T>('/admin/v1/dashboard');
  }

  ranking<T = Row>(days: number): Promise<T> {
    return this.get<T>('/admin/v1/analytics/game-ranking', { days });
  }

  trend<T = Row>(path: string, days: number): Promise<T> {
    return this.get<T>(`/admin/v1/analytics/${path}`, { days });
  }
}
