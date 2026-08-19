/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * JWT Token 管理（AppStorage 简化实现）
 */
export class TokenManager {
  private static ACCESS_TOKEN_KEY = 'access_token';
  private static REFRESH_TOKEN_KEY = 'refresh_token';
  private static USERNAME_KEY = 'username';

  static async saveTokens(accessToken: string, refreshToken: string): Promise<void> {
    AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, accessToken);
    AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, refreshToken);
  }

  static saveUsername(username: string): void {
    AppStorage.setOrCreate(TokenManager.USERNAME_KEY, username);
  }

  static getAccessToken(): string {
    return AppStorage.get(TokenManager.ACCESS_TOKEN_KEY) as string ?? '';
  }

  static getRefreshToken(): string {
    return AppStorage.get(TokenManager.REFRESH_TOKEN_KEY) as string ?? '';
  }

  static getUsername(): string {
    return AppStorage.get(TokenManager.USERNAME_KEY) as string ?? '';
  }

  static clearTokens(): void {
    AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, '');
    AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, '');
    AppStorage.setOrCreate(TokenManager.USERNAME_KEY, '');
  }

  static isLoggedIn(): boolean {
    return (TokenManager.getAccessToken() ?? '').length > 0;
  }
}
