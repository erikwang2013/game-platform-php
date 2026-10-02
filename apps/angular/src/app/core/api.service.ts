/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * C端 API 客户端 — 信封解包 (code===0) / Bearer 头 / 401 刷新一次 / 错误透出
 * 所有 ID 均为 Hashid 字符串。
 */
import { Injectable } from '@angular/core';
import { Observable, catchError, map, of, throwError } from 'rxjs';

/* 契约类型集中在 api.types.ts，此处 re-export 保持既有 import 路径可用 */
import type {
  Activity,
  ActivityProgress,
  AnnouncementBrief,
  AnnouncementDetail,
  AuthResult,
  CaptchaChallenge,
  CaptchaProof,
  CheckinResult,
  DepositCreated,
  DepositOrder,
  ExchangeDone,
  ExchangePayload,
  ExchangeQuote,
  ExchangeRecordRow,
  Game,
  GameDetail,
  GameWallet,
  IdentityStatus,
  IdType,
  LaunchResult,
  Leaderboard,
  Notify,
  Paged,
  PaymentMethodInfo,
  PlatformStats,
  PlayLog,
  PlayLogDetail,
  RankRow,
  Suggestion,
  TicketBrief,
  TicketDetail,
  TicketType,
  Transaction,
  UserProfile,
  WalletInfo,
  WithdrawApplied,
  WithdrawOrder,
} from './api.types';
import { ApiError, tokens } from './session';
/* 传输层（信封解包/401 刷新/错误透出）已拆到 api.base.ts；Api 靠继承拿到 request()。
   刻意**不** re-export api.base：BASE 与 ApiBase 都是内部细节，没有对外消费方。 */
import { ApiDomains } from './api.domains';
import { BASE } from './api.base';

export * from './api.types';

/* 扩展域方法（券/搜索/赛事/分享）与它们的响应类型在 api.domains.ts；
   这里透出去，页面照旧从 '../core/api.service' 导入，不用记第二个路径。 */
export * from './api.domains';

/* 会话/格式化/错误类型/拦截器已拆到 session.ts；此处 re-export 保持既有 import 路径可用 */
export * from './session';

/* ---------------- 客户端 ---------------- */

type Query = Record<string, string | number | boolean | undefined | null>;

/** /captcha/generate 的原始响应：texts 在 extra 里，且刻意不含坐标 */
interface CaptchaRaw {
  key?: string;
  image?: string;
  extra?: { texts?: { order?: number; text?: string }[] };
}

@Injectable({ providedIn: 'root' })
export class Api extends ApiDomains {
  /* ---- 公开接口 ---- */

  platformStats(): Observable<PlatformStats> {
    return this.request<PlatformStats>('GET', `${BASE}/platform/stats`);
  }

  gameList(query: Query = { page: 1, per_page: 24 }): Observable<Paged<Game>> {
    return this.request<Paged<Game>>('GET', `${BASE}/game/list`, query);
  }

  gameSuggest(q: string): Observable<{ suggestions: Suggestion[] }> {
    return this.request<{ suggestions: Suggestion[] }>('GET', `${BASE}/game/suggest`, { q });
  }

  gameDetail(hashid: string): Observable<GameDetail> {
    return this.request<GameDetail>('GET', `${BASE}/game/detail/${hashid}`);
  }

  /** 支付方式列表（公开接口）；createDeposit 的 payment_method_id 取自此处 */
  paymentMethods(): Observable<{ list: PaymentMethodInfo[] }> {
    return this.request<{ list: PaymentMethodInfo[] }>('GET', `${BASE}/payment/methods`);
  }

