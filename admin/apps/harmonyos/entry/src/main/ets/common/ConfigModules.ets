/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 「配置」四个模块的字段描述（批次 2）：VIP 等级 / 排行榜 / 国家配置 / 系统配置。
 * 契约与共用小工具见 ResourceSchema.ets；字段值域以控制器 validator 与列定义为真值。
 */

import { FormField } from './FormDialog';
import {
  FormValues,
  ResourceRow,
  ResourceListResult,
  VipLevelItem,
  LeaderboardItem,
  CountryConfigItem,
  PlatformConfigItem,
  pageQuery
} from '../model/DataModels';
import { ResourceModule, buildRow, unpaged, paged } from './ResourceSchema';
import { t } from '../i18n/Translations';

/* =============================== 5 VIP 等级 ===============================
 * create: level*(≥0) name*(≤50) required_exp*(≥0) benefits*(JSON 对象)
 * update: name required_exp benefits（level 不可改；删除前会拦「该等级下有用户」）
 * 无 status 列 ⇒ 没有状态变更
 * benefits 键白名单 exchange_discount / withdraw_fee_discount / rate_bonus，值都在 [0, 1]
 * ========================================================================== */

const VIP_FIELDS: FormField[] = [
  { name: 'level', label: 'vip.level', type: 'number', required: true, placeholder: 'vip.level_placeholder' },
  { name: 'name', label: 'vip.name', type: 'text', required: true, placeholder: 'vip.name_placeholder' },
  { name: 'required_exp', label: 'vip.required_exp', type: 'number', required: true, placeholder: 'vip.required_exp_placeholder' },
  {
    name: 'benefits', label: 'vip.benefits', type: 'textarea', required: true,
    placeholder: 'vip.benefits_placeholder'
  }
];

function vipFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < VIP_FIELDS.length; i++) {
    const field = VIP_FIELDS[i];
    if (isEdit && field.name === 'level') {
      fields.push({ name: 'level', label: 'vip.level', type: 'text', placeholder: 'common.immutable', disabled: true });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function vipInitial(): FormValues {
  const values: FormValues = {};
  values.level = '0';
  values.name = '';
  values.required_exp = '0';
  // 空对象合法（validateBenefits 放行 []），且比留空更明确「暂不配权益」
  values.benefits = '{}';
  return values;
}

function vipRow(item: VipLevelItem): ResourceRow {
  const values: FormValues = {};
  values.level = `${item.level}`;
  values.name = item.name;
  values.required_exp = `${item.required_exp}`;
  values.benefits = item.benefits ?? '';
  return buildRow(item.id, `VIP ${item.level} · ${item.name}`, `${t('vip.required_exp_short')} ${item.required_exp}`,
    '', true, 0, values);
}

const VIP: ResourceModule = {
  key: 'vip',
  title: 'mod.vip.title',
  desc: 'mod.vip.desc',
  base: '/admin/v1/vip/level',
  paginated: false,
  listPath: (_page: number, _limit: number, _gameId: string): string => '/admin/v1/vip/level/list',
  parseList: (data: Object): ResourceListResult => unpaged<VipLevelItem>(data, vipRow),
  fields: vipFields(false),
  editFields: vipFields(true),
  createInitial: vipInitial,
  statusMode: '',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: ''
};

/* ================================ 6 排行榜 ================================
 * create: name*(≤100) type*(daily|weekly|monthly|alltime) metric*(earned|spent|play_count) rule sort
 * update: name type metric rule status(0/1) sort；game_id 不在 only() 白名单 ⇒ 不进表单
 * 状态：无 toggle 端点 ⇒ 行内切换走 PUT {status}；另有 POST /leaderboard/{hashid}/refresh 清缓存重算
 * ========================================================================== */

const LEADERBOARD_FIELDS: FormField[] = [
  { name: 'name', label: 'leaderboard.name', type: 'text', required: true, placeholder: 'leaderboard.name_placeholder' },
  {
    name: 'type', label: 'leaderboard.type', type: 'select', required: true, options: [
      { label: 'leaderboard.type.daily', value: 'daily' },
      { label: 'leaderboard.type.weekly', value: 'weekly' },
      { label: 'leaderboard.type.monthly', value: 'monthly' },
      { label: 'leaderboard.type.alltime', value: 'alltime' }
    ]
  },
  {
    name: 'metric', label: 'leaderboard.metric', type: 'select', required: true, options: [
      { label: 'leaderboard.metric.earned', value: 'earned' },
      { label: 'leaderboard.metric.spent', value: 'spent' },
      { label: 'leaderboard.metric.play_count', value: 'play_count' }
    ]
  },
  { name: 'rule', label: 'leaderboard.rule', type: 'textarea', placeholder: 'leaderboard.rule_placeholder' },
  { name: 'sort', label: 'common.sort', type: 'number', placeholder: 'leaderboard.sort_placeholder' }
];

function leaderboardInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.type = 'daily';
  values.metric = 'earned';
  values.rule = '';
  values.sort = '0';
  return values;
}

function leaderboardRow(item: LeaderboardItem): ResourceRow {
  const values: FormValues = {};
  values.name = item.name;
  values.type = item.type;
  values.metric = item.metric;
  values.rule = item.rule ?? '';
  values.sort = `${item.sort}`;
  return buildRow(item.id, item.name, `${item.type} · ${item.metric} · ${t('common.sort')} ${item.sort}`,
    item.status === 1 ? t('common.enabled') : t('common.disabled'), item.status === 1, item.status, values);
}

const LEADERBOARD: ResourceModule = {
  key: 'leaderboard',
  title: 'mod.leaderboard.title',
  desc: 'mod.leaderboard.desc',
  base: '/admin/v1/leaderboard',
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/leaderboard/list?${pageQuery(page, limit)}`,
  parseList: (data: Object): ResourceListResult => paged<LeaderboardItem>(data, leaderboardRow),
  fields: LEADERBOARD_FIELDS,
  editFields: LEADERBOARD_FIELDS,
  createInitial: leaderboardInitial,
  statusMode: 'update',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: 'mod.leaderboard.action',
  extraPath: (id: string): string => `/admin/v1/leaderboard/${id}/refresh`
};

/* =============================== 7 国家配置 ===============================
 * create: country_code*(2 位) currency*(3 位) payment_methods(JSON 串) withdraw_methods(JSON 串) min_deposit
 * update: currency payment_methods withdraw_methods min_deposit(≥0) status(0/1)；country_code 不可改
 * 状态：POST /country/config/toggle（{id: hashid, status: 0/1}）
 * min_deposit 用文本字段而非数字：列是 DECIMAL 字符串，走 number 会被 JS 转成 float（金额禁 float）
 * ========================================================================== */

const COUNTRY_FIELDS: FormField[] = [
  { name: 'country_code', label: 'country_config.code', type: 'text', required: true, placeholder: 'country_config.code_placeholder' },
  { name: 'currency', label: 'country_config.currency', type: 'text', required: true, placeholder: 'country_config.currency_placeholder' },
  { name: 'payment_methods', label: 'country_config.payment_methods', type: 'textarea', placeholder: 'country_config.payment_methods_placeholder' },
  { name: 'withdraw_methods', label: 'country_config.withdraw_methods', type: 'textarea', placeholder: 'country_config.withdraw_methods_placeholder' },
  { name: 'min_deposit', label: 'country_config.min_deposit', type: 'text', placeholder: 'country_config.min_deposit_placeholder' }
];

function countryFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < COUNTRY_FIELDS.length; i++) {
    const field = COUNTRY_FIELDS[i];
    if (isEdit && field.name === 'country_code') {
      fields.push({ name: 'country_code', label: 'country_config.code', type: 'text', placeholder: 'common.immutable', disabled: true });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function countryInitial(): FormValues {
  const values: FormValues = {};
  values.country_code = '';
  values.currency = '';
  values.payment_methods = '[]';
  values.withdraw_methods = '[]';
  values.min_deposit = '1.0000';
  return values;
}

function countryRow(item: CountryConfigItem): ResourceRow {
  const values: FormValues = {};
  values.country_code = item.country_code;
  values.currency = item.currency;
  values.payment_methods = item.payment_methods ?? '';
  values.withdraw_methods = item.withdraw_methods ?? '';
  values.min_deposit = item.min_deposit ?? '';
  return buildRow(item.id, `${item.country_code} · ${item.currency}`, `${t('country_config.min_deposit_short')} ${item.min_deposit}`,
    item.status === 1 ? t('common.enabled') : t('common.disabled'), item.status === 1, item.status, values);
}

const COUNTRY: ResourceModule = {
  key: 'country',
  title: 'mod.country.title',
  desc: 'mod.country.desc',
  base: '/admin/v1/country/config',
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/country/config/list?${pageQuery(page, limit)}`,
  parseList: (data: Object): ResourceListResult => paged<CountryConfigItem>(data, countryRow),
  fields: countryFields(false),
  editFields: countryFields(true),
  createInitial: countryInitial,
  statusMode: 'toggle',
  togglePath: '/admin/v1/country/config/toggle',
  needsGame: false,
  deletePassword: false,
  extraText: ''
};

/* =============================== 8 系统配置 ===============================
 * create: group*(≤100) key*(≤100) value*(字符串) type description
 * update: value type description（group/key 不可改）
 * 删除要管理员密码二次确认（ConfigController::destroy → confirmPassword）
 * type 取值见 PlatformConfig::get 的 match：string/int/bool/json/decimal
 * 路由占位是 {id}，但控制器 update/destroy 都 decodeId ⇒ 路径段仍是列表里的 hashid
 * ========================================================================== */

const CONFIG_FIELDS: FormField[] = [
  { name: 'group', label: 'sysconfig.group', type: 'text', required: true, placeholder: 'sysconfig.group_placeholder' },
  { name: 'key', label: 'sysconfig.key', type: 'text', required: true, placeholder: 'sysconfig.key_placeholder' },
  { name: 'value', label: 'sysconfig.value', type: 'textarea', required: true, placeholder: 'sysconfig.value_placeholder' },
  {
    name: 'type', label: 'sysconfig.type', type: 'select', options: [
      { label: 'sysconfig.type.string', value: 'string' },
      { label: 'sysconfig.type.int', value: 'int' },
      { label: 'sysconfig.type.bool', value: 'bool' },
      { label: 'sysconfig.type.json', value: 'json' },
      { label: 'sysconfig.type.decimal', value: 'decimal' }
    ]
  },
  { name: 'description', label: 'sysconfig.description', type: 'text', placeholder: 'sysconfig.description_placeholder' }
];

function configFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < CONFIG_FIELDS.length; i++) {
    const field = CONFIG_FIELDS[i];
    if (isEdit && (field.name === 'group' || field.name === 'key')) {
      fields.push({ name: field.name, label: field.label, type: 'text', placeholder: 'common.immutable', disabled: true });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function configInitial(): FormValues {
  const values: FormValues = {};
  values.group = '';
  values.key = '';
  values.value = '';
  values.type = 'string';
  values.description = '';
  return values;
}

function configRow(item: PlatformConfigItem): ResourceRow {
  const values: FormValues = {};
  values.group = item.group;
  values.key = item.key;
  values.value = item.value ?? '';
  values.type = item.type ?? 'string';
  values.description = item.description ?? '';
  return buildRow(item.id, `${item.group}.${item.key}`, `${item.type ?? 'string'} · ${item.value ?? ''}`,
    '', true, 0, values);
}

const CONFIG: ResourceModule = {
  key: 'config',
  title: 'mod.config.title',
  desc: 'mod.config.desc',
  base: '/admin/v1/config',
  // 仅此模块的建在 POST base 本身（route.php:90 Route::post('/config')），其余模块都是 base + '/create'
  createPath: '/admin/v1/config',
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/config?${pageQuery(page, limit)}`,
  parseList: (data: Object): ResourceListResult => paged<PlatformConfigItem>(data, configRow),
  fields: configFields(false),
  editFields: configFields(true),
  createInitial: configInitial,
  statusMode: '',
  togglePath: '',
  needsGame: false,
  deletePassword: true,
  extraText: ''
};

/** 本组模块（注册表按此顺序拼装） */
export const CONFIG_MODULES: ResourceModule[] = [VIP, LEADERBOARD, COUNTRY, CONFIG];
