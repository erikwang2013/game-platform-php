/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 「风控」三个模块的字段描述（批次 5）：风控规则 / 风控事件 / 反作弊事件。
 * 契约与共用小工具见 ResourceSchema.ets；字段值域以控制器 validator 与列定义为真值：
 *   admin/app/admin/v1/controller/{RiskRule,RiskEvent}Controller.php、AntiCheatController.php、
 *   packages/platform-common/src/model/RiskRule.php（fillable/casts）
 *
 * 本册特有的五条口径（都会让「照抄别的模块」写出来的表单静默出错）：
 *  1. **列表分页参数是 `size`**（不是 limit），形状是 `{total, items}` ⇒ 走 pagedItems，不走 paged。
 *  2. **规则编辑是全量语义**：update 与 create 共用同一个 fill()，name/type/action 一律从请求体读且
 *     非空即 422 ⇒ fullEdit=true（编辑态发整张表单，不做局部更新）。
 *  3. **规则表单摆 status**（与其余模块相反），且编辑全量发出。这一条最初是踩出来的：fill() 末行原为
 *     `in_array((int)($data['status'] ?? 1), [0,1], true) ? (int) $data['status'] : 1` —— 条件用
 *     `?? 1` 判真、真分支又去读 `$data['status']`，键缺失时那个读是 **undefined ⇒ 0**（实测 `php -r`
 *     得 int(0) + 一条 Undefined array key 警告），配合上面的 fullEdit 就是「改个名字顺手把规则停用」。
 *     **服务端已改**（缺键 = 启用），故现在不带 status 也不会再停用；表单继续摆它是为了让运营看得见、
 *     改得动这个字段（它是真实列，且 create 时要有初值），不是因为不发会出事。
 *  4. **规则没有 DELETE 端点**（config/route.php 的风控组只有 list/create/update/toggle/test）⇒ canDelete=false。
 *  5. **两个事件模块是只读流水 + 一个处置动作**：没有 create/update/delete，只有 handle/review
 *     ⇒ canCreate/canEdit/canDelete 全 false，动作挂在 extraForm/extraSubmit 上。
 *
 * 不进本册的四处：设备指纹（同一行按 blocked 二选一出「拉黑/解封」，不是契约里的固定行内动作）、
 * IP 信誉（列表只回掩码而端点要**原 IP**，只能页面级手填）、异常用户（行标识是 user_id，
 * 且冻结/解冻是资金动作）、团伙（status 三态 + detect 是无行上下文的全局动作）
 * ⇒ 四个独立页，见 pages/RiskIndexPage.ets。
 */

import { FormField, FieldOption } from './FormDialog';
import { FormValues, FormPayload, ResourceRow, ResourceListResult } from '../model/DataModels';
import { RiskRuleItem, RiskEventItem, AntiCheatEventItem, RiskSandboxResult } from '../model/RiskModels';
import { ResourceModule, buildRow, pagedItems } from './ResourceSchema';
import { RiskService } from '../service/RiskService';

/* ============================== 1 风控规则 ==============================
 * list:   GET  /admin/v1/risk/rule/list?page=&size=（另有 status/type/scope 过滤，本页不挂）
 * create: POST /admin/v1/risk/rule/create —— name*(≤?) type*(8 枚举) action*(log|warn|block)
 *         scope(all|deposit|withdraw|exchange|login，缺省 all) config(JSON 对象文本)
 *         priority(0..1000，服务端 clamp 不报错) status(0/1，缺省 1)
 * update: PUT  /admin/v1/risk/rule/{hashid} —— **同一个 fill()**：name/type/action 缺一即 422
 * toggle: POST /admin/v1/risk/rule/{hashid}/toggle —— **无请求体**，服务端自己翻转、回 `{status}`
 * test:   POST /admin/v1/risk/rule/test —— 沙箱试算，rule_id/user_id 在 **body** 里（hashid），
 *         context 必须是**对象**（`is_array($context) ?: []`，发字符串会被静默当成空上下文）
 * ====================================================================== */

const RULE_TYPE_OPTIONS: FieldOption[] = [
  { label: 'IP 黑名单', value: 'ip_blacklist' },
  { label: '金额异常', value: 'amount_anomaly' },
  { label: '频率限制', value: 'frequency' },
  { label: '速度（同 IP 多账号）', value: 'velocity' },
  { label: '设备指纹', value: 'device_fingerprint' },
  { label: 'IP 信誉', value: 'ip_reputation' },
  { label: '设备账号图谱', value: 'device_account_graph' },
  { label: '提现模式', value: 'withdraw_pattern' }
];

const RULE_SCOPE_OPTIONS: FieldOption[] = [
  { label: '全部场景', value: 'all' },
  { label: '充值', value: 'deposit' },
  { label: '提现', value: 'withdraw' },
  { label: '兑换', value: 'exchange' },
  { label: '登录', value: 'login' }
];

