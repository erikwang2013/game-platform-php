/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Row } from '../core/api.service';
import { Field } from '../core/crud';
import { t } from '../core/i18n/i18n';
import { idOf } from '../core/render';
import { num } from '../core/util';
import type { Act } from '../components/table';

/**
 * 风控页各标签页的**声明式常量**（字段表 / 动作表 / 枚举），与 risk.ts 的组件分开放：
 * 组件那边是流程（取数、确认、回执），这边是「后端 validator 长什么样」的对照表。
 * 改字段前先读对应控制器（admin/app/admin/v1/controller/Risk*Controller.php），别照 DB 列名硬凑。
 */

/**
 * 抽屉内容类型：risk.ts 的 panel 信号、openPanel 的入参、READ_PANELS/PANEL_TITLES 的键集共用这一个
 * 联合（三处各写一遍的话，加一种抽屉就要改三处，漏一处是编译期报错、再漏一处是空白抽屉）。
 */
export type PanelKind = 'result' | 'candidates' | 'members' | 'timeline' | 'graph' | 'graphclusters';

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
 * config 的键表提示。**服务端是唯一真值**（RiskRuleController::CONFIG_KEYS / INT_BOUNDS /
 * BOOL_KEYS / MONEY_KEYS / RATIO_KEYS），这里只做提示、不做前端校验：
 * 白名单之外的键、超范围的值、字符串 "false" 都由后端逐条 422 回来，前端再写一套只会两处漂移。
 * 文案本身是词条 `risk.rule.keys_note`（见 dict/risk.ts），这里只留键名。
 */
const CONFIG_HINT = 'risk.rule.keys_note';

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
    label: 'risk.rule.name',
    type: 'text',
    required: true,
    full: true,
    placeholder: 'risk.rule.name_hint',
  },
  {
    name: 'type',
    label: 'risk.rule.type',
    type: 'select',
    required: true,
    // 选项名就用后端枚举原文（ip_blacklist…）：译了反而与 config 的键、与日志里的值对不上
    options: RULE_TYPES.map((v) => ({ value: v, label: v })),
  },
  {
    name: 'action',
    label: 'risk.rule.action',
    type: 'select',
    required: true,
    // log 只留痕，warn 提示，block 是真拦（deposit 上也拦 ⇒ 连充值一起停）
    options: [
      { value: 'log', label: 'risk.action.log' },
      { value: 'warn', label: 'risk.action.warn' },
      { value: 'block', label: 'risk.action.block' },
    ],
  },
  {
    name: 'scope',
    label: 'risk.rule.scope',
    type: 'select',
    options: [
      { value: 'all', label: 'risk.scope.all' },
      { value: 'deposit', label: 'risk.scope.deposit' },
      { value: 'withdraw', label: 'risk.scope.withdraw' },
      { value: 'exchange', label: 'risk.scope.exchange' },
      { value: 'login', label: 'risk.scope.login' },
    ],
  },
  {
    name: 'priority',
    label: 'risk.rule.priority',
    type: 'number',
    hint: 'risk.rule.priority_hint',
  },
  { name: 'status', label: 'risk.rule.status', type: 'switch' },
  {
    name: 'config',
    label: 'risk.rule.config',
    type: 'textarea',
    full: true,
    placeholder: '{"max_count":10,"window_minutes":720}',
    hint: CONFIG_HINT,
  },
];

/** 规则行内动作：试算只读（RiskRuleController::test，不写库不落日志），没有删除端点 ⇒ 不摆删除 */
export const RULE_ACTS: Act[] = [{ key: 'test', label: 'risk.rule.test' }];

/**
 * 风险事件的行内处置（POST /risk/event/{hashid}/handle，入参 decision=approve|reject + note≤500）。
 * ⚠ risk_log **没有审核状态列**（RiskEventController::handle 的 Apidoc Desc 原话：处置动作由
 * OperationLog 中间件写入操作审计）⇒ 每一行都仍可再次处置，按钮因此不按状态过滤；
 * 两个词的语义差别完全由这里的文案承载，要反改就改这一处。
 */
export const EVENT_ACTS: Act[] = [
  { key: 'approve', label: 'risk.event.approve' },
  { key: 'reject', label: 'risk.event.reject', danger: true },
];

