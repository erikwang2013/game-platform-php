/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 风控域词条（risk.ts / risk-fields.ts）。
 *
 * 与 `dict/frame.ts` 同一套约定：`键: [英文, 中文]`、占位符 `{name}`、查不到回落英文。
 * **中文一侧逐字等于抽取前的界面原文**（本批只做抽取、不动文案），英文沿用
 * `admin/apps/flutter` 的 translations.dart 里同名键的说法；键名能对上的就直接同名（risk.tab_*、
 * risk.rule.*、risk.ip.*、risk.cluster.*），对不上的按本树的划分另起（risk.head.* 是表头，
 * risk.rule.* 那一组是表单标签 —— 同一个中文词在两处是两回事，不合并）。
 */

/** 规则 config 的键表：中文一侧逐字来自抽取前 risk-fields.ts 的 CONFIG_HINT */
const KEYS_NOTE_ZH = [
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
];

const KEYS_NOTE_EN = [
  'config must be a JSON object; which keys it takes depends on type (server whitelist - one extra key is a 422):',
  'ip_blacklist: blacklist (string array, e.g. ["1.2.3.4"])',
  'amount_anomaly: min_amount (>0), currency (<=10 chars)',
  'frequency: window_minutes (1-10080), max_count (1-100000)',
  'velocity: window_minutes, max_accounts, same_ip (true/false)',
  'device_fingerprint: max_accounts_per_device, new_device_lookback_hours (1-8760), new_device_withdraw_block (true/false)',
  'ip_reputation: block_score_below (0-100), warn_score_below (0-100), block_unknown (true/false)',
  'device_account_graph: cluster_threshold, max_accounts_per_device, frozen_sibling_block (true/false)',
  'withdraw_pattern: window_minutes, max_applies, single_hard_cap (>0), drain_ratio ((0,1]), sigma_window_days (2-3650), sigma_multiplier (1-100), fast_interval_seconds (1-86400), fast_interval_min_count',
  'Integer keys without a stated range are 1-100000; boolean keys need real JSON true/false (the string "false" counts as true).',
  'A threshold of 0 makes the check always match and trips the whole platform (top-ups included), so the server pins the integer lower bound at 1.',
];

