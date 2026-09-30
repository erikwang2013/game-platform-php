/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 风控域（批次 5）的列表项与请求/响应结构。
 * 字段真值一律取自 admin/app/admin/v1/controller/{RiskRule,RiskEvent,RiskDevice,RiskIp,
 * RiskUser,RiskCluster}Controller.php 与 AntiCheatController.php 的 format()/返回值，
 * 单独成文件是因为 DataModels.ets 已 533 行（新增只会把它推得更远）。
 *
 * 三条本域特有的口径：
 *  1. **不是所有端点都用 `page/limit`**：风控族的列表参数是 **`size`**（控制器 `$request->get('size', 20)`），
 *     与其它模块的 `limit` 不是同一套 —— 照抄 `?page=&limit=` 会静默退回缺省 20 条。
 *  2. **行的标识不都叫 `id`**：风控用户行是 `user_id`；设备/IP 行**根本没有 id**
 *     （只有掩码，见下面各接口的注释），故它们的页面不做行内定位动作。
 *  3. **金额一律 string**（bcmath 口径）：冻结/解冻的金额来自 DECIMAL 列，原样上送与展示，
 *     本文件不给它们 number 类型，免得半路被 Number() 转成 float。
 */

/**
 * 风控族的分页响应：`{total, items}`。
 * 不用 DataModels 的 `PageResult<T>`：那个契约是 `{list, total, page, limit}`，
 * 按它给 `items` 端点做断言会让 `list` 在运行时是 undefined（类型上却看不出来）。
 */
export interface ItemsPage<T> {
  /** 两个字段都可缺：信封少给时页面按空列表渲染，而不是让 `undefined.length` 崩在渲染里 */
  items?: T[];
  total?: number;
}

/** 风控规则（GET /admin/v1/risk/rule/list —— **分页参数是 size**，响应是 `{total, items}`） */
export interface RiskRuleItem {
  id: string;
  name: string;
  /** 8 个枚举之一（RiskSandboxService::TYPES） */
  type: string;
  /** all / deposit / withdraw / exchange / login */
  scope: string;
  /** JSON 对象文本（TEXT 列，无 cast）—— 键白名单随 type 变，见 RiskRuleController::CONFIG_KEYS */
  config: string;
  /** log / warn / block */
  action: string;
  priority: number;
  status: number;
}

/**
 * 沙箱试算结果（POST /admin/v1/risk/rule/test）—— 只读评估，不写库。
 * `action` 是**经 disposition 折算后**的处置（high 保留规则自身、medium 时 block 降为 warn、low 一律 log）。
 */
export interface RiskSandboxResult {
  /** 是否命中该规则 */
  matched: boolean;
  /** 命中说明（未命中是空串） */
  message: string;
  /** low / medium / high */
  severity: string;
  /** 折算后的处置：log / warn / block */
  action: string;
}

/** 试算请求体：context 必须是**对象**（控制器 `is_array($context)` 才收，字符串会被静默当成空） */
export interface RiskSandboxRequest {
  rule_id: string;
  user_id: string;
  check_type: string;
  context: Object;
}

/** 风控事件（GET /admin/v1/risk/event/list）—— risk_log 的格式化输出，**没有 status 列** */
export interface RiskEventItem {
  id: string;
  user_id: string;
  rule_id: string;
  /** 规则名（按 rule_id 关联带出），规则已删则是空串 */
  rule_name: string;
  type: string;
  action: string;
  result: string;
  detail: string;
  /** JSON 列，`json_decode(...) ?: []` ⇒ 对象或空数组 */
  context: Object;
  /** 只回掩码（前 8 位 + ****） */
  ip_masked: string;
  fp_masked: string;
  created_at: string;
}

/**
 * 设备指纹（GET /admin/v1/risk/device/list）—— 行**没有 id**（列表按指纹聚合，不是实体表的主键）。
 * 但每行回**完整 `fp_hash`**：封禁/解封端点收的就是它（64 位十六进制），掩码提交不上去
 * ⇒ 页面的两个动作挂在行上（见 RiskDevicePage），展示仍优先用 `fp_masked`。
 */
export interface RiskDeviceItem {
  /** 完整指纹，block/unblock 的入参；缺字段时行内动作置灰（不当成「有值」提交） */
  fp_hash?: string;
  fp_masked: string;
  ip_c_segment: string;
  account_count: number;
  first_seen_at: string;
  last_seen_at: string;
  /**
   * 人工拉黑标记（Redis，TTL 30 天到期自然解封）。
   * **会真的阻断**：`RiskService::check()` 对它短路成 `block`，不看任何规则是否启用
   * （install.sql 那条 device 规则 status=0；Redis 故障时 fail-open 按未拉黑处理）。
   */
  blocked: boolean;
}

