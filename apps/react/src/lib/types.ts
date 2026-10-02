/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * C 端接口的响应类型。与传输层分开：api.ts 只管发请求，形状定义都在这儿
 * （例外：游戏域的 `Game`/`Currency`/`GameWallet`/`PlayLog*` 已拆到 types.game.ts、
 * 数据导出域到 types.export.ts，下面两行把它们并回同一出口，故 `from './types.ts'` 的 import 照旧可用）。
 */

export type * from './types.game.ts';
export type * from './types.export.ts';

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  expires_in?: number;
}

export interface Profile {
  id: string;
  username: string;
  nickname: string | null;
  avatar: string | null;
  email?: string | null;
  created_at?: string;
}

export interface Paged<T> {
  items: T[];
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

export interface PlatformStats {
  total_games: number;
  total_users: number;
  today_game_plays: number;
  active_users_7d: number;
}

export interface WalletInfo {
  id: string;
  balance: string;
  frozen_balance: string;
  total_earned: string;
  total_spent: string;
}

export interface Transaction {
  id: string;
  type: string;
  amount: string;
  balance_after: string;
  ref_type: string | null;
  ref_id: string | null;
  remark: string | null;
  created_at: string;
}

export interface DepositOrder {
  order_no: string;
  amount: string;
  currency: string;
  platform_amount: string;
  status: string;
  paid_at: string | null;
  created_at: string;
}

export interface WithdrawOrder {
  order_no: string;
  platform_amount: string;
  method?: string | null;
  review_note?: string | null;
  status: string;
  created_at: string;
}

export interface PaymentMethod {
  id: string;
  name: string;
  type: string;
  provider: string;
  /** 最小金额，十进制字符串；max_amount 数值为 0（形如 "0.0000"）表示不限 */
  min_amount: string;
  max_amount: string;
}

export interface DepositCreated {
  order_id: string;
  order_no: string;
  amount: string;
  platform_amount: string;
  /** 支付必须在此链接完成，非 http(s) 时不要跳转 */
  checkout_url: string;
  expires_at: string;
}

export interface WithdrawApplied {
  order_id: string;
  order_no: string;
  platform_amount: string;
  fee: string;
  actual_amount: string;
  status: string;
  balance_after: string;
  created_at: string;
}

export type ExchangeDirection = 'in' | 'out';

export interface ExchangeRequest {
  game_id: string;
  currency_id: string;
  direction: ExchangeDirection;
  /** direction='out'（卖）时，后端复用该字段承载「游戏币」数量，见 ExchangeController::quote */
  platform_amount: string;
}

/** direction='in'（买）的询价结果 —— **不导出**：消费者用下面那个联合 `ExchangeQuote`（零处按名引用本名） */
interface ExchangeQuoteIn {
  platform_amount: string;
  game_amount: string;
  spread_fee: string;
  actual_game_amount: string;
  rate: string;
  spread_pct: string;
}

/** direction='out'（卖）的询价结果 —— **不导出**，同上 */
interface ExchangeQuoteOut {
  platform_amount: string;
  platform_equivalent: string;
  spread_fee: string;
  actual_platform_amount: string;
  rate: string;
  spread_pct: string;
}

export type ExchangeQuote = ExchangeQuoteIn | ExchangeQuoteOut;

/** 两个金额字段随方向换位：in=支出平台币/到账游戏币，out=卖出游戏币/到账平台币（到账侧为扣点差净额）。 */
export interface ExchangeDone {
  exchange_id: string;
  direction: ExchangeDirection;
  platform_amount: string;
  game_amount: string;
  spread_fee: string;
  rate: string;
  balance_after: string;
}

export interface Notice {
  id: string;
  type: string;
  title: string;
  content: string;
  is_read: number;
  ref_type: string | null;
  ref_id: string | null;
  created_at: string;
}

/** 公告列表项：无 content，正文只在 detail 返回 */
export interface Announcement {
  id: string;
  title: string;
  type: string;
  created_at: string;
}

export interface AnnouncementDetail extends Announcement {
  content: string;
}

export interface Leaderboard {
  id: string;
  game_id: string | null;
  name: string;
  type: string;
  metric: string;
}

/**
 * 榜单条目。`user_id` 是 **hashid 字符串** —— `LeaderboardController::ranking` 在**出网处**逐行
 * 补编（Service 直出的仍是裸 BIGINT：同一份数组还喂 WS，Redis 缓存里存的也是裸 id，所以补编落点
 * 在控制器不在 Service）。接口**没有昵称/头像** ⇒ 这一列先天只能显示编号，见 Leaderboard.tsx。
 */
export interface RankingRow {
  rank: number;
  user_id: string;
  score: string;
}

export interface ExchangeRecordRow {
  id: string;
  game_id: string;
  currency_id: string;
  direction: ExchangeDirection;
  platform_amount: string;
  game_amount: string;
  rate: string;
  spread_fee: string;
  created_at: string;
}

/* `LanguageInfo` / `LanguageList`（/language/list 的形状）2026-10-02 随 api.ts 的语言包装一并删，
   同日重核形状后**仍不恢复**（`switchLanguage` 已恢复，消费者是 `i18n/useI18n.ts`）：
   本树能选的语言 = 有文案表的语言（`i18n/` 那 13 张），而该端点回的是**后端**支持集；
   照它渲染，后端加了语言而本树没表时会给出一个「选得中、界面却静默全英文」的选项。

   真实响应形状（2026-10-02 从 `LanguageController::list` +
   `TranslationService::getAvailableLanguages()` 逐行读出，**不是旧的 `LanguageList` 类型**）：

     { code: 0, message: …, data: {
         // ⚠ 短码：中间件走的是 TranslationService::setLocale(Locale::normalize(…))
         current: 'zh',
         // ⚠ 键是**全码**：`Locale::fullCode($short)`，如 'zh-CN'
         languages: {
           'en-US': { name: 'English',              nativeName: 'English',          icon: 'us' },
           'zh-CN': { name: 'Chinese (Simplified)', nativeName: '简体中文',          icon: 'cn' },
           'ja-JP': { name: 'Japanese',             nativeName: '日本語',            icon: 'jp' },
           … 共 13 项，顺序即 Locale::SUPPORTED 的顺序
         } } }

   两个字段**码制不一致**（`current` 短码 / `languages` 键全码）—— 这正是当初撤下时留的警告，
   恢复时必须按 `Locale::normalize()` 归一后再比。

   形状只写在注释里、**不建类型**：建了就是「有类型没消费者」，与当初撤它时的理由同款。
   本树能渲染的语言集合在 `i18n/languages.ts` 的 `LANGUAGES`（13 条，`i18n.test.ts` 钉着）。 */

/** /user/2fa/status */
export interface TwoFactorStatus {
  enabled: boolean;
}

/**
 * /user/2fa/setup：qr_url 是 otpauth:// URI（不是图片地址），secret 是 Base32 明文。
 * 每次调用服务端会**先删掉旧的未启用行再建新的**（TwoFactorController::setup），
 * 所以重复点「开始设置」会让上一个二维码立刻作废，页面上只能保留最新一份。
 */
export interface TwoFactorSetup {
  secret: string;
  qr_url: string;
}

/**
 * /user/2fa/enable：8 个 10 位字母数字**备用码**，服务端只回这一次（落库的是 json）。
 * 丢失验证器时用它们登录，每条用掉即作废（verify() 里 unset 后写回）。
 */
export interface TwoFactorEnabled {
  backup_codes: string[];
}

/* ---------------- 身份认证（KYC） ---------------- */

/** 服务端下发的四种状态（IdentityController::status / apply 的状态机） */
export type IdentityStatusValue = 'not_submitted' | 'pending' | 'approved' | 'rejected';

/**
 * /user/identity/status。
 * ⚠ 除 `status` 外**只在已提交时才有值**：未提交时服务端只回 `{status:'not_submitted'}`，
 * 其余字段直接缺席（`IdentityController::status:27-31`）⇒ 全部按可选处理。
 * `real_name` 是**服务端掩码后**的（"张三"→"张*"、"Zhang San"→"Z*** S**"，maskName:152-186），
 * 前端别再截断，会二次伤害。
 * `submitted_at`/`reviewed_at` 是控制器显式 `->format('Y-m-d H:i:s')` 的**墙钟串**（:38-39），
 * 与模型上的 Carbon cast 无关；两个都过 `dt()`。
 */
export interface IdentityStatus {
  status: IdentityStatusValue;
  real_name?: string;
  id_type?: string;
  /** 审核备注，未审核时为空串或 null */
  review_note?: string | null;
  submitted_at?: string | null;
  reviewed_at?: string | null;
}

/** 提交认证的请求体。照片送的是**上传后的落库值**（`lib/upload.ts` 的返回值），不是 File */
export interface IdentityApply {
  real_name: string;
  /** 服务端白名单 `in:id_card,passport,driver_license` */
  id_type: string;
  id_number: string;
  id_front_photo: string;
  /** 唯一可选的一张（validator 里只有它是 nullable） */
  id_back_photo?: string;
  selfie_photo: string;
  country?: string;
}

/**
 * `/country/list` 的一项。服务端只映射三个字段、**没有国家名**（`CountryController::list:27-33`），
 * 所以 KYC 下拉的文案只能用 `country_code` 本身。
 * `min_deposit` 是 `DECIMAL(18,4)` 且模型 cast 成 string 的原样值；本树不展示它，
 * 但回包就长这样，别按「只用到 country_code」把形状裁掉。
 */
export interface CountryOption {
  country_code: string;
  currency: string;
  min_deposit: string;
}

/* 券类型（CouponType / Coupon / UserCouponStatus / UserCoupon）2026-10-01 随优惠券页一并撤下，
   因为服务端券可领不可核销，本树不再有消费者。恢复条件见 Layout.tsx 的 MORE 注释。 */

/**
 * /search 的回包**不是**标准分页信封：只有 {list,total,page,per_page}，**没有 last_page**。
 * 别按 Paged<T> 解（`last_page` 会读成 undefined，翻页控件静默消失）。
 */
export interface SearchResult<T> {
  list: T[];
  total: number;
  page?: number;
  per_page?: number;
}

/**
 * 另一族分页：`items` + total/page/last_page，但**没有 per_page**
 * （工单列表 TicketController::list、聊天记录 ChatController::messages 都是这形）。
 * 与 Paged<T> 差一个 `per_page`：按 Paged<T> 解会读成 undefined，别拿它算页数。
 */
export interface PagedLite<T> {
  items: T[];
  total: number;
  page?: number;
  last_page?: number;
}

/* ---------------- 工单 ---------------- */

/** 创建工单的 type 白名单，与服务端 `in:deposit,withdraw,game,account,other` 一一对应 */
export type TicketType = 'deposit' | 'withdraw' | 'game' | 'account' | 'other';

/** /ticket/list 的行。注意只有 reply_count，**没有正文** —— 正文在 detail */
export interface TicketRow {
  id: string;
  type: string;
  subject: string;
  status: string;
  priority: number;
  reply_count: number;
  created_at: string;
}

export interface TicketReply {
  id: string;
  content: string;
  /** 1 = 客服，0 = 自己 */
  is_admin: number;
  created_at: string;
}

export interface TicketDetail {
  id: string;
  type: string;
  subject: string;
  content: string;
  status: string;
  priority: number;
  replies: TicketReply[];
  created_at: string;
}

/* ---------------- 好友 / 聊天 ---------------- */

/** 好友、申请发起人、聊天对端、群成员都是这个用户摘要形状（服务端四处各自拼的，字段一致） */
export interface FriendUser {
  id: string;
  username: string;
  nickname: string | null;
  avatar: string | null;
}

/**
 * /friend/requests 的条目。⚠ `id` 是**好友关系**的 hashid（不是用户 id），
 * 它才是 accept/reject 要的 `request_id`；而 `user.id` 是发起人的用户 hashid。
 * 两个 id 混用会 404（服务端按 friend_id = 自己 + status=pending 查）。
 */
export interface FriendRequest {
  id: string;
  user: FriendUser;
  created_at: string;
}

export interface Conversation {
  peer: FriendUser;
  /** 服务端已 mb_substr 截到 100 字 */
  last_message: string;
  unread_count: number;
  updated_at: string;
}

export interface ChatMessage {
  id: string;
  from_user_id: string;
  to_user_id: string;
  content: string;
  is_read: number;
  created_at: string;
}

/* ---------------- 运营活动 ---------------- */

/** /activities/list 的行。`config` 是按 type 各自定义 schema 的 JSON（奖励挂在 rewards 或 tasks 上），
 *  猜字段名等于编造 ⇒ 本树一律不渲染，目标值只取 progress 里服务端快照的 `target`。 */
export interface Activity {
  id: string;
  /** signin / daily_task / invite（服务端白名单） */
  type: string;
  name: string;
  /**
   * ⚠ **服务端只在 >0 时才 encodeId**（ActivityController::list），所以这个字段
   * 要么是「全平台」的字面量 0，要么是游戏 hashid 字符串 —— 两种形状混在一个字段里。
   * 判空必须用 `String(game_id) === '0'`，直接 `!game_id` 会把 hashid 也当空。
   */
  game_id: string | number | null;
  status: number;
  start_at: string | null;
  end_at: string | null;
  rollout_percent?: number;
}

/** 进度只按**当天**（period_key = 今天）返回；`all` 周期（邀请转化那条写入口）不在其中 */
export interface ActivityProgress {
  activity_id: string;
  current: number;
  target: number;
  /** progressing / completed / rewarded */
  status: string;
}

/** checkin 的 reward 条目：`type` 是**币种**不是流水类型 */
export interface ActivityReward {
  type: string;
  amount: string;
}

/** /activities/{hashid}/checkin 的回包。status 三值见 ActivityService::checkin */
export interface ActivityCheckinResult {
  status: 'rewarded' | 'already' | 'progressing';
  reward?: ActivityReward[];
}

/* ---------------- 赛事 ---------------- */

/**
 * `/tournament/list` 的行。
 * ⚠ `start_at`/`end_at` 是 **ISO8601 UTC**（模型 cast 成 datetime + 控制器原样透传 ⇒
 * `json_encode(Carbon)`），不是本仓常见的 `Y-m-d H:i:s` 墙钟串 —— 渲染必须过 `dt()`。
 */
export interface Tournament {
  id: string;
  name: string;
  slug: string | null;
  type: string | null;
  description: string | null;
  /** 只有 id/name；`null` = 全平台赛事 */
  game: { id: string; name: string } | null;
  prize_pool: string;
  /** 0 = 免费。只做展示判定，不做任何金额运算 */
  entry_fee: string;
  /** `withCount('entries')` 的数，不是分页的 total */
  player_count: number;
  /** 0 = 不限人数 */
  max_players: number;
  start_at: string | null;
  end_at: string | null;
}

/** `/tournament/{hashid}` = 列表行 + 我的报名 + 排行榜（服务端已截前 100） */
export interface TournamentDetail extends Tournament {
  /** 服务端按 (tournament_id, user_id) 查出来的，**不在本地推算** */
  my_entry: { id: string; score: string; rank: number } | null;
  leaderboard: TournamentRankRow[];
}

/** 只出现在上面 `leaderboard` 那一处 —— **不导出**（零处按名引用） */
interface TournamentRankRow {
  /** 服务端存的是报名行上的 rank 列；未结算时可能为 0/null */
  rank: number | null;
  /** ⚠ 昵称；`TournamentEntry::user` 取不到时服务端给字面量 `'N/A'` */
  user: string;
  score: string;
}

/* 组队/公会的类型（GroupDetail / GroupMemberRow）与 api.ts 的 6 个包装、Group.tsx 及其路由
   2026-10-01 一并撤下。端点都能调通，但 C 端拿它做不成任何事 —— 三条后端事实：
     ① 无「我的组」列表端点（route.php 的组段只有 create/detail/members/join/leave/role）
        ⇒ 没有发现路径，hashid 无从获得（全树也确实没有一处导航过去，是死路由）；
     ② members 只回 user_id 的 hashid，没有昵称/头像 ⇒ 成员列表渲染不出人名；
     ③ `uk_group_user(group_id,user_id)` 不含 left_at，而 leave 只写 left_at 不删行（:238）、
        join 恒 INSERT（:176 存行，:179 收到 1062 就回 422 'Already a member'）⇒ **退出后重入永远失败**。
   恢复条件：上面任一条解除（有列表端点 / 成员带回昵称 / leave 真删行或改为复活旧行）。 */
