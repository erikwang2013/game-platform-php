/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Row } from '../core/api.service';
import { Field } from '../core/crud';
import { idOf } from '../core/render';
import { num } from '../core/util';
import type { Act } from '../components/table';

/**
 * 风控页各标签页的**声明式常量**（字段表 / 动作表 / 枚举），与 risk.ts 的组件分开放：
 * 组件那边是流程（取数、确认、回执），这边是「后端 validator 长什么样」的对照表。
 * 改字段前先读对应控制器（admin/app/admin/v1/controller/Risk*Controller.php），别照 DB 列名硬凑。
 */

/** 真值 = RiskSandboxService::TYPES（与 service 端评估器注册表一致）；少一个后端直接 422 */
export const RULE_TYPES = [
  'ip_blacklist',
  'amount_anomaly',
  'frequency',
  'velocity',
  'device_fingerprint',
  'ip_reputation',
  'device_account_graph',
  'withdraw_pattern',
];

/**
 * config 的键表。**服务端是唯一真值**（RiskRuleController::CONFIG_KEYS / INT_BOUNDS /
 * BOOL_KEYS / MONEY_KEYS / RATIO_KEYS），这里只做提示、不做前端校验：
 * 白名单之外的键、超范围的值、字符串 "false" 都由后端逐条 422 回来，前端再写一套只会两处漂移。
 */
const CONFIG_HINT = [
  'config 必须是 JSON 对象，键随 type 变（服务端白名单，多一个键就 422）：',
  'ip_blacklist: blacklist（字符串数组，如 ["1.2.3.4"]）',
  'amount_anomaly: min_amount（>0）、currency（≤10 字符）',
  'frequency: window_minutes（1-10080）、max_count（1-100000）',
  'velocity: window_minutes、max_accounts、same_ip（true/false）',
  'device_fingerprint: max_accounts_per_device、new_device_lookback_hours（1-8760）、new_device_withdraw_block（true/false）',
  'ip_reputation: block_score_below（0-100）、warn_score_below（0-100）、block_unknown（true/false）',
  'device_account_graph: cluster_threshold、max_accounts_per_device、frozen_sibling_block（true/false）',
  'withdraw_pattern: window_minutes、max_applies、single_hard_cap（>0）、drain_ratio（(0,1]）、sigma_window_days（2-3650）、sigma_multiplier（1-100）、fast_interval_seconds（1-86400）、fast_interval_min_count',
  '未标范围的整数键一律 1-100000；布尔键要真 JSON true/false（字符串 "false" 会被当成 true）。',
  '阈值取 0 会让判定恒真并熔断全站（含充值），服务端因此把整数下界卡在 1。',
].join('\n');

/**
 * 字段真值 = RiskRuleController::fill()（create 与 update **同一套**）：
 * name/type/action 必填，scope/config/priority/status 缺省各自回落 all/{} /100/1。
 *
 * ⚠ 本模块的 update 是**全量**语义（见 Crud.fullEdit）：只发改动字段会被 422，
 * 而 status 缺失会被后端落成 1 —— 停用中的规则一编辑就自己变成启用。
 */
export const RULE_FIELDS: Field[] = [
  {
    name: 'name',
    label: '规则名',
    type: 'text',
    required: true,
    full: true,
    placeholder: '评估器日志与命中记录里回显的就是它',
  },
  {
    name: 'type',
    label: '类型',
    type: 'select',
    required: true,
    options: RULE_TYPES.map((v) => ({ value: v, label: v })),
  },
  {
    name: 'action',
    label: '命中处置',
    type: 'select',
    required: true,
    // log 只留痕，warn 提示，block 是真拦（deposit 上也拦 ⇒ 连充值一起停）
    options: [
      { value: 'log', label: 'log（只记日志）' },
      { value: 'warn', label: 'warn（告警）' },
      { value: 'block', label: 'block（拦截）' },
    ],
  },
  {
    name: 'scope',
    label: '作用域',
    type: 'select',
    options: [
      { value: 'all', label: 'all（全部场景）' },
      { value: 'deposit', label: 'deposit（充值）' },
      { value: 'withdraw', label: 'withdraw（提现）' },
      { value: 'exchange', label: 'exchange（兑换）' },
      { value: 'login', label: 'login（登录）' },
    ],
  },
  {
    name: 'priority',
    label: '优先级',
    type: 'number',
    placeholder: '0-1000，越大越先判（缺省 100）',
  },
  { name: 'status', label: '状态', type: 'switch' },
  {
    name: 'config',
    label: '阈值配置（JSON）',
    type: 'textarea',
    full: true,
    placeholder: '{"max_count":10,"window_minutes":720}',
    hint: CONFIG_HINT,
  },
];

/** 规则行内动作：试算只读（RiskRuleController::test，不写库不落日志），没有删除端点 ⇒ 不摆删除 */
export const RULE_ACTS: Act[] = [{ key: 'test', label: '试算' }];

