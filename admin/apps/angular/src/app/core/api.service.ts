/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { HttpClient, HttpErrorResponse, HttpResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { Auth, SessionUser } from './auth.service';
import { I18n } from './i18n/i18n';
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

/** 未识别的后端结构一律走 Record，模板侧用 dash()/rowsAny() 防御性取值 */
export type Row = Record<string, unknown>;
export type Params = Record<string, string | number | undefined>;
type Method = 'GET' | 'POST' | 'PUT' | 'DELETE';

const REFRESH = 'ga_refresh_token';

/** 信封式 401：AdminAuth 拒绝是 HTTP 200 + {code:401}（app/middleware/AdminAuth.php:34） */
function code401(body: unknown): boolean {
  return typeof body === 'object' && body !== null && (body as Row)['code'] === 401;
}

function query(params: Params): string {
  const parts = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== '')
    .map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`);
  return parts.length ? `?${parts.join('&')}` : '';
}

/** blob → 文本；读不出来回空串（走下坡路时才用，不该因为解析再抛一层） */
async function blobText(b: unknown): Promise<string> {
  try {
    return b instanceof Blob ? await b.text() : '';
  } catch {
    return '';
  }
}

/** 文本 → 信封；`code` 不是数字就返回 null（那它不是本应用的错误响应） */
function asEnvelope(text: string): { code: number; message: string } | null {
  try {
    const o = JSON.parse(text) as Row;
    if (typeof o['code'] !== 'number') return null;
    return { code: o['code'], message: String(o['message'] ?? '') };
  } catch {
    return null;
  }
}

/** Content-Disposition → 文件名。RFC 5987 的 `filename*` 优先，服务端实际发的是 `filename="…"` */
function fileName(cd: string | null): string | null {
  if (!cd) return null;
  const star = /filename\*=UTF-8''([^;]+)/i.exec(cd);
  if (star?.[1]) {
    try {
      return decodeURIComponent(star[1]);
    } catch {
      return star[1];
    }
  }
  return /filename="?([^";]+)"?/i.exec(cd)?.[1] ?? null;
}

/** Blob → 触发一次 <a download> 点击；URL 延后释放（立即 revoke 在部分浏览器会取消下载） */
function saveBlob(blob: Blob, name: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

@Injectable({ providedIn: 'root' })
export class Api {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(Auth);
  private readonly i18n = inject(I18n);
  private refreshing: Promise<boolean> | null = null;

  /** 会话用户（转发 Auth 的信号，页面直接绑定） */
  readonly user = this.auth.user;

  // ---------- 请求 ----------
  async request<T>(method: Method, url: string, body?: unknown, params?: Params): Promise<T> {
    return (await this.envelope<T>(method, url, body, params)).data;
  }

  /**
   * 同 request，但把信封里的 message 一并带出来 —— **资金动作的回执是服务端文案**：
   * 同一个 /withdraw/execute-payout 会回「打款成功」或「打款已提交」（渠道受理 ≠ 已到账），
   * 自己编一句「操作成功」就是把这两种结果糊成一种。
   */
  async envelope<T>(
    method: Method,
    url: string,
    body?: unknown,
    params?: Params,
  ): Promise<{ data: T; message: string }> {
    const full = url + query(params ?? {});
    try {
      const env = await this.once<T>(method, full, body);
      return { data: env.data, message: env.message };
    } catch (e) {
      // 401 有两条来路：信封里 code=401（HTTP 200），或 HTTP 401 —— 都在这条 catch 上
      if (e instanceof ApiError && e.code === 401 && !url.startsWith('/api/v1/auth/')) {
        if (await this.refreshOnce()) {
          const env = await this.once<T>(method, full, body);
          return { data: env.data, message: env.message };
        }
        this.auth.clear();
        throw new ApiError(401, this.i18n.t('app.session_expired'));
      }
      throw e;
    }
  }

  /**
   * 原生响应：**不判 code、不拆 data**，body 原样回（aetherupload 那种自带 error/savedPath 的
   * 非信封协议走这条）。401 两条来路都接，与 envelope() 同一口径：刷新后重放一次，刷不动就清会话。
   * 401 非 2xx 由 sendRaw 抛 ApiError；信封 401 是 HTTP 200，得自己认（code401）。
   */
  async raw<T>(method: Method, url: string, body?: unknown): Promise<T> {
    const retryable = !url.startsWith('/api/v1/auth/');
    try {
      const res = await this.sendRaw<T>(method, url, body);
      if (retryable && code401(res)) throw new ApiError(401, this.i18n.t('app.not_logged_in'));
      return res;
    } catch (e) {
      if (retryable && e instanceof ApiError && e.code === 401) {
        if (await this.refreshOnce()) return this.sendRaw<T>(method, url, body);
        this.auth.clear();
        throw new ApiError(401, this.i18n.t('app.session_expired'));
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

  /** 成功即回整个信封（message 也要），失败一律抛 ApiError */
  private async once<T>(method: Method, url: string, body?: unknown): Promise<Envelope<T>> {
    let env = await this.send<T>(method, url, body);
    if (env.code === 401 && !url.startsWith('/api/v1/auth/')) {
      if (await this.refreshOnce()) env = await this.send<T>(method, url, body);
      if (env.code === 401)
        throw new ApiError(401, env.message || this.i18n.t('app.session_expired'));
    }
    if (env.code !== 0)
      throw new ApiError(
        env.code,
        env.message || this.i18n.t('app.request_failed', { code: env.code }),
      );
    return env;
  }

  private send<T>(method: Method, url: string, body?: unknown): Promise<Envelope<T>> {
    return this.sendRaw<Envelope<T>>(method, url, body);
  }

  private async sendRaw<T>(method: Method, url: string, body?: unknown): Promise<T> {
    const token = this.auth.token;
    // 语言头每次都得挂：后端 `LanguageMiddleware` 按 `X-Language` → `Accept-Language` → 默认 zh
    // 选翻译表。漏了它，界面切成英文/日文时服务端的 message（校验错误、资金回执）仍是中文
    // —— 「支持 13 种语言」就只落了一半。值是小写短码（后端 normalize() 认短码与 zh-CN 全码）。
    const headers: Record<string, string> = { 'X-Language': this.i18n.lang() };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    try {
      return await firstValueFrom(this.http.request<T>(method, url, { body, headers }));
    } catch (e) {
      if (e instanceof HttpErrorResponse) {
        // 422 校验失败 / 404 路由不存在：body 仍是信封时优先用它的 message
        const env = e.error as Envelope<unknown> | null;
        if (env && typeof env.code === 'number') {
          throw new ApiError(
            env.code,
            env.message || this.i18n.t('app.request_failed', { code: env.code }),
          );
        }
        throw new ApiError(
          e.status,
          e.status === 0 ? this.i18n.t('app.server_unreachable') : `HTTP ${e.status}`,
        );
      }
      throw new ApiError(0, this.i18n.t('app.network_error'));
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
  /** 点击验证码：POST 取图（route.php:301 只注册了 POST，用 GET 会命中框架层 405） */
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

  /**
   * 登出。**必须打到服务端** —— `POST /admin/v1/profile/logout` 才把 access 令牌写进 Redis
   * 黑名单并吊销本会话的 refresh（ProfileController::logout）。原先这里只 `auth.clear()`
   * 清 localStorage ⇒ 令牌在自然过期前一直有效、还能换新 access，点「退出」等于没退。
   *
   * 服务端失败也要清本地会话：否则网络不通时用户被卡在登录态里退不出去（代价是那个令牌
   * 要等自己过期，比"退不出去"轻）。
   */
  async logout(): Promise<void> {
    try {
      await this.post('/admin/v1/profile/logout');
    } catch {
      /* 令牌已过期/服务端不可达：本地照清 */
    }
    this.auth.clear();
  }

  // ---------- 文件下载 ----------

  /**
   * 下载导出文件，返回落盘用的文件名。
   *
   * 这类端点回的是**二进制附件**而不是信封，但**校验失败时回的又是信封**
   * （`ExportController::receipt` 的 422「订单不存在」、`ReportController::export` 的 400
   * 「日期范围超 90 天」）⇒ 按 content-type 分流。不分流的话，「导出失败」会静默变成
   * 下载一个内容是错误 JSON 的 .xlsx，用户打开才发现。
   *
   * 401 与别处同一条路：刷新一次重放，刷不动就抛。
   */
  async download(method: Method, url: string, body?: unknown, fallback = 'export'): Promise<string> {
    try {
      return await this.saveFile(method, url, body, fallback);
    } catch (e) {
      if (e instanceof ApiError && e.code === 401 && (await this.refreshOnce())) {
        return this.saveFile(method, url, body, fallback);
      }
      throw e;
    }
  }

  private async saveFile(
    method: Method,
    url: string,
    body: unknown,
    fallback: string,
  ): Promise<string> {
    let res: HttpResponse<Blob>;
    try {
      res = await this.sendFile(method, url, body);
    } catch (e) {
      if (e instanceof ApiError) throw e;
      if (e instanceof HttpErrorResponse) {
        // 非 2xx（422/404）错误体也是 blob，读出来再按信封解析
        const env = asEnvelope(await blobText(e.error));
        throw new ApiError(
          env?.code ?? e.status,
          env?.message || this.i18n.t('app.request_failed', { code: e.status }),
        );
      }
      throw new ApiError(0, this.i18n.t('app.network_error'));
    }
    const blob = res.body ?? new Blob();
    // HTTP 200 + JSON = 信封式失败（本应用业务失败一律 200，见文件头）
    if ((res.headers.get('content-type') ?? '').includes('json')) {
      const env = asEnvelope(await blob.text());
      throw new ApiError(
        env?.code ?? 0,
        env?.message || this.i18n.t('app.request_failed', { code: env?.code ?? 0 }),
      );
    }
    // 文件名优先取 Content-Disposition：服务端带的是一串时间戳/订单号
    // （export_users_20261001.xlsx、receipt_NO123.pdf），自己拼一个只会与内容对不上
    const name = fileName(res.headers.get('content-disposition')) ?? fallback;
    saveBlob(blob, name);
    return name;
  }

  /**
   * 与 sendRaw 同头（语言 + 认证），差别只在要拿到响应头与二进制体。
   *
   * GET 的 body 一律转成查询串：XHR 会**丢掉** GET 的 body、fetch 后端更直接抛
   * 「Request with GET/HEAD method cannot have body」，而 /report/export 恰好是 GET
   * ⇒ 直接透传会出现「参数没发出去、服务端按缺省值导了一份别的日期范围」这种静默错。
   * POST 那几个（/export/*）本来就是 JSON body，原样发。
   */
  private sendFile(method: Method, url: string, body?: unknown): Promise<HttpResponse<Blob>> {
    const headers: Record<string, string> = { 'X-Language': this.i18n.lang() };
    const token = this.auth.token;
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const get = method === 'GET';
    const full = get ? url + query((body ?? {}) as Params) : url;
    return firstValueFrom(
      this.http.request(method, full, {
        body: get ? undefined : body,
        headers,
        observe: 'response',
        responseType: 'blob',
      }),
    );
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
