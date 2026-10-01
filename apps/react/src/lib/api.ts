/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * C 端端点目录。传输层（信封拆封 / 401 刷新 / token 与语言存储）在 `http.ts`。
 * 下面三行 re-export 把那几个符号照旧挂在本模块出口上，既有 import 不动。
 */

import type { CaptchaData, CaptchaProof } from './captcha.ts';
import { ApiError, get, post, put, qs, request } from './http.ts';
import type { PageQuery } from './http.ts';
export { ApiError, language, refreshOnce, setUnauthorizedHandler, tokens } from './http.ts';
export type { PageQuery } from './http.ts';

/* 类型定义见 types.ts，这里 re-export 保持既有 `from "../lib/api.ts"` 的 import 不动 */
export type * from './types.ts';
import type {
  Activity,
  ActivityCheckinResult,
  ActivityProgress,
  Announcement,
  AnnouncementDetail,
  AuthTokens,
  ChatMessage,
  Conversation,
  DepositCreated,
  DepositOrder,
  ExchangeDone,
  ExchangeQuote,
  ExchangeRecordRow,
  ExchangeRequest,
  ExportData,
  FriendRequest,
  FriendUser,
  Game,
  GameWallet,
  IdentityApply,
  IdentityStatus,
  LanguageList,
  Leaderboard,
  Notice,
  Paged,
  PagedLite,
  PaymentMethod,
  PlatformStats,
  PlayLog,
  PlayLogDetail,
  Profile,
  RankingRow,
  SearchResult,
  TicketDetail,
  TicketRow,
  TicketType,
  Tournament,
  TournamentDetail,
  Transaction,
  TwoFactorEnabled,
  TwoFactorSetup,
  TwoFactorStatus,
  WalletInfo,
  WithdrawApplied,
  WithdrawOrder,
} from './types.ts';

/* ---------------- endpoints ---------------- */

