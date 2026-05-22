/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { apiService } from './ApiService';
import {
  LoginRequest,
  LoginResponse,
  DashboardData,
  User,
  UserListResult,
  PageParams
} from '../model/DataModels';

/**
 * 认证服务
 */
export class AuthService {
  static async login(username: string, password: string): Promise<LoginResponse> {
    const body: LoginRequest = { username, password };
    const response = await apiService.post<LoginResponse>('/api/auth/login', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async refreshToken(refreshToken: string): Promise<LoginResponse> {
    const response = await apiService.post<LoginResponse>('/api/auth/refresh', {
      refresh_token: refreshToken
    });
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 仪表盘服务
 */
export class DashboardService {
  static async getDashboard(): Promise<DashboardData> {
    const response = await apiService.get<DashboardData>('/admin/dashboard');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 用户管理服务
 */
export class UserService {
  static async getList(params: PageParams & { keyword?: string; status?: number }): Promise<UserListResult> {
    const query = Object.entries(params)
      .filter(([_, v]) => v !== undefined && v !== '')
      .map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`)
      .join('&');
    const response = await apiService.get<UserListResult>(`/admin/user?${query}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async getDetail(id: string): Promise<User> {
    const response = await apiService.get<User>(`/admin/user/${id}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async create(data: Partial<User> & { password: string }): Promise<void> {
    const response = await apiService.post<User>('/admin/user', data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  static async update(id: string, data: Partial<User>): Promise<void> {
    const response = await apiService.put<User>(`/admin/user/${id}`, data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  static async delete(id: string): Promise<void> {
    const response = await apiService.delete<void>(`/admin/user/${id}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }
}
