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
  /** 登录成功时由 LoginPage 写入（登录响应的 data.user.username） */
  private static ADMIN_USERNAME_KEY = 'adminUsername';

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

  /**
   * 当前登录管理员的用户名 —— 用于**自我保护**（停用/删除自己之前先认出自己是谁）。
   *
   * 为什么用用户名认人（`game_admin_user.username` 是唯一键 `uk_username`）：
   * 行里的 `id` 是 hashid，而 token 里的 `sub` 是裸 BIGINT，前端没有 hashids 编不回去；
   * 用户名相等 ⇔ 同一行。AppStorage 是**进程内内存**（access_token 也在里面，EntryAbility
   * 拿不到就落登录页），故不存在跨会话的陈旧值。
   * 后端不禁止自伤（`UserController::destroy`/`batchStatus` 只验操作者密码），所以这道闸只能在前端。
   */
  static getAdminUsername(): string {
    return (AppStorage.get(TokenManager.ADMIN_USERNAME_KEY) as string) ?? '';
  }
}
