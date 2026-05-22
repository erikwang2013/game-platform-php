/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiResponse } from '../model/DataModels';

/**
 * JWT Token 管理
 */
export class TokenManager {
  private static ACCESS_TOKEN_KEY = 'access_token';
  private static REFRESH_TOKEN_KEY = 'refresh_token';

  static async saveTokens(accessToken: string, refreshToken: string): Promise<void> {
    // 使用首选项存储 token
    // 简化实现，生产环境使用 @ohos.data.preferences
    AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, accessToken);
    AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, refreshToken);
  }

  static getAccessToken(): string {
    return AppStorage.get(TokenManager.ACCESS_TOKEN_KEY) as string ?? '';
  }

  static getRefreshToken(): string {
    return AppStorage.get(TokenManager.REFRESH_TOKEN_KEY) as string ?? '';
  }

  static clearTokens(): void {
    AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, '');
    AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, '');
  }

  static isLoggedIn(): boolean {
    return (TokenManager.getAccessToken() ?? '').length > 0;
  }
}
