/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * API 客户端的地基：信封解包 / Bearer 头 / 401 单飞刷新一次 / 错误透出。
 *
 * 为什么单独成文件：api.service.ts 挂满业务方法后**顶破了仓库的 500 行上限**，而这里
 * 是**与业务无关的传输层**，是能整体搬走、且搬完不留半截逻辑的唯一一块。
 *
 * 选**继承**（`Api extends ApiBase`）而不是拆成第二个可注入服务：拆服务要把 20 多个页面
 * 加 spec 的 `inject(Api)` 全改一遍，收益完全一样。继承则**一个调用点都不用动**。
 * 代价是子类必须 `extends ApiBase` —— 别把 `Api` 改成直接 `@Injectable` 的独立类。
 *
 * `request` 是 `protected` 而非 `private`：子类要靠它发请求。
 *
 * 401 的判定看 **body.code** 不看 HTTP status —— 后端所有响应都是 HTTP 200 + 信封。
 */
import { HttpClient, HttpErrorResponse, HttpParams } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, from, lastValueFrom, map, of, switchMap, throwError } from 'rxjs';
import type { AuthResult, Envelope } from './api.types';
import { ApiError, tokens } from './session';

type Query = Record<string, string | number | boolean | undefined | null>;

export const BASE = '/api/v1';

export abstract class ApiBase {
  protected readonly http = inject(HttpClient);
  protected readonly router = inject(Router);
  private refreshing: Promise<boolean> | null = null;

  protected request<T>(
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
}