  /**
   * 取点击式验证码，响应规范化成 {key, image, texts}：
   * 服务端下发 data.{key,image,extra.texts}，image 是裸 base64（无 data: 前缀），
   * texts 按 order 升序 = 要求的点击顺序（画布恒 300×200）。
   */
  captcha(): Observable<CaptchaChallenge> {
    return this.request<CaptchaRaw>('POST', `${BASE}/captcha/generate`, undefined, {
      difficulty: 'easy',
    }).pipe(
      map((raw) => ({
        key: String(raw?.key ?? ''),
        image: String(raw?.image ?? ''),
        texts: (raw?.extra?.texts ?? [])
          .slice()
          .sort((a, b) => Number(a.order ?? 0) - Number(b.order ?? 0))
          .map((t) => String(t.text ?? '')),
      })),
    );
  }

  /* ---- 认证（登录/注册服务端强制验证码） ---- */

  login(username: string, password: string, proof: CaptchaProof): Observable<AuthResult> {
    return this.request<AuthResult>('POST', `${BASE}/auth/login`, undefined, {
      username,
      password,
      ...proof,
    });
  }

  register(
    payload: {
      username: string;
      password: string;
      email?: string;
      nickname?: string;
      /** 邀请短码（裂变转化）。服务端 `nullable|string|max:12`，`AuthController::register` 收下后交给 `ShareLink::bindConversion` */
      share_code?: string;
    } & CaptchaProof,
  ): Observable<AuthResult> {
    return this.request<AuthResult>('POST', `${BASE}/auth/register`, undefined, payload);
  }

  logout(): void {
    tokens.clear();
  }

  /* ---- 需登录 ---- */

  walletInfo(): Observable<WalletInfo> {
    return this.request<WalletInfo>('GET', `${BASE}/wallet/info`);
  }

  walletTransactions(page = 1, perPage = 20): Observable<Paged<Transaction>> {
    return this.request<Paged<Transaction>>('GET', `${BASE}/wallet/transactions`, {
      page,
      per_page: perPage,
    });
  }

  depositOrders(page = 1, perPage = 20): Observable<Paged<DepositOrder>> {
    return this.request<Paged<DepositOrder>>('GET', `${BASE}/deposit/orders`, {
      page,
      per_page: perPage,
    });
  }

  withdrawOrders(page = 1, perPage = 20): Observable<Paged<WithdrawOrder>> {
    return this.request<Paged<WithdrawOrder>>('GET', `${BASE}/withdraw/orders`, {
      page,
      per_page: perPage,
    });
  }

  /** 兑换记录（买入/卖出成交流水），与其它列表同形状 */
  exchangeRecords(page = 1, perPage = 20): Observable<Paged<ExchangeRecordRow>> {
    return this.request<Paged<ExchangeRecordRow>>('GET', `${BASE}/exchange/records`, {
      page,
      per_page: perPage,
    });
  }

  /* ---- 资金写操作（金额一律传字符串原文，不得经过 float） ---- */

  /** 创建充值订单；502 表示支付网关不可用，可提示重试 */
  createDeposit(payload: {
    amount: string;
    currency: string;
    payment_method_id: string;
  }): Observable<DepositCreated> {
    return this.request<DepositCreated>('POST', `${BASE}/deposit/create`, undefined, payload);
  }

  /** 申请提现（服务端强制验证码；可能因全局开关/风控/限额/审核锁返回 403/400/429/503） */
  applyWithdraw(
    payload: {
      platform_amount: string;
      method: string;
      account_info: string;
    } & CaptchaProof,
  ): Observable<WithdrawApplied> {
    return this.request<WithdrawApplied>('POST', `${BASE}/withdraw/apply`, undefined, payload);
  }

  /** 兑换询价；不产生订单，仅用于展示汇率/点差/预计到账 */
  quoteExchange(payload: ExchangePayload): Observable<ExchangeQuote> {
    return this.request<ExchangeQuote>('POST', `${BASE}/exchange/quote`, undefined, payload);
  }

  /** 买入：平台币 → 游戏币（服务端按路径固定 direction='in'，请求体里的 direction 被忽略） */
  exchangeBuy(payload: ExchangePayload): Observable<ExchangeDone> {
    return this.request<ExchangeDone>('POST', `${BASE}/exchange/buy`, undefined, payload);
  }

