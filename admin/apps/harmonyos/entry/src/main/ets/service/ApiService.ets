/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import http from '@ohos.net.http';
import { ApiResponse } from '../model/DataModels';
import { TokenManager } from '../utils/TokenManager';

// 开发环境地址 — 真机部署时修改为实际服务器地址
// 模拟器: http://10.0.2.2:8787
// 真机: http://<服务器IP>:8787
const BASE_URL = 'http://10.0.2.2:8787'; // 模拟器访问宿主机

/**
 * HTTP API 服务 — 支持 JWT 无感刷新
 */
export class ApiService {
  private httpRequest: http.HttpRequest;

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
        'API-Version': 'v1',
        'X-Client-Platform': 'harmonyos',
        'Authorization': (token && token.length > 0) ? `Bearer ${token}` : '',
      },
      connectTimeout: 15000,
      readTimeout: 15000,
    };

    if (body) {
      options.extraData = JSON.stringify(body);
    }

    const url = `${BASE_URL}${path}`;
    const response = await this.httpRequest.request(url, options);

    // 401 时先尝试刷新 token，而不是直接踢回登录页
    if (response.responseCode === 401 && retryCount === 0) {
      const refreshed = await this.tryRefreshToken();
      if (refreshed) {
        // 刷新成功，用新 token 重试原始请求
        return this.request<T>(method, path, body, retryCount + 1);
      }
      // 刷新失败，清除 token 并通知调用方
      TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    if (response.responseCode === 401) {
      TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    const result: ApiResponse<T> = JSON.parse(response.result as string);
    return result;
  }

  /**
   * 尝试用 refresh_token 刷新 access_token
   * @returns 刷新成功返回 true
   */
  private async tryRefreshToken(): Promise<boolean> {
    const refreshToken = TokenManager.getRefreshToken();
    if (!refreshToken) {
      return false;
    }

    try {
      const refreshResponse = await this.httpRequest.request(
        `${BASE_URL}/api/auth/refresh`,
        {
          method: http.RequestMethod.POST,
          header: {
            'Content-Type': 'application/json',
            'API-Version': 'v1',
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

  async delete<T>(path: string): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.DELETE, path);
  }
}

export const apiService = new ApiService();