/**
 * 风险用户的行内动作：冻结无请求体（服务端按可用余额全额锁），解冻可选金额。
 * 「时间轴」（GET /risk/users/{hashid}/timeline）与「关联图谱」（GET /risk/graph/{hashid}）都是
 * 只读动作，无状态可言 ⇒ 不做 when 过滤。
 */
export const USER_ACTS: Act[] = [
  { key: 'hold', label: 'risk.user.hold', danger: true },
  { key: 'release', label: 'risk.user.release' },
  { key: 'timeline', label: 'risk.user.timeline' },
  { key: 'graph', label: 'risk.graph.title' },
];

/**
 * 风险时间轴的表头。⚠ `source` 是**事件的来源系统**（risk=风控命中 / play=对局记录 /
 * anticheat=反作弊），与 `type`（规则类型）是两列不同的东西 —— 三源合并后只看 type 会分不清
 * 「这条是风控拦的」还是「这条是对局里发现的」。
 */
export const TIMELINE_HEADS: Record<string, string> = {
  time: 'risk.head.time',
  source: 'risk.head.source',
  type: 'risk.head.type',
  action: 'risk.head.action',
  result: 'risk.head.result',
  detail: 'risk.head.detail',
};

/** 反作弊事件状态值域（AntiCheatController::review 的 `in:` 规则）+ 各自的词条键（值 = risk.ac.status.*） */
export const ANTICHEAT_STATUS: Record<string, string> = {
  open: 'risk.ac.status.open',
  confirmed: 'risk.ac.status.confirmed',
  whitelisted: 'risk.ac.status.whitelisted',
  closed: 'risk.ac.status.closed',
};

/**
 * 审核按钮：**字符串枚举状态**（不是 0/1 翻转），每个按钮只在自己不是当前状态时出现。
 * 状态已经等于目标的行上再摆一个按钮，点下去是把同一状态再写一遍 —— 界面在骗人。
 * 顺序 = 按钮顺序（推进态在前，「重开」这种回退态在后）。
 */
export const ANTICHEAT_ACTS: Act[] = [
  ...(['confirmed', 'whitelisted', 'closed', 'open'] as const).map(
    (s): Act => ({
      key: 'rv_' + s,
      label: ANTICHEAT_STATUS[s]!,
      danger: s === 'confirmed',
      when: (row) => String(row['status'] ?? '') !== s,
    }),
  ),
  // 只读详情（GET /anticheat/events/{hashid}）摆在四个状态按钮之后：列表的 format() 已把
  // evidence 摊成对象，但不带 user_trust（信任分/档位/命中数只在详情里）
  { key: 'detail', label: 'risk.ac.detail' },
];

/**
 * 团伙行内动作。三个状态按钮：0/1/2（Apidoc：0=误判 1=观察中 2=已处置）是**多值状态**，
 * **不能**用 0/1 翻转（会把 2 压成 0）；各自只在自己不是当前状态时出现。
 * 末尾「成员」是只读动作（GET /risk/clusters/{hashid}/members），没有状态可言 ⇒ 不做 when 过滤。
 */
export const CLUSTER_ACTS: Act[] = [
  { key: 'cl_1', label: 'risk.cluster.act.1', when: (row) => num(row['status']) !== 1 },
  { key: 'cl_2', label: 'risk.cluster.act.2', when: (row) => num(row['status']) !== 2 },
  { key: 'cl_0', label: 'risk.cluster.act.0', when: (row) => num(row['status']) !== 0 },
  { key: 'members', label: 'risk.cluster.members' },
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
  { key: 'block', label: 'risk.device.block', danger: true, when: (row) => !row['blocked'] },
  { key: 'unblock', label: 'risk.device.unblock', when: (row) => !!row['blocked'] },
];

/** 拉黑是**真拦截**（RiskService::check() 对人工标记短路，不看 device_fingerprint 规则是否启用）⇒ 文案要说清代价 */
export const deviceConfirmText = (row: Row): string =>
  t('risk.device.block_confirm', { fp: String(row['fp_masked'] ?? '') });

/**
 * 回执与确认文案里的**拼装逻辑**（纯函数，和 deviceConfirmText 同一类）：组件那边只管「什么时候弹、
 * 弹完干什么」，拼句子与查词条都放这里 —— 句子一变，改的是这一处。
 */