/** 沙箱试算的 check_type：与 scope 同域，但**没有 all**（试算必须落在某个具体场景上） */
const CHECK_TYPE_OPTIONS: FieldOption[] = RULE_SCOPE_OPTIONS.slice(1);

const RULE_ACTION_OPTIONS: FieldOption[] = [
  { label: '仅记录', value: 'log' },
  { label: '告警', value: 'warn' },
  { label: '拦截', value: 'block' }
];

const RULE_STATUS_OPTIONS: FieldOption[] = [
  { label: '启用', value: '1' },
  { label: '停用', value: '0' }
];

/**
 * config 的键白名单与值域 —— **只提示，不在这里复刻校验**（真值在 RiskRuleController::
 * CONFIG_KEYS/INT_BOUNDS/BOOL_KEYS/MONEY_KEYS/RATIO_KEYS，服务端才是唯一闸门）。
 * 之所以要摆出来：评估器读的是 `$config['自己的键'] ?? 默认值`，写错键名不报错、只是**静默不生效**，
 * 运营会以为阈值设上了。这段提示让「有哪些键、什么值域」在填的时候就看得见。
 */
const CONFIG_HINT: string =
  'config 是一个 JSON 对象，键必须落在本 type 的白名单里（写错键名服务端 422）。\n'
    + 'ip_blacklist → blacklist（IP 字符串数组）\n'
    + 'amount_anomaly → min_amount（金额 >0）、currency（≤10 字符）\n'
    + 'frequency → window_minutes(1~10080)、max_count(1~100000)\n'
    + 'velocity → window_minutes(1~10080)、max_accounts(1~100000)、same_ip(布尔)\n'
    + 'device_fingerprint → max_accounts_per_device(1~100000)、new_device_lookback_hours(1~8760)、new_device_withdraw_block(布尔)\n'
    + 'ip_reputation → block_score_below(0~100)、warn_score_below(0~100)、block_unknown(布尔)\n'
    + 'device_account_graph → cluster_threshold(1~100000)、max_accounts_per_device(1~100000)、frozen_sibling_block(布尔)\n'
    + 'withdraw_pattern → window_minutes(1~10080)、max_applies(1~100000)、single_hard_cap(金额 >0)、'
    + 'drain_ratio(比率 0<x≤1)、sigma_window_days(2~3650)、sigma_multiplier(1~100)、'
    + 'fast_interval_seconds(1~86400)、fast_interval_min_count(1~100000)\n'
    + '布尔必须写 JSON 的 true/false —— 写成 "false" 字符串会被当成 true（评估器是 (bool) 取值）；\n'
    + '数值只收十进制字面量（1e3 会被 bccomp 拒掉）。';

const RULE_FIELDS: FormField[] = [
  { name: 'name', label: '规则名称', type: 'text', required: true, placeholder: '如「提现频率过高」' },
  { name: 'type', label: '规则类型', type: 'select', required: true, options: RULE_TYPE_OPTIONS },
  { name: 'scope', label: '生效场景', type: 'select', options: RULE_SCOPE_OPTIONS },
  { name: 'action', label: '命中处置', type: 'select', required: true, options: RULE_ACTION_OPTIONS },
  {
    name: 'config', label: '阈值配置（JSON）', type: 'textarea', hint: CONFIG_HINT,
    placeholder: '如 {"window_minutes":5,"max_count":10}；留空 = {} 用各键缺省值'
  },
  { name: 'priority', label: '优先级', type: 'number', placeholder: '0~1000，越大越先判，缺省 100' },
  // status 进表单：它是真实列、create 要有初值、编辑要看得见改得动（服务端已对缺键回落到启用，见册头第 3 条）
  { name: 'status', label: '状态', type: 'select', required: true, options: RULE_STATUS_OPTIONS }
];

function ruleInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.type = 'frequency';
  values.scope = 'all';
  values.action = 'log';
  values.config = '';
  values.priority = '100';
  values.status = '1';
  return values;
}

function ruleTypeLabel(type: string): string {
  for (let i = 0; i < RULE_TYPE_OPTIONS.length; i++) {
    if (RULE_TYPE_OPTIONS[i].value === type) {
      return RULE_TYPE_OPTIONS[i].label;
    }
  }
  return type;
}

function optionLabelOf(options: FieldOption[], value: string): string {
  for (let i = 0; i < options.length; i++) {
    if (options[i].value === value) {
      return options[i].label;
    }
  }
  return value;
}