export const api = {
  // auth（登录/注册服务端强制点击验证码，proof 与业务字段同级进请求体）
  captcha: () => post<CaptchaData>('/captcha/generate', { difficulty: 'easy' }),
  // 开了 2FA 的账号走另一支：不签发 token，只回要求第二步校验的短期票据
  login: (username: string, password: string, proof: CaptchaProof) =>
    post<AuthTokens | { require_2fa: true; pending_2fa_token: string }>('/auth/login', {
      username,
      password,
      ...proof,
    }),
  /**
   * 注册。`shareCode` 是分享短码（服务端 `nullable|string|max:12`）：
   * 带上它注册链路会走 ShareLink::bindConversion ⇒ conversions 原子自增 +
   * 写邀请活动的 user.registered 进度。无效码**不报错**，静默按无码处理（bindConversion 返回 null）。
   */
  register: (
    username: string,
    password: string,
    email: string,
    proof: CaptchaProof,
    shareCode?: string,
  ) =>
    post<AuthTokens>('/auth/register', {
      username,
      password,
      email,
      ...(shareCode ? { share_code: shareCode } : {}),
      ...proof,
    }),

  // public
  stats: () => get<PlatformStats>('/platform/stats'),
  games: (p: PageQuery & { keyword?: string } = {}) => get<Paged<Game>>(`/game/list${qs(p)}`),
  suggest: (q: string) =>
    get<{ suggestions: Array<{ id: string; name: string; slug: string }> }>(
      `/game/suggest${qs({ q })}`,
    ),
  gameDetail: (hashid: string) => get<Game>(`/game/detail/${hashid}`),
  launch: (gameId: string) => post<{ session_id: string; api_endpoint: string | null }>(
    '/game/launch',
    { game_id: gameId },
  ),

  // account
  profile: () => get<Profile>('/user/profile'),

  /**
   * 注销账号。请求体契约以服务端为准（service/app/api/v1/controller/UserController.php:163）：
   * 除 password 外还要求 confirm 恰为字面量 'yes'。成功即账号已注销、会话已吊销，须重新登录。
   */
  deleteAccount: (password: string, confirm = 'yes') =>
    post<unknown>('/user/delete-account', { password, confirm }),

  /**
   * 注销后回读：账号真注销了 ⇒ 资料接口必须已取不到。
   * true = 确认已注销（401/404）；false = 仍读得到资料 ⇒ 注销没生效。
   * 网络故障这类无法判定的失败**不吞**，原样抛出交调用方提示。
   * retry=false：此时 refresh token 也已被服务端吊销，不必再试刷新。
   */
  accountGone: async (): Promise<boolean> => {
    try {
      await request<Profile>('/user/profile', {}, false);
      return false;
    } catch (e) {
      if (e instanceof ApiError && (e.code === 401 || e.code === 404)) return true;
      throw e;
    }
  },
  wallet: () => get<WalletInfo>('/wallet/info'),
  transactions: (p: PageQuery = {}) => get<Paged<Transaction>>(`/wallet/transactions${qs(p)}`),
  deposits: (p: PageQuery = {}) => get<Paged<DepositOrder>>(`/deposit/orders${qs(p)}`),
  withdraws: (p: PageQuery = {}) => get<Paged<WithdrawOrder>>(`/withdraw/orders${qs(p)}`),

  // 钱包写操作：金额一律字符串透传，UI 层不做数值运算
  paymentMethods: () => get<{ list: PaymentMethod[] }>('/payment/methods'),
  createDeposit: (p: { amount: string; currency: string; payment_method_id: string }) =>
    post<DepositCreated>('/deposit/create', p),
  applyWithdraw: (p: { platform_amount: string; method: string; account_info: string } & CaptchaProof) =>
    post<WithdrawApplied>('/withdraw/apply', p),
  exchangeQuote: (p: ExchangeRequest) => post<ExchangeQuote>('/exchange/quote', p),
  // 买入后端刻意不加验证码；卖出强制
  exchangeBuy: (p: ExchangeRequest) => post<ExchangeDone>('/exchange/buy', p),
  exchangeSell: (p: ExchangeRequest & CaptchaProof) => post<ExchangeDone>('/exchange/sell', p),

  // notifications
  notices: (p: PageQuery = {}) => get<Paged<Notice>>(`/notification/list${qs(p)}`),
  unreadCount: () => get<{ count: number }>('/notification/unread-count'),
  markRead: (id?: string) => post<unknown>('/notification/read', id ? { id } : {}),

  /**
   * 2FA 登录第二步。login() 在账号开了 2FA 时只回 pending_2fa_token（不签发正式 token），
   * 必须拿短期票据 + 验证码来这里换。响应结构与 login 成功一致。
   * code 服务端收 between:6,10：6 位=TOTP、10 位=备用码（用掉即作废）。
   */
  verify2fa: (pendingToken: string, code: string) =>
    post<AuthTokens>('/2fa/verify', { pending_2fa_token: pendingToken, code }),

  // 2FA 自助管理（都要登录态；enable/disable 的 code 仍是 size:6 的 TOTP，
  // 备用码只能用于登录第二步，不能用于启停——这是服务端契约，别放宽）
  twoFactorStatus: () => get<TwoFactorStatus>('/user/2fa/status'),
  twoFactorSetup: () => post<TwoFactorSetup>('/user/2fa/setup', {}),
  twoFactorEnable: (code: string) => post<TwoFactorEnabled>('/user/2fa/enable', { code }),
  twoFactorDisable: (password: string, code: string) =>
    post<unknown>('/user/2fa/disable', { password, code }),

  /** 兑换记录：与钱包其它列表同形状（items/total/page/last_page） */
  exchangeRecords: (p: PageQuery = {}) =>
    get<Paged<ExchangeRecordRow>>(`/exchange/records${qs(p)}`),

  // 公告 / 排行榜（公开，无需登录）
  announcements: () => get<{ list: Announcement[] }>('/announcement/list'),
  announcement: (hashid: string) => get<AnnouncementDetail>(`/announcement/detail/${hashid}`),
  leaderboards: () => get<{ list: Leaderboard[] }>('/leaderboard/list'),
  leaderboard: (hashid: string) =>
    get<{ leaderboard: Leaderboard; ranking: RankingRow[] }>(`/leaderboard/${hashid}`),

  // 游戏资产 / 战绩
  gameBalances: () => get<{ games: GameWallet[] }>('/game/balance'),
  playLogs: (p: PageQuery = {}) => get<Paged<PlayLog>>(`/game/play-logs${qs(p)}`),
  /**
   * 单条战绩详情。服务端按 `id + user_id` 查（`GamePlayLogController::detail:79-81`），
   * 别人的或不存在的一律 `code:404`「游戏记录不存在」⇒ 归属由服务端兜住，前端不做任何预筛。
   */
  playLogDetail: (hashid: string) =>
    get<PlayLogDetail>(`/game/play-log/${encodeURIComponent(hashid)}`),

  /* 优惠券三个端点（/coupon/available|claim|my）2026-10-01 撤下：端点本身能跑通，
     但券可领不可核销 ⇒ 页面只能提供假价值。没有消费者的接线一律删，不留在树里当死码。
     恢复条件见 Layout.tsx 的 MORE 注释。 */

  /**
   * 全局搜索。回包**没有 last_page**（只有 total/page/per_page），故单独一个类型。
   * 只暴露 type=game：服务端把 type=user 标注为 admin 用途（SearchController 注释），
   * C 端不该用它检索其他用户。
   */
  searchGames: (q: string, p: PageQuery = {}) =>
    get<SearchResult<Game>>(`/search${qs({ q, type: 'game', ...p })}`),

  /* 邮箱/手机验证四个端点（/verify/send-email|confirm-email|send-sms|confirm-phone）2026-10-01 撤下：
     后端不建立归属、结果也读不回（证据见 Security.tsx 顶部注释）。 */

  // 语言：切换的是**服务端响应文案与通知的语言**（headers 里的 X-Language），本树界面文案固定中文
  languages: () => get<LanguageList>('/language/list'),
  switchLanguage: (locale: string) => post<{ locale: string }>('/language/switch', { locale }),

  /**
   * 改资料。服务端白名单是 nickname / avatar / language 三项（UserController::updateProfile），
   * 只送改动的字段即可；本树目前只用得上 nickname（头像要上传，本树无上传能力）。
   */
  updateProfile: (p: { nickname: string }) =>
    put<{ id: string; username: string; nickname: string | null; avatar: string | null }>(
      '/user/profile',
      p,
    ),

  /* ---------------- 身份认证（KYC） ---------------- */

  /**
   * 认证状态。**从未提交时只回 `{status:'not_submitted'}`**，其余字段全缺 ⇒ 按可选处理，
   * 别假设 `real_name` 一定有值（`IdentityController::status:27-31`）。
   */
  identityStatus: () => get<IdentityStatus>('/user/identity/status'),
  /**
   * 提交认证。照片字段送的是**上传后的落库值**（`lib/upload.ts` 的 `uploadImage` 返回值），
   * 不是 File。服务端状态机见 `IdentityController::apply:69-74`：已有 pending/approved 回 422
   * 「You already have a pending or approved KYC submission」；rejected 走复用原行的重新提交。
   * `id_back_photo` 是唯一可选的照片（validator 里 nullable）。
   */
  applyIdentity: (p: IdentityApply) => post<unknown>('/user/identity/apply', p),

  /* ---------------- 数据导出（GDPR） ---------------- */

  /**
   * 导出我的数据。服务端把四类明细各 `->limit(100)` 后连同资料/钱包/第三方账号一起塞进
   * **普通信封**回（`UserController::exportData:110-162`）⇒ 这里就是普通的 `get`，
   * 落盘由 `lib/exportData.ts` 自己捏 Blob（那条链路的形状与 admin 的附件下载不同）。
   */
  exportData: () => get<ExportData>('/user/export-data'),

  /* ---------------- 工单 ---------------- */
  // 回包是 PagedLite（有 last_page，**没有 per_page**），别按 Paged<T> 解
  tickets: (p: PageQuery = {}) => get<PagedLite<TicketRow>>(`/ticket/list${qs(p)}`),
  // detail 是「我的工单」：服务端校验 user_id 归属，别人的工单回 404
  ticket: (hashid: string) => get<TicketDetail>(`/ticket/${hashid}`),
  // type 白名单见 TicketType；subject ≤200、content ≤5000，超长由服务端 422
  createTicket: (p: { type: TicketType; subject: string; content: string }) =>
    post<{ id: string }>('/ticket/create', p),
  /** 回复自己的工单。工单 status=closed 时服务端回 422，前端要能显示原因 */
  replyTicket: (hashid: string, content: string) =>
    post<{ id: string }>(`/ticket/${hashid}/reply`, { content }),

  /* ---------------- 好友 ---------------- */
  // 四个列表端点都不分页，直接给全量
  friends: () => get<{ list: FriendUser[] }>('/friend/list'),
  friendRequests: () => get<{ list: FriendRequest[] }>('/friend/requests'),
  /** friend_id 是**对方用户**的 hashid */
  friendRequest: (friendId: string) => post<{ id: string }>('/friend/request', { friend_id: friendId }),
  /** request_id 是**好友关系**的 hashid（来自 friendRequests 的 id），不是用户 id */
  friendAccept: (requestId: string) => post<unknown>('/friend/accept', { request_id: requestId }),
  friendReject: (requestId: string) => post<unknown>('/friend/reject', { request_id: requestId }),
  friendRemove: (friendId: string) => post<unknown>('/friend/remove', { friend_id: friendId }),
  /** 搜用户加好友。服务端 limit(20) 且不分页；空 q 直接回空列表 */
  friendSearch: (q: string) =>
    get<{ list: FriendUser[] }>(`/friend/search${qs({ q })}`),

  /* ---------------- 聊天 ---------------- */
  conversations: () => get<{ list: Conversation[] }>('/chat/conversations'),
  /**
   * 与某人的聊天记录（PagedLite）。⚠ 这是个**有副作用的 GET**：
   * 服务端在返回前会把对方发给我的未读全部置为已读（ChatController.php:99-100），
   * 所以「打开会话」就等于「标记已读」，不需要再调 markChatRead。
   * items 已由服务端 array_reverse 成时间正序（旧→新）。
   */
  chatMessages: (peerHashid: string, p: PageQuery = {}) =>
    get<PagedLite<ChatMessage>>(`/chat/messages/${peerHashid}${qs(p)}`),
  /** 只能给好友发；非好友服务端回 403 */
  sendChat: (toUserId: string, content: string) =>
    post<{ id: string; created_at: string }>('/chat/send', { to_user_id: toUserId, content }),
  /** 手动补标已读（打开会话时服务端已代劳，这里只在需要时用） */
  markChatRead: (fromUserId: string) => post<unknown>('/chat/read', { from_user_id: fromUserId }),
  chatUnreadTotal: () => get<{ count: number }>('/chat/unread-total'),

  /* 组队 / 公会的 6 个包装（/groups 建、/{hashid} 看、/{hashid}/members、join、leave、role）
     2026-10-01 随 Group.tsx 一并撤下：端点本身都能调通，但 C 端没有可达路径。
     三条后端事实与恢复条件见 types.ts 的组队/公会墓碑注释。 */

  /* ---------------- 运营活动 ---------------- */

  /**
   * 活动列表。服务端已按「启用 + 在起止时间内 + 灰度命中」筛过，直接渲染即可。
   * 只回 `{list}`，**不分页**。
   */
  activities: () => get<{ list: Activity[] }>('/activities/list'),

  /**
   * 我的今日进度（`period_key` = 今天，limit 50）。
   * 从没参与过的活动**不会**出现在这里 ⇒ 合并时要补零值，别显示 undefined。
   * 与 list 是两个端点，页面用 Promise.all 合并，**不逐条拉 /activities/{hashid}**
   * （detail 回的 participation 与这里同源，逐个拉就是 N+1）。
   */
  activityProgress: () => get<{ list: ActivityProgress[] }>('/activities/progress'),

  /**
   * 签到 / 领奖。奖励是**真钱**：服务端在同一事务里写 reward_log + WalletService::mutate。
   * 幂等由服务端唯一键保证，重复点回 `status:'already'` 而不是重复发奖。
   * 失败（活动不可参与 / 已结束）服务端回 400 + 原因，本方法原样抛。
   * ⚠ 服务端 `checkin()` **只认 type 无关的 canJoin**，故能用它的只有 signin 类；
   * 见 Activities.tsx 的 canCheckin()。
   */
  activityCheckin: (hashid: string) =>
    post<ActivityCheckinResult>(`/activities/${hashid}/checkin`, {}),

  /* ---------------- 赛事 ---------------- */

  /**
   * 赛事列表（`TournamentController::list`）。
   *
   * ⚠ **整个赛事功能由 FeatureFlag `tournament` 把着**：开关关掉时本端点回
   * `code:503 / 'Tournaments not available'`（不是空列表）——页面要把这句原文透出来，
   * 别渲染成「暂无赛事」（那是在替服务端撒谎）。
   * 服务端 `status` 默认 `active`；`upcoming` = `start_at > now AND status=1`，
   * `ended` = `end_at < now`（**不过滤 status**），`active` = 在时间窗内且 `status=1`。
   *
   * 回包是 `PagedLite`：有 `items/total/page/last_page`，**没有 `per_page`**
   * （`TournamentController::list` 的 success 数组里就没这个键）⇒ 别用 `Paged<T>` 假装有。
   */
  tournaments: (status: 'upcoming' | 'active' | 'ended', p: PageQuery = {}) =>
    get<PagedLite<Tournament>>(`/tournament/list${qs({ status, ...p })}`),

  /**
   * 赛事详情。⚠ **这个端点不查 FeatureFlag**（`detail()` 里没有那道判断）⇒
   * 「详情能打开」不能当成「赛事功能开着」的证据。
   * `my_entry` 与 `leaderboard` 都由服务端算，页面不推算、不排序。
   */
  tournamentDetail: (hashid: string) => get<TournamentDetail>(`/tournament/${hashid}`),

  /**
   * 报名。服务端三道闸，任一不满足都回 400/404/422 + 原因：
   *  1. `status !== 1` ⇒ 404 'Tournament not available'；
   *  2. `start_at <= now` ⇒ 400 'Tournament has already started'（**开赛后不能再报名**）；
   *  3. 已报名 ⇒ 422 'Already entered'；满员（`max_players > 0` 且已达）⇒ 400 'Tournament is full'。
   * 页面只做本地预判（拿本机时钟比 `start_at`），真闸在服务端，被拒时原样透出 message。
   */
  tournamentJoin: (hashid: string) => post<{ id: string }>(`/tournament/${hashid}/join`, {}),

  /* ---------------- 分享短码（邀请） ---------------- */

  /**
   * 生成邀请短码（8 位，`ShareController::create`）。
   *
   * ⚠ 服务端**只有这一个写入口、没有任何读端点**：每调一次就落一行新码，
   * 历史码（配 `user_id`）和 `clicks`/`conversions` 都查不回来。
   * 所以本树不缓存、不展示「我的邀请码」这类需要回读的东西。
   * `expires_at` 建行时从不赋值、列默认 NULL ⇒ 实际永不过期，回包恒为 null。
   * 传 `activityId`（活动 hashid）才把后续转化记进该活动进度，不传记 0=无活动。
   */
  createShare: (activityId?: string) =>
    post<{ short_code: string; expires_at: string | null }>(
      '/shares',
      activityId ? { activity_id: activityId } : {},
    ),

  /**
   * 分享落地页点击上报（**匿名公开路由**，未登录也能调）。
   * 服务端只做 `clicks` 原子自增、不回内容、也不建立任何归属（ShareController::visit）。
   * 404 = 短码不存在或已过期；422 = 短码缺失/超 12 字。
   */
  visitShare: (shortCode: string) => post<unknown>('/shares/visit', { short_code: shortCode }),
};