  /** 卖出：游戏币 → 平台币（服务端按路径固定 direction='out'，并强制验证码；买入不需要） */
  exchangeSell(payload: ExchangePayload & CaptchaProof): Observable<ExchangeDone> {
    return this.request<ExchangeDone>('POST', `${BASE}/exchange/sell`, undefined, payload);
  }

  profile(): Observable<UserProfile> {
    return this.request<UserProfile>('GET', `${BASE}/user/profile`);
  }

  /**
   * 改资料。服务端白名单是 nickname / avatar / language 三项（UserController::updateProfile:64-90），
   * 只送改动的字段即可；本树只改昵称（头像走上传，语言没有切换器）。校验：`nullable|max:50`。
   *
   * ⚠ 回包**不是** `UserProfile`：只有 id/username/nickname/avatar/language 五个字段
   * （缺 email/phone/country/last_login_at/created_at，同文件 :97-103）⇒ 类型照实写这个子集，
   * 别声明成 UserProfile 骗调用方。保存后要完整资料就再调 `profile()`。
   */
  updateProfile(payload: {
    nickname: string;
  }): Observable<{ id: string; username: string; nickname: string; avatar: string }> {
    return this.request<{ id: string; username: string; nickname: string; avatar: string }>(
      'PUT',
      `${BASE}/user/profile`,
      undefined,
      payload,
    );
  }

  /**
   * 注销账号。请求体契约以服务端为准（service/app/api/v1/controller/UserController.php:163）：
   * 除 password 外还要求 confirm 恰为字面量 'yes'。
   * 成功即账号已注销、服务端会话已吊销，此后必须重新登录。
   */
  deleteAccount(password: string, confirm = 'yes'): Observable<unknown> {
    return this.request<unknown>('POST', `${BASE}/user/delete-account`, undefined, {
      password,
      confirm,
    });
  }

  /**
   * 注销后回读：账号真注销了 ⇒ 资料接口必须已取不到。
   * true = 确认已注销（401/404）；false = 仍读得到资料 ⇒ 注销没生效。
   * 网络/5xx 这类无法判定的失败**不吞**，原样抛出交调用方提示。
   * retry=false：此时 refresh token 也已被服务端吊销，不必再试刷新。
   */
  accountGone(): Observable<boolean> {
    return this.request<UserProfile>(
      'GET',
      `${BASE}/user/profile`,
      undefined,
      undefined,
      false,
    ).pipe(
      map(() => false),
      catchError((e: ApiError) => {
        if (e.code === 401 || e.code === 404) return of(true);
        return throwError(() => e);
      }),
    );
  }

  notifications(page = 1, perPage = 20): Observable<Paged<Notify>> {
    return this.request<Paged<Notify>>('GET', `${BASE}/notification/list`, {
      page,
      per_page: perPage,
    });
  }

  unreadCount(): Observable<{ count: number }> {
    return this.request<{ count: number }>('GET', `${BASE}/notification/unread-count`);
  }

  markRead(id?: string): Observable<unknown> {
    return this.request<unknown>('POST', `${BASE}/notification/read`, undefined, { id });
  }

  launchGame(gameId: string): Observable<LaunchResult> {
    return this.request<LaunchResult>('POST', `${BASE}/game/launch`, undefined, {
      game_id: gameId,
    });
  }

  /* ==================== 2FA 登录验证（公开接口） ==================== */

  /**
   * 用登录时下发的 pending_2fa_token 换正式令牌。响应形状与 login 完全相同
   * （服务端 TwoFactorController::verify → issueLogin），所以 AuthResult 可直接复用。
   * 用户身份取自票据本身，客户端无法自选用户；失败 401（票据失效）/422（码错）/403（锁定）。
   */
  twoFactorVerify(pendingToken: string, code: string): Observable<AuthResult> {
    return this.request<AuthResult>('POST', `${BASE}/2fa/verify`, undefined, {
      pending_2fa_token: pendingToken,
      code,
    });
  }