function ruleRow(item: RiskRuleItem): ResourceRow {
  const values: FormValues = {};
  values.name = item.name;
  values.type = item.type;
  values.scope = item.scope;
  values.action = item.action;
  // config 是 TEXT 列、模型无 cast ⇒ 列表回传的就是运营当时输入的原文，可原样回填
  values.config = item.config ?? '';
  values.priority = `${item.priority}`;
  values.status = `${item.status}`;
  const config = item.config ?? '';
  const head = `${ruleTypeLabel(item.type)} · ${optionLabelOf(RULE_SCOPE_OPTIONS, item.scope)}`
    + ` · ${optionLabelOf(RULE_ACTION_OPTIONS, item.action)} · 优先级 ${item.priority}`;
  const sub = config.length > 0 ? `${head} · ${config}` : `${head} · 未配阈值`;
  return buildRow(item.id, item.name, sub,
    item.status === 1 ? '启用' : '停用', item.status === 1, item.status, values);
}

/** 试算结果 → 给运营看的几行（沙箱是只读评估，页面上说清「没写库、没触发处置」） */
function sandboxLines(result: RiskSandboxResult): string[] {
  const lines: string[] = [];
  lines.push(`命中：${result.matched ? '是' : '否'}`);
  lines.push(`严重度：${result.severity} · 折算后处置：${optionLabelOf(RULE_ACTION_OPTIONS, result.action)}（${result.action}）`);
  const said = result.message ?? '';
  lines.push(said.length > 0 ? `说明：${said}` : '说明：（服务端未给出命中说明）');
  lines.push('本试算只读：不写库、不落风控日志、不触发任何处置。');
  return lines;
}

async function submitRuleTest(id: string, body: FormPayload): Promise<string[]> {
  const result = await RiskService.testRule(
    id,
    `${body['user_id'] ?? ''}`,
    `${body['check_type'] ?? 'login'}`,
    `${body['context'] ?? ''}`
  );
  return sandboxLines(result);
}

const RISK_RULE: ResourceModule = {
  key: 'risk_rule',
  title: '风控规则',
  desc: '规则阈值与启停，可沙箱试算（无删除）',
  base: '/admin/v1/risk/rule',
  // 没有 DELETE 端点 ⇒ 不画删除按钮
  canDelete: false,
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/risk/rule/list?page=${page}&size=${limit}`,
  parseList: (data: Object): ResourceListResult => pagedItems<RiskRuleItem>(data, ruleRow),
  fields: RULE_FIELDS,
  // 编辑态字段与新建态一致：update 与 create 是同一个 fill()（全量语义）
  editFields: RULE_FIELDS,
  createInitial: ruleInitial,
  fullEdit: true,
  statusMode: 'toggle_bare',
  togglePath: '/admin/v1/risk/rule/{id}/toggle',
  needsGame: false,
  deletePassword: false,
  extraText: '试算',
  extraForm: [
    {
      name: 'user_id', label: '用户 hashid', type: 'text', required: true,
      placeholder: '取用户列表里的 user_id', hint: '试算按这个用户的历史算；服务端不校验它是否存在（编的 id 会当成一个没有历史的新用户）。'
    },
    { name: 'check_type', label: '校验场景', type: 'select', required: true, options: CHECK_TYPE_OPTIONS },
    {
      name: 'context', label: '上下文（JSON）', type: 'textarea',
      placeholder: '如 {"amount":"100.0000","ip":"1.2.3.4"}；可留空',
      hint: '必须是 JSON 对象：评估器只从对象里取 ip / user_agent / amount / fp_hash 四个键，发字符串会被静默当成空上下文。'
    }
  ],
  extraSubmit: submitRuleTest
};

/* ============================== 2 风控事件 ==============================
 * list:   GET /admin/v1/risk/event/list?page=&size=（另有 user_id/rule_id/action/type/时间 过滤）
 * handle: POST /admin/v1/risk/event/{hashid}/handle {decision: approve|reject, note: ≤500}
 * **没有 create/update/delete**：risk_log 是流水表，没有审核状态列 —— handle **也不写这张表**，
 * 只把处置记进 OperationLog 审计 ⇒ 列表刷新后行不会变，结果框里必须把这件事说清楚，
 * 否则运营会以为「处置完了事件状态就该变」。
 * ============================================================== */

const EVENT_ACTION_OPTIONS: FieldOption[] = [
  { label: '仅记录', value: 'log' },
  { label: '告警', value: 'warn' },
  { label: '拦截', value: 'block' }
];

const EVENT_DECISION_OPTIONS: FieldOption[] = [
  { label: '确认风险（approve）', value: 'approve' },
  { label: '判定误报（reject）', value: 'reject' }
];

function eventRow(item: RiskEventItem): ResourceRow {
  const values: FormValues = {};
  const ruleName = item.rule_name ?? '';
  const title = ruleName.length > 0 ? ruleName : (item.type ?? '');
  const detail = item.detail ?? '';
  const head = `${item.user_id} · ${item.action} · ${item.result} · ${item.ip_masked} · ${item.created_at}`;
  const sub = detail.length > 0 ? `${head} · ${detail}` : head;
  // 无状态列 ⇒ 不画切换按钮（statusMode=''），徽标用处置动作，恒绿
  return buildRow(item.id, title.length > 0 ? title : item.id, sub,
    optionLabelOf(EVENT_ACTION_OPTIONS, item.action), true, 0, values);
}

async function submitEventHandle(id: string, body: FormPayload): Promise<string[]> {
  return RiskService.handleEvent(id, `${body['decision'] ?? ''}`, `${body['note'] ?? ''}`);
}

const RISK_EVENT: ResourceModule = {
  key: 'risk_event',
  title: '风控事件',
  desc: '命中流水，可人工处置（只留痕，不写事件）',
  base: '/admin/v1/risk/event',
  canCreate: false,
  canEdit: false,
  canDelete: false,
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/risk/event/list?page=${page}&size=${limit}`,
  parseList: (data: Object): ResourceListResult => pagedItems<RiskEventItem>(data, eventRow),
  fields: [],
  editFields: [],
  createInitial: (): FormValues => {
    const values: FormValues = {};
    return values;
  },
  statusMode: '',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: '处置',
  extraForm: [
    { name: 'decision', label: '处置结论', type: 'select', required: true, options: EVENT_DECISION_OPTIONS },
    { name: 'note', label: '处置备注', type: 'textarea', placeholder: '最长 500 字，进操作审计' }
  ],
  extraSubmit: submitEventHandle
};

