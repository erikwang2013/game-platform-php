/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { apiService } from './ApiService';
import {
  LoginResponse,
  UserProfile,
  GameItem,
  GameListResult,
  GameLaunchResult,
  WalletInfo,
  TransactionItem,
  TransactionListResult
} from '../model/DataModels';

function unwrapList<T>(data: GameListResult | TransactionListResult | T[] | undefined | null): T[] {
  if (!data) {
    return [];
  }
  if (Array.isArray(data)) {
    return data as T[];
  }
  const boxed = data as GameListResult & TransactionListResult;
  if (boxed.items) {
    return boxed.items as T[];
  }
  if (boxed.list) {
    return boxed.list as T[];
  }
  return [];
}

/**
 * 认证服务 — C 端 /api/auth/*
 */
export class AuthService {
  static async login(username: string, password: string): Promise<LoginResponse> {
    const response = await apiService.post<LoginResponse>('/api/auth/login', { username, password });
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async register(username: string, password: string): Promise<LoginResponse> {
    const response = await apiService.post<LoginResponse>('/api/auth/register', { username, password });
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 游戏服务 — C 端 /api/game/*
 */
export class GameService {
  static async list(keyword?: string): Promise<GameItem[]> {
    let path = '/api/game/list?page=1&per_page=50';
    if (keyword && keyword.length > 0) {
      path += `&keyword=${encodeURIComponent(keyword)}`;
    }
    const response = await apiService.get<GameListResult>(path);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return unwrapList<GameItem>(response.data);
  }

  static async detail(id: string): Promise<GameItem> {
    const response = await apiService.get<GameItem>(`/api/game/detail/${id}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async launch(gameId: string): Promise<GameLaunchResult> {
    const response = await apiService.post<GameLaunchResult>('/api/game/launch', { game_id: gameId });
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 钱包服务 — C 端 /api/wallet/*
 */
export class WalletService {
  static async info(): Promise<WalletInfo> {
    const response = await apiService.get<WalletInfo>('/api/wallet/info');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async transactions(page: number = 1): Promise<TransactionItem[]> {
    const response = await apiService.get<TransactionListResult>(`/api/wallet/transactions?page=${page}&per_page=20`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return unwrapList<TransactionItem>(response.data);
  }
}

/**
 * 用户服务 — C 端 /api/user/profile
 */
export class UserService {
  static async profile(): Promise<UserProfile> {
    const response = await apiService.get<UserProfile>('/api/user/profile');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async updateProfile(data: object): Promise<UserProfile> {
    const response = await apiService.put<UserProfile>('/api/user/profile', data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}