export const fundsNote = (key: string, data: Row): string =>
  t(key === 'hold' ? 'risk.user.hold_done' : 'risk.user.release_done', {
    amount: String(data[key === 'hold' ? 'frozen_amount' : 'released_amount'] ?? ''),
  });

export const clusterMarked = (row: Row, id: string, status: number): string =>
  t('risk.cluster.marked', {
    name: String(row['name'] ?? id),
    // status 是数值枚举（0/1/2）⇒ 先过 CLUSTER_STATUS 翻成词条键，再查表
    status: t(CLUSTER_STATUS[String(status)] ?? String(status)),
  });

export const clusterCreated = (data: Row, fallback: string): string =>
  t('risk.cluster.created', { name: String((data['cluster'] as Row)?.['name'] ?? fallback) });

/** 请求体：只发完整哈希；掩码字段只用于显示，绝不进请求体 */
export const deviceBody = (row: Row): Row => ({ fp_hash: String(row['fp_hash'] ?? '') });

/** 回执：block 回 data.fp_masked（服务端重新掩码的那个），unblock 只回空 data ⇒ 缺省用行上的 */
export const deviceNote = (row: Row, key: string, data: Row): string =>
  t(key === 'block' ? 'risk.device.blocked_note' : 'risk.device.unblocked_note', {
    fp: String(data['fp_masked'] ?? row['fp_masked'] ?? ''),
  });

export const CLUSTER_STATUS: Record<string, string> = {
  '0': 'risk.cluster.status.0',
  '1': 'risk.cluster.status.1',
  '2': 'risk.cluster.status.2',
};

export const RISK_TABS = [
  { key: 'overview', label: 'risk.tab_overview' },
  { key: 'users', label: 'risk.tab_users' },
  { key: 'events', label: 'risk.tab_events' },
  { key: 'rules', label: 'risk.tab_rules' },
  { key: 'clusters', label: 'risk.tab_clusters' },
  { key: 'ip', label: 'risk.tab_ip' },
  { key: 'devices', label: 'risk.tab_devices' },
  { key: 'anticheat', label: 'risk.tab_anticheat' },
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
    user_id: 'risk.head.user',
    username: 'risk.head.username',
    score: 'risk.head.score',
    band: 'risk.head.band',
    hit_count: 'risk.head.hits',
    last_hit_at: 'risk.head.last_hit',
    whitelisted: 'risk.head.whitelisted',
  },
  events: {
    id: 'risk.head.event',
    user_id: 'risk.head.user',
    rule_name: 'risk.head.rule',
    type: 'risk.head.type',
    action: 'risk.head.action',
    result: 'risk.head.result',
    ip_masked: 'risk.head.ip',
    fp_masked: 'risk.head.device',
    created_at: 'risk.head.time',
  },
  rules: {
    id: 'ID',
    name: 'risk.head.rule_name',
    type: 'risk.head.type',
    scope: 'risk.head.scope',
    action: 'risk.head.action',
    priority: 'risk.head.priority',
    status: 'risk.head.status',
  },
  clusters: {
    id: 'ID',
    name: 'risk.head.cluster',
    type: 'risk.head.type',
    fingerprint_masked: 'risk.head.fingerprint',
    user_count: 'risk.head.accounts_count',
    status_label: 'risk.head.status',
    updated_at: 'risk.head.updated',
  },
  ip: {
    ip_masked: 'risk.head.ip_hash',
    reputation_score: 'risk.head.reputation',
    source: 'risk.head.source',
    hit_count: 'risk.head.hits',
    last_seen_at: 'risk.head.last_seen',
  },
  devices: {
    fp_masked: 'risk.head.fp',
    ip_c_segment: 'risk.head.ip_c',
    account_count: 'risk.head.accounts',
    blocked: 'risk.head.blocked',
    last_seen_at: 'risk.head.last_seen',
  },
  anticheat: {
    id: 'risk.head.event',
    user_id: 'risk.head.user',
    game_id: 'risk.head.game',
    rule_type: 'risk.head.rule',
    severity: 'risk.head.severity',
    action: 'risk.head.action',
    status: 'risk.head.status',
    review_note: 'risk.head.review_note',
    created_at: 'risk.head.time',
  },
};