/* ============================ 3 反作弊事件 ============================
 * list:   GET /admin/v1/anticheat/events?page=&size=（另有 user_id/game_id/rule_type/status/时间）
 * review: POST /admin/v1/anticheat/events/{hashid}/review {status: 四值字符串枚举, note: ≤255}
 * 与风控事件不同，这一张**真写状态列**（update status/reviewer_id/review_note）⇒ 审核完列表会变。
 * 但 status 是**四值字符串枚举**（open/confirmed/whitelisted/closed），不是二态 ⇒ 不给行内翻转按钮。
 * 没有 create/update/delete。
 * ==================================================================== */

const CHEAT_STATUS_OPTIONS: FieldOption[] = [
  { label: '待处理（open）', value: 'open' },
  { label: '确认作弊（confirmed）', value: 'confirmed' },
  { label: '加白放行（whitelisted）', value: 'whitelisted' },
  { label: '关闭（closed）', value: 'closed' }
];

function cheatStatusLabel(status: string): string {
  for (let i = 0; i < CHEAT_STATUS_OPTIONS.length; i++) {
    if (CHEAT_STATUS_OPTIONS[i].value === status) {
      return CHEAT_STATUS_OPTIONS[i].label;
    }
  }
  return status;
}

function cheatRow(item: AntiCheatEventItem): ResourceRow {
  const values: FormValues = {};
  const ruleName = item.rule_name ?? '';
  const title = ruleName.length > 0 ? ruleName : (item.rule_type ?? '');
  const note = item.review_note ?? '';
  const head = `${item.user_id} · ${item.action} · 严重度 ${item.severity} · 扣分 ${item.score_delta}`
    + ` · ${item.stat_date} · ${item.created_at}`;
  const sub = note.length > 0 ? `${head} · 备注：${note}` : head;
  return buildRow(item.id, title.length > 0 ? title : item.id, sub,
    cheatStatusLabel(item.status), item.status === 'whitelisted', 0, values);
}

async function submitCheatReview(id: string, body: FormPayload): Promise<string[]> {
  return RiskService.reviewAntiCheat(id, `${body['status'] ?? ''}`, `${body['note'] ?? ''}`);
}

const ANTI_CHEAT_EVENT: ResourceModule = {
  key: 'anticheat_event',
  title: '反作弊事件',
  desc: '作弊事件人工审核（四值结论，无增删改）',
  base: '/admin/v1/anticheat/events',
  canCreate: false,
  canEdit: false,
  canDelete: false,
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/anticheat/events?page=${page}&size=${limit}`,
  parseList: (data: Object): ResourceListResult => pagedItems<AntiCheatEventItem>(data, cheatRow),
  fields: [],
  editFields: [],
  createInitial: (): FormValues => {
    const values: FormValues = {};
    return values;
  },
  statusMode: '',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: '审核',
  extraForm: [
    {
      name: 'status', label: '审核结论', type: 'select', required: true, options: CHEAT_STATUS_OPTIONS,
      hint: 'whitelisted 需附备注；结论只是记事件，不联动信任分（控制器原话）。'
    },
    { name: 'note', label: '审核备注', type: 'textarea', placeholder: '最长 255 字' }
  ],
  extraSubmit: submitCheatReview
};

/** 本组模块（注册表按此顺序拼装） */
export const RISK_MODULES: ResourceModule[] = [RISK_RULE, RISK_EVENT, ANTI_CHEAT_EVENT];