  /* ==================== 2FA 自助（开启 / 关闭） ==================== */

  /** 是否已开启。后端只回 {enabled}，不回启用时间/剩余备份码数 */
  twoFactorStatus(): Observable<{ enabled: boolean }> {
    return this.request<{ enabled: boolean }>('GET', `${BASE}/user/2fa/status`);
  }

  /**
   * 起一个未启用的 setup（服务端先删掉该用户上一条未启用的，可反复调）。
   * 回 `secret`（Base32）与 `qr_url`（otpauth:// 协议串）—— **都不是图片**。
   */
  twoFactorSetup(): Observable<{ secret: string; qr_url: string }> {
    return this.request<{ secret: string; qr_url: string }>('POST', `${BASE}/user/2fa/setup`);
  }

  /**
   * 用 6 位 TOTP 启用。回 **8 个 10 位备份码，只此一次** —— 服务端 enable() 生成后落库，
   * 之后没有任何端点能再读出来（status 只回 enabled），页面必须当场给用户抄走。
   * 这里刻意是 size:6：备份码此刻还没发给用户，不可能拿备份码来启用。
   */
  twoFactorEnable(code: string): Observable<{ backup_codes: string[] }> {
    return this.request<{ backup_codes: string[] }>('POST', `${BASE}/user/2fa/enable`, undefined, {
      code,
    });
  }

  /**
   * 关闭 2FA：**密码 + 6 位 TOTP**。同样刻意是 size:6（与登录 verify 的 between:6,10 不同）：
   * 服务端 disable() 只走 verifyTOTP()，不查备份码表 ⇒ 传 10 位备份码必然 422，
   * 客户端别按「登录能填 10 位」的对称去放宽这里。两个输入框都是 6。
   */
  twoFactorDisable(password: string, code: string): Observable<unknown> {
    return this.request<unknown>('POST', `${BASE}/user/2fa/disable`, undefined, {
      password,
      code,
    });
  }

  /* ==================== 公告 / 排行榜（公开接口） ==================== */

  /** 公告列表 —— 后端不分页，硬 limit 20；列表项不含正文 */
  announcements(): Observable<{ list: AnnouncementBrief[] }> {
    return this.request<{ list: AnnouncementBrief[] }>('GET', `${BASE}/announcement/list`);
  }

  announcementDetail(hashid: string): Observable<AnnouncementDetail> {
    return this.request<AnnouncementDetail>(
      'GET',
      `${BASE}/announcement/detail/${encodeURIComponent(hashid)}`,
    );
  }

  /** 排行榜列表 —— 后端不分页 */
  leaderboards(): Observable<{ list: Leaderboard[] }> {
    return this.request<{ list: Leaderboard[] }>('GET', `${BASE}/leaderboard/list`);
  }

  /** 榜单详情：ranking 里的 user_id 是**裸整数**，服务端未编码 */
  leaderboardRanking(hashid: string): Observable<{
    leaderboard: Leaderboard;
    ranking: RankRow[];
  }> {
    return this.request<{ leaderboard: Leaderboard; ranking: RankRow[] }>(
      'GET',
      `${BASE}/leaderboard/${encodeURIComponent(hashid)}`,
    );
  }

  /* ==================== 工单 ==================== */

  tickets(page = 1, perPage = 20): Observable<Paged<TicketBrief>> {
    return this.request<Paged<TicketBrief>>('GET', `${BASE}/ticket/list`, {
      page,
      per_page: perPage,
    });
  }

  ticketDetail(hashid: string): Observable<TicketDetail> {
    return this.request<TicketDetail>(
      'GET',
      `${BASE}/ticket/${encodeURIComponent(hashid)}`,
    );
  }

  createTicket(payload: {
    type: TicketType;
    subject: string;
    content: string;
  }): Observable<{ id: string }> {
    return this.request<{ id: string }>('POST', `${BASE}/ticket/create`, undefined, payload);
  }