/**
 * 风险事件的行内处置（POST /risk/event/{hashid}/handle，入参 decision=approve|reject + note≤500）。
 * ⚠ risk_log **没有审核状态列**（RiskEventController::handle 的 Apidoc Desc 原话：处置动作由
 * OperationLog 中间件写入操作审计）⇒ 每一行都仍可再次处置，按钮因此不按状态过滤；
 * 两个词的语义差别完全由这里的文案承载，要反改就改这一处。
 */
export const EVENT_ACTS: Act[] = [
  { key: 'approve', label: '确认风险' },
  { key: 'reject', label: '驳回', danger: true },
];

/** 风险用户的行内动作：冻结无请求体（服务端按可用余额全额锁），解冻可选金额 */
export const USER_ACTS: Act[] = [
  { key: 'hold', label: '冻结', danger: true },
  { key: 'release', label: '解冻' },
];

/** 反作弊事件状态值域（AntiCheatController::review 的 `in:` 规则）+ 各自的中文说法 */
export const ANTICHEAT_STATUS: Record<string, string> = {
  open: '待审核',
  confirmed: '确认作弊',
  whitelisted: '白名单',
  closed: '关闭',
};

/**
 * 审核按钮：**字符串枚举状态**（不是 0/1 翻转），每个按钮只在自己不是当前状态时出现。
 * 状态已经等于目标的行上再摆一个按钮，点下去是把同一状态再写一遍 —— 界面在骗人。
 * 顺序 = 按钮顺序（推进态在前，「重开」这种回退态在后）。
 */
export const ANTICHEAT_ACTS: Act[] = (['confirmed', 'whitelisted', 'closed', 'open'] as const).map(
  (s): Act => ({
    key: 'rv_' + s,
    label: ANTICHEAT_STATUS[s]!,
    danger: s === 'confirmed',
    when: (row) => String(row['status'] ?? '') !== s,
  }),
);

/**
 * 团伙行内动作。三个状态按钮：0/1/2（Apidoc：0=误判 1=观察中 2=已处置）是**多值状态**，
 * **不能**用 0/1 翻转（会把 2 压成 0）；各自只在自己不是当前状态时出现。
 * 末尾「成员」是只读动作（GET /risk/clusters/{hashid}/members），没有状态可言 ⇒ 不做 when 过滤。
 */
export const CLUSTER_ACTS: Act[] = [
  { key: 'cl_1', label: '观察中', when: (row) => num(row['status']) !== 1 },
  { key: 'cl_2', label: '已处置', when: (row) => num(row['status']) !== 2 },
  { key: 'cl_0', label: '标记误判', when: (row) => num(row['status']) !== 0 },
  { key: 'members', label: '成员' },
];

/**
 * 设备行内动作（POST /risk/device/{block|unblock}，**路径不带 id**，请求体 `{fp_hash}`）。
 * `fp_hash` 是列表回传的**完整 64 位十六进制**：`RiskDeviceController::fpHash()` 只认这个形状，
 * 提交 `fp_masked`（前 8 位 + ****）一律 400 ⇒ 行内动作读的是 fp_hash，掩码只用于显示与确认文案。
 * 两个动作按 blocked 互斥：已拉黑的行只出「解封」，未拉黑的行只出「拉黑」——
 * 反的那个点下去要么重复标记、要么把服务端刚翻过去的状态再翻回来。
 * （`blocked` 是 PHP bool ⇒ JSON true/false；写成真假值判断，0/1 或 undefined 也照旧成立。）
 */
export const DEVICE_ACTS: Act[] = [
  { key: 'block', label: '拉黑', danger: true, when: (row) => !row['blocked'] },
  { key: 'unblock', label: '解封', when: (row) => !!row['blocked'] },
];

/** 拉黑是**真拦截**（RiskService::check() 对人工标记短路，不看 device_fingerprint 规则是否启用）⇒ 文案要说清代价 */
export const deviceConfirmText = (row: Row): string =>
  `确认拉黑设备「${String(row['fp_masked'] ?? '')}」？该指纹上的账号充值/提现将直接被拒（30 天后自动解封）。`;

/** 请求体：只发完整哈希；掩码字段只用于显示，绝不进请求体 */
export const deviceBody = (row: Row): Row => ({ fp_hash: String(row['fp_hash'] ?? '') });

/** 回执：block 回 data.fp_masked（服务端重新掩码的那个），unblock 只回空 data ⇒ 缺省用行上的 */
export const deviceNote = (row: Row, key: string, data: Row): string =>
  `${key === 'block' ? '已拉黑设备 ' : '已解封设备 '}${String(data['fp_masked'] ?? row['fp_masked'] ?? '')}`;

export const CLUSTER_STATUS: Record<string, string> = {
  '0': '误判',
  '1': '观察中',
  '2': '已处置',
};

export const RISK_TABS = [
  { key: 'overview', label: '风控总览' },
  { key: 'users', label: '风险用户' },
  { key: 'events', label: '风险事件' },
  { key: 'rules', label: '风控规则' },
  { key: 'clusters', label: '关联团伙' },
  { key: 'ip', label: 'IP 信誉' },
  { key: 'devices', label: '设备' },
  { key: 'anticheat', label: '反作弊' },
];