export const RISK: Record<string, [string, string]> = {
  // ---- 页头 / 标签页 ----
  'risk.title': ['Risk Control', '风险控制'],
  'risk.subtitle': [
    'Overview / Events / Rules / Clusters / IP / Devices / Anti-Cheat',
    '风控总览 / 事件 / 规则 / 团伙 / IP / 设备 / 反作弊',
  ],
  'risk.search_placeholder': ['User ID / rule name / device', '用户 ID / 规则名 / 设备'],
  'risk.tab_overview': ['Overview', '风控总览'],
  'risk.tab_users': ['Flagged Users', '风险用户'],
  'risk.tab_events': ['Events', '风险事件'],
  'risk.tab_rules': ['Rules', '风控规则'],
  'risk.tab_clusters': ['Clusters', '关联团伙'],
  'risk.tab_ip': ['IP Reputation', 'IP 信誉'],
  'risk.tab_devices': ['Devices', '设备'],
  'risk.tab_anticheat': ['Anti-Cheat', '反作弊'],
  'risk.overview_empty': ['No overview data yet', '风控总览暂无数据'],

  /** 增删改底座的模块名（crud.noun），拼进「新建{name}」「确认删除「{name}」」 */
  'risk.noun.rule': ['Risk rule', '风控规则'],
  'risk.noun.event': ['Risk event', '风险事件'],
  'risk.noun.user': ['Flagged user', '风险用户'],
  'risk.noun.cluster': ['Cluster', '团伙'],
  'risk.noun.anticheat': ['Anti-cheat event', '反作弊事件'],
  'risk.noun.device': ['Device fingerprint', '设备指纹'],

  // ---- 规则表单（RULE_FIELDS / RULE_ACTS）----
  'risk.rule.name': ['Rule name', '规则名'],
  'risk.rule.name_hint': [
    'Shows up in the evaluator log and the hit records',
    '评估器日志与命中记录里回显的就是它',
  ],
  'risk.rule.type': ['Type', '类型'],
  'risk.rule.action': ['Handling', '命中处置'],
  'risk.rule.scope': ['Scope', '作用域'],
  'risk.rule.priority': ['Priority', '优先级'],
  'risk.rule.priority_hint': [
    '0-1000, higher is evaluated first (default 100)',
    '0-1000，越大越先判（缺省 100）',
  ],
  'risk.rule.status': ['Status', '状态'],
  'risk.rule.config': ['Threshold config (JSON)', '阈值配置（JSON）'],
  'risk.rule.keys_note': [KEYS_NOTE_EN.join('\n'), KEYS_NOTE_ZH.join('\n')],
  'risk.rule.test': ['Sandbox test', '试算'],
  'risk.rule.create': ['Create rule', '新建规则'],
  'risk.rule.edit': ['Edit rule', '编辑规则'],

  'risk.action.log': ['log (audit only)', 'log（只记日志）'],
  'risk.action.warn': ['warn (alert)', 'warn（告警）'],
  'risk.action.block': ['block (intercept)', 'block（拦截）'],
  'risk.scope.all': ['all (every scenario)', 'all（全部场景）'],
  'risk.scope.deposit': ['deposit (top-up)', 'deposit（充值）'],
  'risk.scope.withdraw': ['withdraw (payout)', 'withdraw（提现）'],
  'risk.scope.exchange': ['exchange', 'exchange（兑换）'],
  'risk.scope.login': ['login', 'login（登录）'],

  // ---- 沙箱试算 ----
  'risk.sandbox.user_prompt': [
    'Sandbox user ID (hashid, empty = anonymous)',
    '试算用户 ID（hashid，可留空 = 未登录）',
  ],
  'risk.sandbox.check_type_prompt': [
    'Check type (deposit / withdraw / exchange / login)',
    '检测场景（deposit / withdraw / exchange / login）',
  ],
  'risk.sandbox.context_prompt': [
    'context JSON (optional, e.g. {"amount":"1000","ip":"1.2.3.4"})',
    'context JSON（可选，如 {"amount":"1000","ip":"1.2.3.4"}）',
  ],
  'risk.sandbox.context_invalid': [
    'context must be a JSON object (e.g. {"amount":"1000"})',
    'context 必须是 JSON 对象（形如 {"amount":"1000"}）',
  ],

  // ---- 风险事件处置 ----
  'risk.event.approve': ['Approve risk', '确认风险'],
  'risk.event.reject': ['Reject', '驳回'],
  'risk.event.reject_confirm': ['Reject risk event "{name}"?', '确认驳回「{name}」这条风险事件？'],
  'risk.event.note_prompt': [
    'Handling note (optional, up to 500 characters)',
    '处置说明（可留空，最长 500 字）',
  ],
  'risk.event.marked': ['Handling recorded', '处置已记录'],

  // ---- 异常用户（钱路）----
  'risk.user.hold': ['Freeze', '冻结'],
  'risk.user.release': ['Unfreeze', '解冻'],
  'risk.user.hold_confirm': [
    'Freeze the entire available balance of "{name}"? The amount is computed server-side from the current available balance; frozen funds cannot be withdrawn or spent.',
    '确认冻结「{name}」的全部可用余额？金额由服务端按当前可用余额全额计算，冻结期间不可提现/消费。',
  ],
  'risk.user.release_confirm': [
    'Unfreeze the frozen funds of "{name}"?',
    '确认解冻「{name}」的冻结资金？',
  ],
  'risk.user.amount_prompt': [
    'Amount to unfreeze (empty = everything)',
    '解冻金额（留空 = 全额解冻）',
  ],
  'risk.user.hold_done': ['Frozen {amount}', '已冻结 {amount}'],
  'risk.user.release_done': ['Released {amount}', '已解冻 {amount}'],

  // ---- 关联团伙 ----
  'risk.cluster.act.0': ['Mark false positive', '标记误判'],
  'risk.cluster.act.1': ['Watching', '观察中'],
  'risk.cluster.act.2': ['Handled', '已处置'],
  'risk.cluster.members': ['Members', '成员'],
  'risk.cluster.status.0': ['False positive', '误判'],
  'risk.cluster.status.1': ['Watching', '观察中'],
  'risk.cluster.status.2': ['Handled', '已处置'],
  'risk.cluster.marked': ['Cluster "{name}" marked as {status}', '团伙「{name}」已标记为{status}'],
  'risk.cluster.detect': ['Detect clusters', '聚类检测'],
  'risk.cluster.confirm_candidate': ['Confirm as cluster', '确认为团伙'],
  'risk.cluster.name_prompt': [
    'Cluster name (required, up to 100 characters)',
    '团伙名称（必填，最长 100 字）',
  ],
  'risk.cluster.created': ['Cluster "{name}" created', '已建团伙「{name}」'],
  'risk.panel.result': ['Rule sandbox (read-only, nothing written)', '规则试算（只读，未写库）'],
  'risk.panel.candidates': ['Cluster candidates (not persisted)', '聚类候选（未落库）'],
  'risk.panel.members': ['Cluster members (read-only)', '团伙成员（只读）'],

  // ---- 设备 ----
  'risk.device.block': ['Block', '拉黑'],
  'risk.device.unblock': ['Unblock', '解封'],
  'risk.device.block_confirm': [
    'Block device "{fp}"? Top-ups and payouts on this fingerprint will be refused outright (auto-unblocked after 30 days).',
    '确认拉黑设备「{fp}」？该指纹上的账号充值/提现将直接被拒（30 天后自动解封）。',
  ],
  'risk.device.blocked_note': ['Blocked device {fp}', '已拉黑设备 {fp}'],
  'risk.device.unblocked_note': ['Unblocked device {fp}', '已解封设备 {fp}'],

  // ---- 反作弊 ----
  'risk.ac.status.open': ['Pending review', '待审核'],
  'risk.ac.status.confirmed': ['Confirmed cheating', '确认作弊'],
  'risk.ac.status.whitelisted': ['Whitelisted', '白名单'],
  'risk.ac.status.closed': ['Closed', '关闭'],
  'risk.ac.marked': ['Marked as {status}', '已标记为{status}'],
  'risk.ac.confirm': [
    'Confirm "{name}" as cheating and record the review?',
    '确认「{name}」作弊并记入审核？',
  ],
  'risk.ac.note_prompt': [
    'Whitelist reason (recorded in the review note, up to 255 characters)',
    '加白理由（记入审核备注，最长 255 字）',
  ],

  // ---- IP 信誉 ----
  'risk.ip.block': ['Block IP', '拉黑 IP'],
  'risk.ip.whitelist': ['Add to whitelist', '加入白名单'],
  'risk.ip.appeal': ['Appeal pass', '误判申诉放行'],
  'risk.ip.recheck': ['Recheck', '重查'],
  'risk.ip.verb.block': ['Blocked', '已拉黑'],
  'risk.ip.verb.whitelist': ['Added to whitelist', '已加入白名单'],
  'risk.ip.verb.appeal': ['Passed on appeal', '已申诉放行'],
  'risk.ip.verb.recheck': ['Reputation cache refreshed', '已刷新信誉缓存'],
  'risk.ip.verb.done': ['Submitted', '已提交'],
  'risk.ip.prompt': ['IP address (e.g. 1.2.3.4)', 'IP 地址（如 1.2.3.4）'],
  'risk.ip.recheck_prompt': ['IP to recheck', '要重查的 IP'],

  // ---- 二次确认里的对象标识（whoEvent / whoUser / whoAnti）----
  'risk.who.rule': ['Rule "{name}"', '规则「{name}」'],
  'risk.who.user': ['User {id}', '用户 {id}'],
  'risk.who.user_id': ['{name} ({id})', '{name}（{id}）'],

  /**
   * 表头（RISK_HEADS / CANDIDATE_HEADS）。键名一律 `risk.head.*`：表头是「列名」，
   * 与 `risk.rule.*` 那组「表单标签」分开 —— 同一个中文词（如「状态」）在两处的英文口径不同。
   */
  'risk.head.user': ['User', '用户'],
  'risk.head.username': ['Username', '用户名'],
  'risk.head.score': ['Trust score', '信任分'],
  'risk.head.band': ['Band', '档位'],
  'risk.head.hits': ['Hits', '命中次数'],
  'risk.head.last_hit': ['Last hit', '最近命中'],
  'risk.head.whitelisted': ['Whitelisted', '白名单'],
  'risk.head.event': ['Event', '事件'],
  'risk.head.rule': ['Rule', '规则'],
  'risk.head.type': ['Type', '类型'],
  'risk.head.action': ['Action', '处置'],
  'risk.head.result': ['Result', '结果'],
  'risk.head.ip': ['IP', 'IP'],
  'risk.head.device': ['Device', '设备'],
  'risk.head.time': ['Time', '时间'],
  'risk.head.rule_name': ['Rule name', '规则名'],
  'risk.head.scope': ['Scope', '作用域'],
  'risk.head.priority': ['Priority', '优先级'],
  'risk.head.status': ['Status', '状态'],
  'risk.head.cluster': ['Cluster', '团伙'],
  'risk.head.fingerprint': ['Fingerprint', '指纹'],
  'risk.head.accounts_count': ['Accounts', '账号数'],
  'risk.head.updated': ['Updated', '更新时间'],
  'risk.head.ip_hash': ['IP hash', 'IP 哈希'],
  'risk.head.reputation': ['Reputation', '信誉分'],
  'risk.head.source': ['Source', '来源'],
  'risk.head.last_seen': ['Last seen', '最近出现'],
  'risk.head.fp': ['Device fingerprint', '设备指纹'],
  'risk.head.ip_c': ['IP C-segment', 'C 段'],
  'risk.head.accounts': ['Linked accounts', '关联账号'],
  'risk.head.blocked': ['Blocked', '已拉黑'],
  'risk.head.game': ['Game', '游戏'],
  'risk.head.severity': ['Severity', '严重度'],
  'risk.head.review_note': ['Review note', '审核备注'],
};
