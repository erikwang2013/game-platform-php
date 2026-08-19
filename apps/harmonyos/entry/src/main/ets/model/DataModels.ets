/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 统一响应格式
 */
export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

/**
 * 登录请求/响应
 */
export interface LoginRequest {
  username: string;
  password: string;
}

export interface LoginUser {
  id: string;
  username: string;
  nickname: string;
  avatar: string;
}

export interface LoginResponse {
  access_token: string;
  refresh_token: string;
  user?: LoginUser;
}

/**
 * 个人资料
 */
export interface UserProfile {
  id: string;
  username: string;
  nickname: string;
  avatar: string;
  email: string;
  phone: string;
  country: string;
  language: string;
  last_login_at: string;
  created_at: string;
}

/**
 * 游戏币种
 */
export interface GameCurrency {
  id: string;
  name: string;
  symbol: string;
  exchange_rate: string;
  min_exchange?: string;
  max_exchange?: string;
}

/**
 * 游戏分类
 */
export interface GameCategory {
  name: string;
  slug: string;
}

/**
 * 游戏列表项 / 详情
 */
export interface GameItem {
  id: string;
  name: string;
  slug: string;
  type: string;
  description: string;
  cover_image: string;
  currencies?: GameCurrency[];
  categories?: GameCategory[];
  api_endpoint?: string;
}

export interface GameListResult {
  items?: GameItem[];
  list?: GameItem[];
  total: number;
  page: number;
  per_page: number;
  last_page?: number;
}

export interface GameLaunchResult {
  id: string;
  name: string;
  slug: string;
  type: string;
  api_endpoint: string;
  session_id: string;
}

/**
 * 钱包
 */
export interface WalletInfo {
  id: string;
  balance: string;
  frozen_balance: string;
  total_earned: string;
  total_spent: string;
}

export interface TransactionItem {
  id: string;
  type: string;
  amount: string;
  balance_after: string;
  ref_type?: string;
  ref_id?: string;
  remark: string;
  created_at: string;
}

export interface TransactionListResult {
  items?: TransactionItem[];
  list?: TransactionItem[];
  total: number;
  page: number;
  per_page: number;
  last_page?: number;
}