/** 取数路径（每个标签页一个端点；overview 单独走 risk/overview） */
export const RISK_PATHS: Record<string, string> = {
  users: 'risk/users',
  events: 'risk/event/list',
  rules: 'risk/rule/list',
  clusters: 'risk/clusters',
  ip: 'risk/ip/list',
  devices: 'risk/device/list',
  anticheat: 'anticheat/events',
};

/**
 * 每张表的表头（键 = 控制器 format() 回给前端的字段名）。必须显式给：自动推导按对象键序
 * 截到 10 列，事件表有 12 个键 ⇒ created_at 这种最该看的反而被切掉。
 * clusters 用摊平出来的 status_label（0/1/2 三值，见 risk.ts 的 fetch）。
 */
export const RISK_HEADS: Record<string, Record<string, string>> = {
  users: {
    user_id: '用户',
    username: '用户名',
    score: '信任分',
    band: '档位',
    hit_count: '命中次数',
    last_hit_at: '最近命中',
    whitelisted: '白名单',
  },
  events: {
    id: '事件',
    user_id: '用户',
    rule_name: '规则',
    type: '类型',
    action: '处置',
    result: '结果',
    ip_masked: 'IP',
    fp_masked: '设备',
    created_at: '时间',
  },
  rules: {
    id: 'ID',
    name: '规则名',
    type: '类型',
    scope: '作用域',
    action: '处置',
    priority: '优先级',
    status: '状态',
  },
  clusters: {
    id: 'ID',
    name: '团伙',
    type: '类型',
    fingerprint_masked: '指纹',
    user_count: '账号数',
    status_label: '状态',
    updated_at: '更新时间',
  },
  ip: {
    ip_masked: 'IP 哈希',
    reputation_score: '信誉分',
    source: '来源',
    hit_count: '命中次数',
    last_seen_at: '最近出现',
  },
  devices: {
    fp_masked: '设备指纹',
    ip_c_segment: 'C 段',
    account_count: '关联账号',
    blocked: '已拉黑',
    last_seen_at: '最近出现',
  },
  anticheat: {
    id: '事件',
    user_id: '用户',
    game_id: '游戏',
    rule_type: '规则',
    severity: '严重度',
    action: '处置',
    status: '状态',
    review_note: '审核备注',
    created_at: '时间',
  },
};

/** IP 四个动作的回执动词（端点同一个形状：POST /risk/ip/{key} `{ip}`） */
export const IP_VERBS: Record<string, string> = {
  block: '已拉黑',
  whitelist: '已加入白名单',
  appeal: '已申诉放行',
  recheck: '已刷新信誉缓存',
};

/**
 * 试算 context 的解析：只认 **JSON 对象**（服务端 `!is_array($context)` 会把它静默换成 []，
 * 于是评估器拿到空上下文、试算结果永远是「未命中」）。非法 JSON / 数组 / 标量一律返回 null，
 * 由调用方报错。**不校验业务键**：键的值域是各评估器的事。
 */
export function parseContext(raw: string): unknown | null {
  if (raw.trim() === '') return {};
  try {
    const v: unknown = JSON.parse(raw);
    return v && typeof v === 'object' && !Array.isArray(v) ? v : null;
  } catch {
    return null;
  }
}

/** 抽屉标题：与 risk.ts 的 panel() 一一对应（试算 / 候选 / 只读的团伙成员） */
export const PANEL_TITLES: Record<string, string> = {
  result: '规则试算（只读，未写库）',
  candidates: '聚类候选（未落库）',
  members: '团伙成员（只读）',
};

/** 聚类候选抽屉：候选字段 = detect 的 candidates[]（fingerprint 是完整哈希，掩码列只用于显示） */
export const CANDIDATE_HEADS: Record<string, string> = {
  type: '类型',
  fingerprint_masked: '指纹',
  user_count: '账号数',
};

export const CANDIDATE_ACTS: Act[] = [{ key: 'confirm', label: '确认为团伙' }];

/**
 * 二次确认文案里的对象标识：**必须能认出是谁**（只有一个 hashid 等于没告诉人）。
 * 三种行的字段名不同：事件行给 rule_name，风险用户行给 username，反作弊行给 rule_name/rule_type。
 */
export function whoEvent(row: Row): string {
  const rule = String(row['rule_name'] ?? '');
  const user = String(row['user_id'] ?? '');
  return (
    [rule && `规则「${rule}」`, user && `用户 ${user}`].filter(Boolean).join(' / ') || idOf(row)
  );
}

export function whoUser(row: Row): string {
  const name = String(row['username'] ?? '');
  const id = String(row['user_id'] ?? '');
  return name ? `${name}（${id}）` : id || idOf(row);
}

export function whoAnti(row: Row): string {
  const rule = String(row['rule_name'] ?? '') || String(row['rule_type'] ?? '');
  const user = String(row['user_id'] ?? '');
  return (
    [rule && `规则「${rule}」`, user && `用户 ${user}`].filter(Boolean).join(' / ') || idOf(row)
  );
}
