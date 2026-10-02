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
 * 分页查询串（`page` + 页大小）—— **一次扇出三个别名**，谁读得懂谁拿。
 *
 * 后端读页大小的键不统一：`limit`（列表类控制器，默认 15）、`size`（风控七个端点，默认 20）、
 * `per_page`（SearchController）。只发其中一种时，读不懂的端点会**静默退回自己的默认值**：
 * 本树页大小恒 15，与默认值 20 的风控端点就对不上，第 15 条之后的数据翻页翻不到。
 * 多出来的键服务端不读，无副作用（控制器一律 `$request->input('key', 默认)` 按需取）。
 */
export function pageQuery(page: number, size: number): string {
  return `page=${page}&page_size=${size}&limit=${size}&size=${size}&per_page=${size}`;
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
  /** 已分配角色的 **hashid** 数组（`UserController::withRoleIds` 回传，列表与详情都带；裸 `roles` 已被后端 unset） */
  role_ids?: string[];
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

/**
 * 表单值（框内）：一律 string —— HTTP 表单语义，number 用十进制串、switch 用 '1'/'0'。
 * 值域是运行时的（字段描述驱动），故不用联合类型，换算集中在 FormDialog.payload()
 */
export type FormValues = Record<string, string>;

/** 表单提交体（线上）：switch 已换算成 0/1、number 已换算成整型，可直接当请求体 */
export type FormPayload = Record<string, string | number | string[]>;

/** 状态切换请求体（body 里的 id 是 hashid） */
export interface ToggleRequest {
  id: string;
  status: number;
}

/**
 * 游戏（/admin/v1/game/list）—— 列表已按行返回全部可编辑列（api_key/api_secret 是 $hidden），
 * 故编辑表单的回填快照直接取自列表行，不必再拉一次详情
 */
export interface GameItem {
  id: string;
  name: string;
  slug: string;
  type: string;
  platform: string;
  region: string;
  status: number;
  sort: number;
  description?: string;
  cover_image?: string;
  api_endpoint?: string;
  sdk_version?: string;
}

/** 公告（/admin/v1/announcement/list） */
export interface AnnouncementItem {
  id: string;
  title: string;
  content: string;
  type: string;
  target_lang: string;
  status: number;
  created_at: string;
}

/**
 * 报表汇总（/admin/v1/report/summary）
 */
export interface ReportSummary {
  start: string;
  end: string;
  new_users: number;
  deposit_amount: string;
  deposit_count: number;
  withdraw_amount: string;
  withdraw_count: number;
  exchange_amount: string;
  play_count: number;
}

/* ---------------------------------------------------------------------------
 * 「内容与配置」8 个模块（批次 2）—— 通用行 + 各模块列表项。
 * 字段真值一律取自 admin/app/admin/v1/controller/<X>Controller.php 的 validator
 * 与 packages/platform-common/src/model/<X>.php 的列定义，描述见 common/ResourceModules.ets。
 * ------------------------------------------------------------------------- */

/**
 * 通用资源行 —— 18 个模块共用一套列表渲染（各模块的字段描述在 ResourceModules.ets）。
 * values 是**编辑表单快照**（键与模块 editFields 对齐），列表加载时一次算好，点编辑直接用。
 */
export interface ResourceRow {
  /** 行标识：列表返回的 id 原样（hashid） */
  id: string;
  /** 首行主标题 */
  title: string;
  /** 次行摘要 */
  subtitle: string;
  /** 状态徽标文案；空串 = 该实体没有 status 列，不渲染徽标 */
  badge: string;
  /** 徽标配色：true = 绿（启用/正常），false = 红 */
  badgeOk: boolean;
  /** 当前 status（无 status 的模块恒 0），行内切换用它算目标状态 */
  status: number;
  /** 编辑表单预填值 */
  values: FormValues;
  /** 树形模块（权限树）的层级，0 = 顶层；平铺模块缺省 —— 行组件按它缩进 */
  depth?: number;
  /** 树形模块：有子节点 ⇒ 行首画展开/折叠箭头 */
  hasChildren?: boolean;
  /** 树形模块：祖先 id 链（根行为空数组）；折叠某节点时靠它整枝隐藏 */
  ancestors?: string[];
}

export interface ResourceListResult {
  rows: ResourceRow[];
  total: number;
}

/** 游戏分类（GET /admin/v1/game/category/list —— 整表返回，不分页） */
export interface GameCategoryItem {
  id: string;
  name: string;
  slug: string;
  icon: string;
  sort: number;
  status: number;
}

/** 游戏区服（GET /admin/v1/game/server/list?game_id=<hashid>）—— data 是裸数组，不是 {list} */
export interface GameServerItem {
  id: string;
  /** encodeIds 默认只编 id ⇒ game_id 是裸 BIGINT，不进表单（前端拿不到它，见报告） */
  game_id: number;
  name: string;
  region: string;
  /** 0 维护 / 1 正常 / 2 火爆 / 3 新服（四态，见 GameServerController::update 注释） */
  status: number;
  sort: number;
}

/** 运营活动（GET /admin/v1/activities/list）—— config 在模型里 cast 成 array，故是对象不是字符串 */
export interface ActivityItem {
  id: string;
  type: string;
  name: string;
  game_id: number;
  config: Object | null;
  /** 0 停用 / 1 启用 / 2 已结束 */
  status: number;
  start_at: string | null;
  end_at: string | null;
  rollout_percent: number;
}

/** 成就（GET /admin/v1/achievement/list）—— condition_json 是无 cast 的字符串列，原样存 JSON 文本 */
export interface AchievementItem {
  id: string;
  key: string;
  name: string;
  description: string;
  icon: string;
  condition_json: string;
  points: number;
  status: number;
}

/** VIP 等级（GET /admin/v1/vip/level/list） */
export interface VipLevelItem {
  id: string;
  level: number;
  name: string;
  required_exp: number;
  /** 无 cast 的字符串列：JSON 对象文本，键白名单见 VipLevelController::validateBenefits */
  benefits: string;
}

/** 排行榜（GET /admin/v1/leaderboard/list） */
export interface LeaderboardItem {
  id: string;
  name: string;
  /** daily / weekly / monthly / alltime */
  type: string;
  /** earned / spent / play_count */
  metric: string;
  game_id: number;
  rule: string;
  status: number;
  sort: number;
}

/** 国家配置（GET /admin/v1/country/config/list）—— 金额列都是字符串（bcmath 口径），表单也不做 float 转换 */
export interface CountryConfigItem {
  id: string;
  country_code: string;
  currency: string;
  payment_methods: string;
  withdraw_methods: string;
  min_deposit: string;
  status: number;
}

/** 系统配置（GET /admin/v1/config —— 路由占位是 {id}，值仍是 hashid：控制器 decodeId 解它） */
export interface PlatformConfigItem {
  id: string;
  group: string;
  key: string;
  value: string;
  type: string;
  description: string;
}

/* ---------------------------------------------------------------------------
 * 「用户与权限」三个模块（批次 3）—— 通用行 + 各模块列表项。
 * 字段真值同样取自 admin/app/admin/v1/controller/{PlatformUser,Role,Permission}Controller.php
 * 的 validator 与列定义，描述见 common/UserModules.ets。
 * ------------------------------------------------------------------------- */

/**
 * 平台(C端)用户（GET /admin/v1/platform/user/list）。
 * 只声明本页真要用的列：email/phone 走 Encryptable 且两树 cipher 有已知分歧（见记忆
 * encryptable-cipher-divergence-admin-service），列表页不拿它们做展示，免得把一个
 * 加解密问题显示成空白或明文。
 */
export interface PlatformUserItem {
  id: string;
  username: string;
  nickname: string | null;
  /** 0 禁用 / 1 正常（UPDATE 严格只收 0/1） */
  status: number;
  created_at: string;
}

/** 角色（GET /admin/v1/role —— 注意没有 /list 段，Route::resource 直挂 /role） */
export interface RoleItem {
  id: string;
  name: string;
  slug: string;
  description: string;
  status: number;
  /** withCount('users') 带出的关联用户数；删除角色会 detach 这些用户 */
  users_count?: number;
  /** 已授权限的 **hashid** 数组（RoleController::index 回传），编辑态用它回填多选 */
  permission_ids?: string[];
}

/**
 * 权限节点（GET /admin/v1/permission）—— 树形，非根节点的 children 才存在。
 * `id` 与 `parent_id` 都是 hashid（PermissionController::buildTree 把 parent_id 也编了）；
 * 根节点的 parent_id 是字面量 0（hashid 编不出 0）⇒ 类型是联合。
 */
export interface PermissionNode {
  id: string;
  parent_id: string | number;
  name: string;
  slug: string;
  /** 1 菜单 / 2 按钮 / 3 接口（store 的 `in:1,2,3`） */
  type: number;
  icon: string | null;
  path: string | null;
  sort: number;
  children?: PermissionNode[];
}

/** KYC 身份认证记录（GET /admin/v1/identity/list） */
export interface IdentityItem {
  id: string;
  real_name: string | null;
  id_type: string;
  id_number: string | null;
  country: string | null;
  /** pending / approved / rejected（审核只推进 pending，见 IdentityController::review 的 CAS） */
  status: string;
  review_note: string | null;
  reviewed_at: string | null;
  created_at: string;
  /**
   * 三态：`toArray()` **恒带这个键**，关联查不到时是 **null**（不是缺键、也不是 undefined）。
   * 旧注释写「只在存在时带出这一项」是假的 —— 只判 `!== undefined` 会在这种行上抛
   * （`null.username`）。与 WithdrawItem.user 同款，两种都要防。
   */
  user?: IdentityUserRef | null;
}

export interface IdentityUserRef {
  id: string;
  username: string;
}

/*
 * 「资金」四个模块 + 工单/上传的形状已搬到 model/FundModels.ets（本文件此前 572 行，越过本仓 500 行上限）。
 * 从本文件转出，故所有 `import { … } from '../model/DataModels'` 的调用点一行未改。
 */
export {
  PaymentMethodItem,
  CouponItem,
  CouponStats,
  CdnProviderItem,
  WithdrawLimitItem,
  WithdrawOrderItem,
  WithdrawUserRef,
  WithdrawSwitchState,
  TicketItem,
  UploadTicket
} from './FundModels';