/**
 * IP 信誉（GET /admin/v1/risk/ip/list）。
 * **同样只有 ip_masked，没有 id**；四个写端点收的是**原文 IP**（`filter_var(..., FILTER_VALIDATE_IP)`），
 * 掩码串过不了校验 ⇒ 与设备同款，操作要人工输入原 IP。
 */
export interface RiskIpItem {
  ip_masked: string;
  /** 0..100，越高越可信（白名单 100 / 黑名单 0） */
  reputation_score: number;
  /** internal_blacklist / internal_whitelist / 外部来源 */
  source: string;
  hit_count: number;
  first_seen_at: string;
  last_seen_at: string;
}

/** 异常用户（GET /admin/v1/risk/users）—— 队列来自 user_trust，**行标识是 `user_id`（不是 id）** */
export interface RiskUserItem {
  /** hashid（控制器 `encodeId($userId)`） */
  user_id: string;
  username: string;
  /** 信任分，越低越可疑 */
  score: number;
  band: string;
  hit_count: number;
  last_hit_at: string;
  whitelisted: number;
}

/** 冻结回执（POST /admin/v1/risk/users/{hashid}/hold）—— 金额是服务端算的全额 */
export interface RiskHoldResult {
  user_id: string;
  /** DECIMAL 的 string cast：只展示与拼接，不做任何算术 */
  frozen_amount: string;
}

/** 解冻回执（POST /admin/v1/risk/users/{hashid}/release）—— 实际释放量由服务端按子台账算出 */
export interface RiskReleaseResult {
  user_id: string;
  released_amount: string;
}

/** 风控时间线（GET /admin/v1/risk/users/{hashid}/timeline）—— 合并 risk_log/play_log/anticheat_event */
export interface RiskTimelineItem {
  time: string;
  /** risk / play / anticheat */
  source: string;
  type: string;
  action: string;
  result: string;
  detail: string;
}

export interface RiskTimelineResult {
  user_id: string;
  total: number;
  events: RiskTimelineItem[];
}

/** 已确认团伙（GET /admin/v1/risk/clusters —— **没有 /list 段**，路由直挂 /clusters） */
export interface RiskClusterItem {
  id: string;
  name: string;
  type: string;
  /** 完整指纹（same_ip 是 ip_hash、same_device 是 fp_hash；manual 可为空串） */
  fingerprint: string;
  fingerprint_masked: string;
  user_count: number;
  /** 1 观察中 / 2 已处置 / 0 误判 —— **三态，不能用 0/1 翻转控件** */
  status: number;
  created_at: string;
  updated_at: string;
}

/** 聚类候选（POST /admin/v1/risk/clusters/detect）—— 只出候选**不落库**（近 7 天，IP≥5 账户 / 设备≥3 账户） */
export interface RiskClusterCandidate {
  /** same_ip / same_device */
  type: string;
  fingerprint: string;
  fingerprint_masked: string;
  user_count: number;
}

export interface RiskClusterDetectResult {
  window_days: number;
  candidates: RiskClusterCandidate[];
}

/** 团伙成员（GET /admin/v1/risk/clusters/{hashid}/members） */
export interface RiskClusterMember {
  id: string;
  username: string;
}

export interface RiskClusterMembersResult {
  cluster: RiskClusterItem;
  members: RiskClusterMember[];
}

/**
 * 人工确认团伙的请求体：**不含 member_ids**（控制器收 hashid 数组，但创建时还没有 cluster id、
 * 也就拿不到成员 hashid；名单由服务端按 fingerprint 现查 —— 详见 RiskClusterPage 文件头）。
 */
export interface RiskClusterConfirmRequest {
  type: string;
  fingerprint: string;
  name: string;
  user_count: number;
}

/** 设备封禁回执（POST /admin/v1/risk/device/block） */
export interface RiskDeviceBlockResult {
  fp_masked: string;
}

/** IP 信誉写入回执（block / whitelist / appeal 共用） */
export interface RiskIpWriteResult {
  ip_masked: string;
  source: string;
}

/** 反作弊事件（GET /admin/v1/anticheat/events）—— status 是**字符串枚举** */
export interface AntiCheatEventItem {
  id: string;
  user_id: string;
  game_id: string;
  rule_type: string;
  rule_name: string;
  severity: number;
  score_delta: number;
  action: string;
  /** JSON 列，`json_decode(...) ?: []` */
  evidence: Object;
  round_id: string;
  stat_date: string;
  /** open / confirmed / whitelisted / closed */
  status: string;
  review_note: string;
  created_at: string;
}
