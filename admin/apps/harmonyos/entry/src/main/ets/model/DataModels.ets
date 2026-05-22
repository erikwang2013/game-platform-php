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
 * 分页参数
 */
export interface PageParams {
  page: number;
  limit: number;
}

/**
 * 分页响应
 */
export interface PageResult<T> {
  list: T[];
  total: number;
  page: number;
  limit: number;
}

/**
 * 仪表盘统计数据
 */
export interface StatCard {
  label: string;
  value: string;
  icon: string;
  color: string;
  trend?: number;
}

/**
 * 趋势数据
 */
export interface TrendData {
  dates: string[];
  series: TrendSeries[];
}

export interface TrendSeries {
  name: string;
  data: number[];
  color: string;
}

/**
 * 仪表盘完整数据
 */
export interface DashboardData {
  stats: StatCard[];
  trends: TrendData;
  distribution: Record<string, DistributionItem[]>;
  recent_logs: OperationLogItem[];
}

export interface DistributionItem {
  name: string;
  value: number;
}

/**
 * 用户模型
 */
export interface User {
  id: string; // hashid 字符串
  username: string;
  real_name: string;
  avatar: string;
  phone: string;
  email: string;
  status: number;
  last_login_at: string;
  last_login_ip: string;
  created_at: string;
}

export interface UserListResult extends PageResult<User> {}

/**
 * 角色模型
 */
export interface Role {
  id: string;
  name: string;
  slug: string;
  description: string;
  status: number;
}

/**
 * 操作日志
 */
export interface OperationLogItem {
  id: string;
  user_name: string;
  action: string;
  method: string;
  path: string;
  ip: string;
  created_at: string;
}

/**
 * 登录请求/响应
 */
export interface LoginRequest {
  username: string;
  password: string;
}

export interface LoginResponse {
  access_token: string;
  refresh_token: string;
  expires_in: number;
}