/** IP 四个动作的回执动词（端点同一个形状：POST /risk/ip/{key} `{ip}`） */
export const IP_VERBS: Record<string, string> = {
  block: 'risk.ip.verb.block',
  whitelist: 'risk.ip.verb.whitelist',
  appeal: 'risk.ip.verb.appeal',
  recheck: 'risk.ip.verb.recheck',
};

/**
 * 四个**只读**抽屉：动作键 → 面板类型 + 取数路径（路径里的 id 一律是**出边界的 hashid**）。
 * 四条都是 GET、都在选中行的上下文里、都复用那一个抽屉槽：
 *  - members  GET /risk/clusters/{hashid}/members —— 团伙成员（列表只有账号数，成员要点开看）
 *  - timeline GET /risk/users/{hashid}/timeline —— 风控/对局/反作弊三源合并，服务端按时间倒序截 200 条
 *  - graph    GET /risk/graph/{hashid}          —— 两跳闭包（同设备账号 → 其设备上的账号）
 *  - detail   GET /anticheat/events/{hashid}    —— 比列表多一个 user_trust（信任分/档位/命中数）
 * 前两条各摊平成一列行，detail 是结构化对象 ⇒ 走结果面板的原始响应（与沙箱试算同一个呈现），
 * graph 的两份响应形状不同 ⇒ 由 risk-graph 组件按 mode 自己呈现。
 */
export const READ_PANELS: Record<string, { panel: PanelKind; path: (id: string) => string }> = {
  members: { panel: 'members', path: (id) => 'risk/clusters/' + id + '/members' },
  timeline: { panel: 'timeline', path: (id) => 'risk/users/' + id + '/timeline' },
  graph: { panel: 'graph', path: (id) => 'risk/graph/' + id },
  detail: { panel: 'result', path: (id) => 'anticheat/events/' + id },
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

/**
 * 抽屉标题：与 risk.ts 的 panel() 一一对应（试算 / 候选 / 团伙成员 / 风险时间轴 / 图谱 / 设备关联簇）。
 * 后两个是同一份端点族（/risk/graph/*）的两个抽屉：一个以用户为根、一个没有根（全站高账号数设备）。
 */
export const PANEL_TITLES: Record<string, string> = {
  result: 'risk.panel.result',
  candidates: 'risk.panel.candidates',
  members: 'risk.panel.members',
  timeline: 'risk.user.timeline',
  graph: 'risk.graph.title',
  graphclusters: 'risk.graph.clusters',
};

/** 聚类候选抽屉：候选字段 = detect 的 candidates[]（fingerprint 是完整哈希，掩码列只用于显示） */
export const CANDIDATE_HEADS: Record<string, string> = {
  type: 'risk.head.type',
  fingerprint_masked: 'risk.head.fingerprint',
  user_count: 'risk.head.accounts_count',
};

export const CANDIDATE_ACTS: Act[] = [{ key: 'confirm', label: 'risk.cluster.confirm_candidate' }];

/**
 * 二次确认文案里的对象标识：**必须能认出是谁**（只有一个 hashid 等于没告诉人）。
 * 三种行的字段名不同：事件行给 rule_name，风险用户行给 username，反作弊行给 rule_name/rule_type。
 */
export function whoEvent(row: Row): string {
  const rule = String(row['rule_name'] ?? '');
  const user = String(row['user_id'] ?? '');
  return (
    [rule && t('risk.who.rule', { name: rule }), user && t('risk.who.user', { id: user })]
      .filter(Boolean)
      .join(' / ') || idOf(row)
  );
}

export function whoUser(row: Row): string {
  const name = String(row['username'] ?? '');
  const id = String(row['user_id'] ?? '');
  return name ? t('risk.who.user_id', { name, id }) : id || idOf(row);
}

export function whoAnti(row: Row): string {
  const rule = String(row['rule_name'] ?? '') || String(row['rule_type'] ?? '');
  const user = String(row['user_id'] ?? '');
  return (
    [rule && t('risk.who.rule', { name: rule }), user && t('risk.who.user', { id: user })]
      .filter(Boolean)
      .join(' / ') || idOf(row)
  );
}
