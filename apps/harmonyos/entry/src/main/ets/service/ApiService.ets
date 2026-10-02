/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import http from '@ohos.net.http';
import { ApiResponse, LoginResponse } from '../model/DataModels';
import { TokenManager } from '../utils/TokenManager';
import { API_BASE_URL } from '../common/AppConfig';
import { LocaleController } from '../i18n/LocaleController';

/**
 * HTTP API 服务 — 支持 JWT 无感刷新
 */
export class ApiService {
  private httpRequest: http.HttpRequest;

  /** 进行中的刷新（单飞），见 tryRefreshToken */
  private static refreshInFlight: Promise<boolean> | null = null;

  constructor() {
    this.httpRequest = http.createHttp();
  }

  /**
   * 发送请求（自动处理 401 和 token 刷新）
   */
  private async request<T>(method: http.RequestMethod, path: string, body?: object, retryCount: number = 0): Promise<ApiResponse<T>> {
    const token = TokenManager.getAccessToken();

    const options: http.HttpRequestOptions = {
      method: method,
      header: {
        'Content-Type': 'application/json',

        'X-Client-Platform': 'harmonyos',
        // 语言必须先于业务请求发出去：后端 `LanguageMiddleware::detectLocale()` 的顺序是
        // `X-Language` → `Accept-Language` → 配置默认(zh)。**不发这个头就落到第二步** ——
        // 设备是法语时服务端吐法语而界面是中文（同屏两语言）；**恒发 `en` 更糟**，
        // 那会把 zh 默认主动压掉。值用短码（后端 `common\Locale::normalize()` 认短码与全码）。
        'X-Language': LocaleController.currentCode,
        'Authorization': (token && token.length > 0) ? `Bearer ${token}` : '',
      },
      connectTimeout: 15000,
      readTimeout: 15000,
    };

    if (body) {
      options.extraData = JSON.stringify(body);
    }

    const url = `${API_BASE_URL}${path}`;
    const response = await this.httpRequest.request(url, options);

    if (response.responseCode === 401 && retryCount === 0) {
      const refreshed = await this.tryRefreshToken();
      if (refreshed) {
        return this.request<T>(method, path, body, retryCount + 1);
      }
      TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    if (response.responseCode === 401) {
      TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    const result: ApiResponse<T> = JSON.parse(response.result as string);

    // 服务端 json() 信封恒为 HTTP 200，鉴权失败体现在 body.code，上面的状态码分支看不到
    if (result.code === 401) {
      if (retryCount === 0) {
        const refreshed = await this.tryRefreshToken();
        if (refreshed) {
          return this.request<T>(method, path, body, retryCount + 1);
        }
      }
      TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    return result;
  }

  /**
   * 尝试用 refresh_token 刷新 access_token。
   * 单飞：并发请求同时撞 401 时只发一次刷新请求——refresh token 是一次性轮换的，
   * 第二次刷新会因旧 token 已进黑名单而失败，进而误清 token 登出。
   */
  private tryRefreshToken(): Promise<boolean> {
    const inFlight = ApiService.refreshInFlight;
    if (inFlight !== null) {
      return inFlight;
    }
    const started = this.doRefreshToken().finally(() => {
      ApiService.refreshInFlight = null;
    });
    ApiService.refreshInFlight = started;
    return started;
  }

  private async doRefreshToken(): Promise<boolean> {
    const refreshToken = TokenManager.getRefreshToken();
    if (!refreshToken) {
      return false;
    }

    try {
      const refreshResponse = await this.httpRequest.request(
        `${API_BASE_URL}/api/v1/auth/refresh`,
        {
          method: http.RequestMethod.POST,
          header: {
            'Content-Type': 'application/json',

            'X-Client-Platform': 'harmonyos',
            // 刷新绕过了 request()，语言头得单独补：/auth/refresh 的失败 message 也走 trans()
            'X-Language': LocaleController.currentCode,
            'Authorization': `Bearer ${refreshToken}`,
          },
          extraData: JSON.stringify({ refresh_token: refreshToken }),
          connectTimeout: 10000,
          readTimeout: 10000,
        }
      );

      if (refreshResponse.responseCode === 200) {
        // 刷新接口的 data 与登录同为 token 对
        const data: ApiResponse<LoginResponse> = JSON.parse(refreshResponse.result as string);
        if (data.code === 0 && data.data) {
          TokenManager.saveTokens(
            data.data.access_token,
            data.data.refresh_token
          );
          return true;
        }
      }
    } catch (_) {
      // 刷新请求本身失败（网络错误等）
    }

    return false;
  }

  async get<T>(path: string): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.GET, path);
  }

  async post<T>(path: string, body?: object): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.POST, path, body);
  }

  async put<T>(path: string, body?: object): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.PUT, path, body);
  }

  async delete<T>(path: string): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.DELETE, path);
  }
}

export const apiService = new ApiService();
