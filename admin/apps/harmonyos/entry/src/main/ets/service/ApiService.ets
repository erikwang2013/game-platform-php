/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import http from '@ohos.net.http';
import { ApiResponse } from '../model/DataModels';
import { TokenManager } from '../utils/TokenManager';
import { API_BASE_URL } from '../common/AppConfig';

/**
 * 只用来探 `body.code`：信封端点（标准控制器）与裸 result 端点（aetherupload 插件）共用一条 401 判定
 */
interface ErrorProbe {
  code?: number;
}

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
   * 传输 + 401 无感刷新：返回**解析后的响应体本体**，不做信封解包。
   *
   * 标准控制器回 {code,message,data} 信封，aetherupload 插件端点直接 json() 出 result 本体 ——
   * 两条路的差异留给调用方（request 按信封读，postRaw/postMultipart 按裸对象读）。
   * 401 的两个来源都要覆盖：HTTP 状态码（框架层拒绝）与 body.code（服务端信封恒 200）。
   */
  private async send(method: http.RequestMethod, path: string, body?: object,
    retryCount: number = 0, parts?: Array<http.MultiFormData>): Promise<Object> {
    const token = TokenManager.getAccessToken();

    const header: Record<string, string> = {};
    // parts 存在时**不设** Content-Type：multipart 的 boundary 由 net 栈自己拼进这个头，
    // 手写一个不带 boundary 的会盖掉它，服务端就切不出分块（$request->file() 拿到 null）
    if (parts === undefined) {
      header['Content-Type'] = 'application/json';
    }
    header['X-Client-Platform'] = 'harmonyos';
    header['Authorization'] = (token && token.length > 0) ? `Bearer ${token}` : '';

    const options: http.HttpRequestOptions = {
      method: method,
      header: header,
      connectTimeout: 15000,
      // multipart 是 5MB 级的单块上传，读完要时间，读超时单独放宽
      readTimeout: parts === undefined ? 15000 : 60000,
    };

    if (parts !== undefined) {
      options.multiFormDataList = parts;
    } else if (body) {
      options.extraData = JSON.stringify(body);
    }

    const url = `${API_BASE_URL}${path}`;
    const response = await this.httpRequest.request(url, options);

    // 401 时先尝试刷新 token，而不是直接踢回登录页
    if (response.responseCode === 401 && retryCount === 0) {
      const refreshed = await this.tryRefreshToken();
      if (refreshed) {
        // 刷新成功，用新 token 重试原始请求
        return this.send(method, path, body, retryCount + 1, parts);
      }
      // 刷新失败，清除 token 并通知调用方
      TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    if (response.responseCode === 401) {
      TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    const result: Object = JSON.parse(response.result as string);
    const probe = result as ErrorProbe;

    // 服务端 json() 信封恒为 HTTP 200，鉴权失败体现在 body.code，上面的状态码分支看不到
    if (probe.code === 401) {
      if (retryCount === 0) {
        const refreshed = await this.tryRefreshToken();
        if (refreshed) {
          return this.send(method, path, body, retryCount + 1, parts);
        }
      }
      TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    return result;
  }

  /**
   * 发送请求（自动处理 401 和 token 刷新）
   */
  private async request<T>(method: http.RequestMethod, path: string, body?: object, retryCount: number = 0): Promise<ApiResponse<T>> {
    const result = await this.send(method, path, body, retryCount);
    return result as ApiResponse<T>;
  }

  /**
   * 尝试用 refresh_token 刷新 access_token。
   * 单飞：并发请求同时撞 401 时只发一次刷新——refresh token 是一次性轮换的
   * （旧 jti 立即入黑名单，见 admin AuthController::refresh），第二次刷新会因旧票已废
   * 而失败，进而误清 token 把用户登出。
   * @returns 刷新成功返回 true
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

          },
          extraData: JSON.stringify({ refresh_token: refreshToken }),
          connectTimeout: 10000,
          readTimeout: 10000,
        }
      );

      if (refreshResponse.responseCode === 200) {
        const data = JSON.parse(refreshResponse.result as string);
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

  /** 可带 body：系统配置删除要管理员密码（ConfigController::destroy → confirmPassword） */
  async delete<T>(path: string, body?: object): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.DELETE, path, body);
  }

  /**
   * 裸 JSON 端点（不经 {code,message,data} 信封）—— aetherupload 插件直接回 result 本体。
   * 鉴权与刷新语义与 post 完全一致（同走 send）。
   */
  async postRaw<T>(path: string, body?: object): Promise<T> {
    const result = await this.send(http.RequestMethod.POST, path, body, 0);
    return result as T;
  }

  /**
   * multipart 上传（文件分块走这里）：body 是 multiFormDataList，不能被 JSON.stringify。
   * Content-Type 由 net 栈按 boundary 自填，见 send 里 header 的分支。
   */
  async postMultipart<T>(path: string, parts: Array<http.MultiFormData>): Promise<T> {
    const result = await this.send(http.RequestMethod.POST, path, undefined, 0, parts);
    return result as T;
  }
}

export const apiService = new ApiService();