  /** 回复工单；服务端会把工单状态从 closed 之外打成 waiting */
  replyTicket(hashid: string, content: string): Observable<{ id: string }> {
    return this.request<{ id: string }>(
      'POST',
      `${BASE}/ticket/${encodeURIComponent(hashid)}/reply`,
      undefined,
      { content },
    );
  }

  /* ==================== 游戏流水 ==================== */

  /**
   * 游戏币余额（按游戏聚合）。与 `/wallet/info` 是**两本账**：那边是平台币，这边是各游戏内的币。
   * 只回有余额记录的游戏；没有资产时是空数组而不是 404。
   */
  gameBalances(): Observable<{ games: GameWallet[] }> {
    return this.request<{ games: GameWallet[] }>('GET', `${BASE}/game/balance`);
  }

  playLogs(page = 1, perPage = 20, gameId?: string): Observable<Paged<PlayLog>> {
    return this.request<Paged<PlayLog>>('GET', `${BASE}/game/play-logs`, {
      page,
      per_page: perPage,
      game_id: gameId,
    });
  }

  playLogDetail(hashid: string): Observable<PlayLogDetail> {
    return this.request<PlayLogDetail>(
      'GET',
      `${BASE}/game/play-log/${encodeURIComponent(hashid)}`,
    );
  }

  /* 推荐码两个端点（/referral/my-code|apply）2026-10-01 撤下：整条链**无 bootstrap 写入路径**，
     循环依赖 —— apply() 要求先查到一行才创建，而这行只能由 apply() 创建；my-code 读
     referrer_id = 我 同样恒空。零种子 / 零迁移写入 / admin 侧零引用。详见 api.domains.ts
     的同名墓碑注释（含连带不可达的 invite_1 / invite_10 两个成就）。 */

  /* ==================== 身份认证（KYC） ==================== */

  identityStatus(): Observable<IdentityStatus> {
    return this.request<IdentityStatus>('GET', `${BASE}/user/identity/status`);
  }

  /**
   * 提交实名认证。照片字段收的是**上传后落库的相对地址**（见 upload.ts）。
   * 重复提交：pending/approved 一律 422；rejected 可直接重交覆盖原记录。
   */
  applyIdentity(payload: {
    real_name: string;
    id_type: IdType;
    id_number: string;
    id_front_photo: string;
    id_back_photo?: string;
    selfie_photo: string;
    country?: string;
  }): Observable<unknown> {
    return this.request<unknown>('POST', `${BASE}/user/identity/apply`, undefined, payload);
  }

  /* ==================== 运营活动 ==================== */

  /**
   * 活动列表。服务端已做完三道过滤：status=启用 + 时间窗内 + 灰度
   * （`rollout_percent`，按 userId 判定 `activity_{id}`），客户端不要再自己筛。
   */
  activities(): Observable<{ list: Activity[] }> {
    return this.request<{ list: Activity[] }>('GET', `${BASE}/activities/list`);
  }

  /** 我的活动进度 —— 只回**当天**（period_key=YYYY-MM-DD），最多 50 条 */
  activityProgress(): Observable<{ list: ActivityProgress[] }> {
    return this.request<{ list: ActivityProgress[] }>('GET', `${BASE}/activities/progress`);
  }

  /**
   * 签到 / 领奖。服务端在**同一个事务**里写 reward_log + 钱包
   * （ActivityService::checkin → grantRewards → WalletService::mutate），
   * 所以返回就是权威结果，**前端不许自己加钱**，连余额都该重新拉。
   * 幂等靠 `uk_idempotent` 唯一键，重复点返回 `already` 而不是重复发奖。
   * 业务失败（活动不可用/已结束）走 HTTP 200 + code=400。
   */
  activityCheckin(hashid: string): Observable<CheckinResult> {
    return this.request<CheckinResult>(
      'POST',
      `${BASE}/activities/${encodeURIComponent(hashid)}/checkin`,
    );
  }
}
