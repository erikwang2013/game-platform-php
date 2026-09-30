/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { apiService } from './ApiService';
import {
  ApiResponse,
  LoginRequest,
  LoginResponse,
  CaptchaChallenge,
  CaptchaGenerateResult,
  CaptchaTarget,
  CaptchaClick,
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
 * 验证码服务 — 公开端点 /api/v1/captcha/generate
 *
 * 登录/注册/提现申请/兑换卖出 四个动作都由服务端强制点击验证码，
 * 取图与归一化收敛在这里，四处（登录页/提现页/兑换页）共用同一个弹框组件。
 */
export class CaptchaService {
  /**
   * 取一张新图。验证码一次性：每次开框都要现取，复用旧 key 必验不过。
   * 服务端 data = { key, image(裸 base64 PNG), extra: { texts: [{order, text}] } }，
   * 这里归一化成 CaptchaChallenge（texts 已按 order 升序），弹框拿到即可直接渲染。
   */
  static async generate(): Promise<CaptchaChallenge> {
    const response = await apiService.post<CaptchaGenerateResult>(
      '/api/v1/captcha/generate',
      { difficulty: 'medium' }
    );
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const texts = response.data?.extra?.texts ?? [];
    // 库不可用时服务端回空 texts（stub），此时无题可点 ⇒ 按加载失败处理，别让用户点个空框
    if (texts.length === 0) {
      throw new Error('验证码加载失败');
    }
    texts.sort((a: CaptchaTarget, b: CaptchaTarget) => a.order - b.order);
    return { key: response.data.key, image: response.data.image, texts };
  }
}

/**
 * 认证服务 — C 端 /api/v1/auth/*
 */
export class AuthService {
  static async login(
    username: string,
    password: string,
    captchaKey: string,
    clicks: CaptchaClick[]
  ): Promise<LoginResponse> {
    const req: LoginRequest = { username, password, captcha_key: captchaKey, clicks };
    const response = await apiService.post<LoginResponse>('/api/v1/auth/login', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    // 2FA：服务端只回 require_2fa + pending_2fa_token，没有令牌对。不拦的话
    // saveTokens(undefined, undefined) 会"登录成功"跳进主页面，之后每个请求都 401。
    // 语义与 Angular/React 端一致：不在本客户端完成 2FA，明确告知用户。
    if (response.data.require_2fa) {
      throw new Error('该账号已开启二次验证（2FA），本客户端暂不支持，请改用支持 2FA 的客户端登录。');
    }
    return response.data;
  }

  static async register(
    username: string,
    password: string,
    captchaKey: string,
    clicks: CaptchaClick[]
  ): Promise<LoginResponse> {
    const req: LoginRequest = { username, password, captcha_key: captchaKey, clicks };
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
 * 用户服务 — C 端 /api/v1/user/profile、/api/v1/user/delete-account
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

  /**
   * 注销账号。请求体契约以服务端为准（service/app/api/v1/controller/UserController.php:163）：
   * 除 password 外还要求 confirm 恰为字面量 'yes'。
   * 用户输入原样透传，由服务端裁决；失败时服务端原因原样抛出，不吞成通用错误。
   */
  static async deleteAccount(password: string, confirm: string): Promise<void> {
    const response = await apiService.post<object>('/api/v1/user/delete-account', {
      password: password,
      confirm: confirm
    });
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /**
   * 注销后回读：账号真注销了 ⇒ 资料接口必须已取不到。
   * true = 确认已注销（401 会话已被服务端吊销 / 404 查无此人）；false = 仍读得到资料 ⇒ 注销没生效。
   * 其余失败（网络/5xx）无法判定，原样抛给调用方按「无法确认」处理——绝不把不确定当成功。
   */
  static async accountGone(): Promise<boolean> {
    let response: ApiResponse<UserProfile>;
    try {
      response = await apiService.get<UserProfile>('/api/v1/user/profile');
    } catch (e) {
      const msg: string = (e as Error).message;
      if (msg === 'UNAUTHORIZED') {
        return true;
      }
      throw e;
    }
    if (response.code === 0) {
      return false;
    }
    if (response.code === 404) {
      return true;
    }
    throw new Error(response.message);
  }
}
