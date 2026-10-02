/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import preferences from '@ohos.data.preferences';
import common from '@ohos.app.ability.common';
import hilog from '@ohos.hilog';

const TAG = 'TokenManager';

/** 应用私有目录下的 preferences 文件名 */
const PREF_STORE = 'game_platform_auth';

/**
 * JWT Token 管理
 *
 * AppStorage 是**纯内存**：冷启动即空 ⇒ `isLoggedIn()` 恒 false ⇒ EntryAbility 里
 * `GameHallPage` 那一支是死代码，每次启动都要重输账号 + 密码 + 过验证码。
 * 所以三个 key 同时落到 preferences 上：init() 冷启动读回内存、写操作同步落盘、
 * 登出走 deleteSync（真删盘上的值，不是只清内存）。
 *
 * 对外方法签名保持同步不变 —— ApiService 的请求路径与 EntryAbility 的启动页分支都在同步上下文里，
 * preferences 的 Sync 系列 API（@since 11，本机 SDK 已核实存在）正好满足；
 * 异步版 getPreferences/put/flush 会波及全部调用方，不采用。
 */
export class TokenManager {
  private static ACCESS_TOKEN_KEY = 'access_token';
  private static REFRESH_TOKEN_KEY = 'refresh_token';
  private static USERNAME_KEY = 'username';

  /** 磁盘句柄；init() 之前为 null，此时写只进内存（与改造前行为一致） */
  private static store: preferences.Preferences | null = null;

  /**
   * 接上磁盘存储并把已保存的值读回内存。必须在任何 Token 读写之前调用一次
   * （EntryAbility.onCreate 里调，早于 onWindowStageCreate 的启动页分支）。
   * 失败只告警不抛：存储不可用时退化成纯内存（＝改造前），不把 App 卡在启动上。
   */
  static init(context: common.Context): void {
    if (TokenManager.store !== null) {
      return;
    }
    try {
      const options: preferences.Options = { name: PREF_STORE };
      TokenManager.store = preferences.getPreferencesSync(context, options);
      AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, TokenManager.readString(TokenManager.ACCESS_TOKEN_KEY));
      AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, TokenManager.readString(TokenManager.REFRESH_TOKEN_KEY));
      AppStorage.setOrCreate(TokenManager.USERNAME_KEY, TokenManager.readString(TokenManager.USERNAME_KEY));
    } catch (e) {
      TokenManager.store = null;
      hilog.warn(0x0000, TAG, 'token store unavailable, memory only');
    }
  }

  /** 读盘；无盘或类型不符时给 '' */
  private static readString(key: string): string {
    const store = TokenManager.store;
    if (store === null) {
      return '';
    }
    const value = store.getSync(key, '');
    return typeof value === 'string' ? value : '';
  }

  /**
   * 落盘。putSync/deleteSync 只改内存缓存，要 flush 才进文件；
   * 而 flushSync 是 API 14、本项目 compatibleSdkVersion 12（编译期会告警），故只能用异步 flush。
   * 失败只告警：内存值已生效，本次会话不受影响。
   */
  private static flushSoon(store: preferences.Preferences): void {
    store.flush().catch(() => {
      hilog.warn(0x0000, TAG, 'flush token failed');
    });
  }

  /** 内存与盘一起写。key 值非法时只告警：内存值已生效，本次会话不受影响 */
  private static persist(key: string, value: string): void {
    const store = TokenManager.store;
    if (store === null) {
      return;
    }
    try {
      store.putSync(key, value);
    } catch (e) {
      hilog.warn(0x0000, TAG, 'persist token failed');
      return;
    }
    TokenManager.flushSoon(store);
  }

  /** 内存与盘一起清（登出 / 注销 / token 失效三条路都走这里） */
  private static erase(key: string): void {
    AppStorage.setOrCreate(key, '');
    const store = TokenManager.store;
    if (store === null) {
      return;
    }
    try {
      store.deleteSync(key);
    } catch (e) {
      hilog.warn(0x0000, TAG, 'erase token failed');
      return;
    }
    TokenManager.flushSoon(store);
  }

  static async saveTokens(accessToken: string, refreshToken: string): Promise<void> {
    AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, accessToken);
    AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, refreshToken);
    TokenManager.persist(TokenManager.ACCESS_TOKEN_KEY, accessToken);
    TokenManager.persist(TokenManager.REFRESH_TOKEN_KEY, refreshToken);
  }

  static saveUsername(username: string): void {
    AppStorage.setOrCreate(TokenManager.USERNAME_KEY, username);
    TokenManager.persist(TokenManager.USERNAME_KEY, username);
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
    TokenManager.erase(TokenManager.ACCESS_TOKEN_KEY);
    TokenManager.erase(TokenManager.REFRESH_TOKEN_KEY);
    TokenManager.erase(TokenManager.USERNAME_KEY);
  }

  static isLoggedIn(): boolean {
    return (TokenManager.getAccessToken() ?? '').length > 0;
  }
}
