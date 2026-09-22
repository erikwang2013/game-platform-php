/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { apiService } from './ApiService';
import {
  LoginRequest,
  LoginResponse,
  UserProfile,
  UserProfileUpdateRequest,
  GameItem,
  GameListResult,
  GameLaunchRequest,
  GameLaunchResult,
  WalletInfo,
  TransactionItem,
  TransactionListResult,
  PagedListResult,
  PaymentMethodItem,
  PaymentMethodListResult,
  DepositCreateRequest,
  DepositCreateResult,
  WithdrawApplyRequest,
  WithdrawApplyResult,
  ExchangeRequest,
  ExchangeQuoteResult,
  ExchangeDoneResult
} from '../model/DataModels';

function unwrapList<T>(data: PagedListResult<T> | T[] | undefined | null): T[] {
  if (!data) {
    return [];
  }
  if (Array.isArray(data)) {
    return data as T[];
  }
  if (data.items) {
    return data.items;
  }
  if (data.list) {
    return data.list;
  }
  return [];
}

/**
 * 认证服务 — C 端 /api/v1/auth/*
 */
export class AuthService {
  static async login(username: string, password: string): Promise<LoginResponse> {
    const req: LoginRequest = { username, password };
    const response = await apiService.post<LoginResponse>('/api/v1/auth/login', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async register(username: string, password: string): Promise<LoginResponse> {
    const req: LoginRequest = { username, password };
    const response = await apiService.post<LoginResponse>('/api/v1/auth/register', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 游戏服务 — C 端 /api/v1/game/*
 */
export class GameService {
  static async list(keyword?: string): Promise<GameItem[]> {
    let path = '/api/v1/game/list?page=1&per_page=50';
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
    const response = await apiService.get<GameItem>(`/api/v1/game/detail/${id}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async launch(gameId: string): Promise<GameLaunchResult> {
    const req: GameLaunchRequest = { game_id: gameId };
    const response = await apiService.post<GameLaunchResult>('/api/v1/game/launch', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 钱包服务 — C 端 /api/v1/wallet/*
 */
export class WalletService {
  static async info(): Promise<WalletInfo> {
    const response = await apiService.get<WalletInfo>('/api/v1/wallet/info');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async transactions(page: number = 1): Promise<TransactionItem[]> {
    const response = await apiService.get<TransactionListResult>(`/api/v1/wallet/transactions?page=${page}&per_page=20`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return unwrapList<TransactionItem>(response.data);
  }
}

/**
 * 支付服务 — C 端 /api/v1/payment/*、/api/v1/deposit/*、/api/v1/withdraw/*、/api/v1/exchange/*
 * 金额均为字符串：请求原样透传用户输入，响应交给页面展示，服务端是唯一金额权威
 */
export class PaymentService {
  static async methods(): Promise<PaymentMethodItem[]> {
    const response = await apiService.get<PaymentMethodListResult>('/api/v1/payment/methods');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data?.list ?? [];
  }

  static async createDeposit(req: DepositCreateRequest): Promise<DepositCreateResult> {
    const response = await apiService.post<DepositCreateResult>('/api/v1/deposit/create', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async applyWithdraw(req: WithdrawApplyRequest): Promise<WithdrawApplyResult> {
    const response = await apiService.post<WithdrawApplyResult>('/api/v1/withdraw/apply', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /** 询价：仅展示，不产生订单；买入/卖出会由服务端重新定价 */
  static async quoteExchange(req: ExchangeRequest): Promise<ExchangeQuoteResult> {
    const response = await apiService.post<ExchangeQuoteResult>('/api/v1/exchange/quote', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async buyExchange(req: ExchangeRequest): Promise<ExchangeDoneResult> {
    const response = await apiService.post<ExchangeDoneResult>('/api/v1/exchange/buy', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async sellExchange(req: ExchangeRequest): Promise<ExchangeDoneResult> {
    const response = await apiService.post<ExchangeDoneResult>('/api/v1/exchange/sell', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 用户服务 — C 端 /api/v1/user/profile
 */
export class UserService {
  static async profile(): Promise<UserProfile> {
    const response = await apiService.get<UserProfile>('/api/v1/user/profile');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async updateProfile(data: UserProfileUpdateRequest): Promise<UserProfile> {
    const response = await apiService.put<UserProfile>('/api/v1/user/profile', data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}
