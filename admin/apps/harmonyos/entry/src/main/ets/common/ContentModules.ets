/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 「内容」四个模块的字段描述（批次 2）：游戏分类 / 游戏区服 / 运营活动 / 成就。
 * 契约与共用小工具见 ResourceSchema.ets；字段值域以控制器 validator 与列定义为真值。
 */

import { FormField, FieldOption } from './FormDialog';
import {
  FormValues,
  ResourceRow,
  ResourceListResult,
  GameCategoryItem,
  GameServerItem,
  ActivityItem,
  AchievementItem,
  pageQuery
} from '../model/DataModels';
import { ResourceModule, buildRow, unpaged, paged, jsonText } from './ResourceSchema';
import { t } from '../i18n/Translations';

/* ================================ 1 游戏分类 ================================
 * create: name*(≤50) slug*(≤50, ^[a-z0-9_-]+$) icon sort（status 由后端强制 1）
 * update: name icon sort status(0/1)；slug 不在 only() 白名单 ⇒ 创建后不可改
 * 状态：无 toggle 端点 ⇒ 行内切换走 PUT {status}
 * ========================================================================== */

const CATEGORY_FIELDS: FormField[] = [
  { name: 'name', label: 'category.name', type: 'text', required: true, placeholder: 'category.name_placeholder' },
  { name: 'slug', label: 'category.slug', type: 'text', required: true, placeholder: 'category.slug_placeholder' },
  {
    name: 'icon', label: 'category.icon', type: 'image', placeholder: 'category.icon_placeholder',
    hint: 'category.icon_hint'
  },
  { name: 'sort', label: 'common.sort', type: 'number', placeholder: 'category.sort_placeholder' }
];

function categoryFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < CATEGORY_FIELDS.length; i++) {
    const field = CATEGORY_FIELDS[i];
    if (isEdit && field.name === 'slug') {
      fields.push({ name: 'slug', label: 'category.slug', type: 'text', placeholder: 'common.immutable', disabled: true });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function categoryInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.slug = '';
  values.icon = '';
  values.sort = '0';
  return values;
}

function categoryRow(item: GameCategoryItem): ResourceRow {
  const values: FormValues = {};
  values.name = item.name;
  values.slug = item.slug;
  values.icon = item.icon ?? '';
  values.sort = `${item.sort}`;
  return buildRow(item.id, item.name, `${item.slug} · ${t('common.sort')} ${item.sort}`,
    item.status === 1 ? t('common.enabled') : t('common.disabled'), item.status === 1, item.status, values);
}

const CATEGORY: ResourceModule = {
  key: 'category',
  title: 'mod.category.title',
  desc: 'mod.category.desc',
  base: '/admin/v1/game/category',
  paginated: false,
  listPath: (_page: number, _limit: number, _gameId: string): string => '/admin/v1/game/category/list',
  parseList: (data: Object): ResourceListResult => unpaged<GameCategoryItem>(data, categoryRow),
  fields: categoryFields(false),
  editFields: categoryFields(true),
  createInitial: categoryInitial,
  statusMode: 'update',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: ''
};

/* ================================ 2 游戏区服 ================================
 * 列表与新建都挂在某个游戏下（list 的 game_id 是必填 hashid）⇒ 页面顶部先选游戏。
 * create: game_id*(hashid，由页面注入) name*(≤50) region(≤20) status(0..3) sort
 * update: name region status(0..3) sort
 * 状态四态 ⇒ 不给行内切换按钮，走表单下拉（一个二态按钮表达不了四态）
 * ========================================================================== */

const SERVER_STATUS_OPTIONS: FieldOption[] = [
  { label: 'server.status.maintenance', value: '0' },
  { label: 'server.status.normal', value: '1' },
  { label: 'server.status.hot', value: '2' },
  { label: 'server.status.new', value: '3' }
];

const SERVER_FIELDS: FormField[] = [
  { name: 'name', label: 'server.name', type: 'text', required: true, placeholder: 'server.name_placeholder' },
  { name: 'region', label: 'server.region', type: 'text', placeholder: 'server.region_placeholder' },
  { name: 'status', label: 'server.status', type: 'select', required: true, options: SERVER_STATUS_OPTIONS },
  { name: 'sort', label: 'common.sort', type: 'number', placeholder: 'server.sort_placeholder' }
];

function serverInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.region = '';
  values.status = '1';
  values.sort = '0';
  return values;
}

function serverStatusText(status: number): string {
  for (let i = 0; i < SERVER_STATUS_OPTIONS.length; i++) {
    if (SERVER_STATUS_OPTIONS[i].value === `${status}`) {
      return t(SERVER_STATUS_OPTIONS[i].label);
    }
  }
  return t('server.unknown');
}

function serverRow(item: GameServerItem): ResourceRow {
  const values: FormValues = {};
  values.name = item.name;
  values.region = item.region ?? '';
  values.status = `${item.status}`;
  values.sort = `${item.sort}`;
  return buildRow(item.id, item.name, `${item.region ?? ''} · ${t('common.sort')} ${item.sort}`,
    serverStatusText(item.status), item.status === 1, item.status, values);
}

const SERVER: ResourceModule = {
  key: 'server',
  title: 'mod.server.title',
  desc: 'mod.server.desc',
  base: '/admin/v1/game/server',
  paginated: false,
  listPath: (_page: number, _limit: number, gameId: string): string =>
    `/admin/v1/game/server/list?game_id=${encodeURIComponent(gameId)}`,
  parseList: (data: Object): ResourceListResult => {
    // 这个端点的 data 是裸数组，不是 {list}
    const list = data as GameServerItem[];
    const rows: ResourceRow[] = [];
    for (let i = 0; i < list.length; i++) {
      rows.push(serverRow(list[i]));
    }
    const result: ResourceListResult = { rows: rows, total: rows.length };
    return result;
  },
  fields: SERVER_FIELDS,
  editFields: SERVER_FIELDS,
  createInitial: serverInitial,
  statusMode: '',
  togglePath: '',
  needsGame: true,
  deletePassword: false,
  extraText: ''
};

/* ================================ 3 运营活动 ================================
 * create: type*(signin|daily_task|invite) name*(≤100) status*(0/1/2) rollout_percent(0..100) config(JSON)
 * update: name config status start_at end_at rollout_percent；type 不可改
 * config 按 type 校验（ActivityController::parseConfig）：空串 = 用 handler 默认配置；
 *   signin {rewards:[{day,reward:{type,amount}}]} / daily_task {tasks:[{event,target,reward}]} /
 *   invite {target,rewards:[...]}；reward.type 只认 platform_coin / game_coin，amount 是十进制串。
 * 状态三态（0 停用/1 启用/2 已结束）⇒ 不给行内切换，走表单下拉。
 * 未纳入表单：game_id（create 的规则是 integer、列是裸 BIGINT，前端拿不到裸 id）、
 *   start_at/end_at（要日期控件，不传即不动，与公告页同一取舍）。
 * ========================================================================== */

const ACTIVITY_FIELDS: FormField[] = [
  {
    name: 'type', label: 'activity.type', type: 'select', required: true, options: [
      { label: 'activity.type.signin', value: 'signin' },
      { label: 'activity.type.daily_task', value: 'daily_task' },
      { label: 'activity.type.invite', value: 'invite' }
    ]
  },
  { name: 'name', label: 'activity.name', type: 'text', required: true, placeholder: 'activity.name_placeholder' },
  {
    name: 'status', label: 'activity.status', type: 'select', required: true, options: [
      { label: 'common.disabled', value: '0' },
      { label: 'common.enabled', value: '1' },
      { label: 'activity.status.ended', value: '2' }
    ]
  },
  { name: 'rollout_percent', label: 'activity.rollout_percent', type: 'number', placeholder: 'activity.rollout_percent_placeholder' },
  {
    name: 'config', label: 'activity.config', type: 'textarea',
    placeholder: 'activity.config_placeholder'
  }
];

function activityFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < ACTIVITY_FIELDS.length; i++) {
    const field = ACTIVITY_FIELDS[i];
    if (isEdit && field.name === 'type') {
      fields.push({ name: 'type', label: 'activity.type', type: 'text', placeholder: 'common.immutable', disabled: true });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function activityInitial(): FormValues {
  const values: FormValues = {};
  values.type = 'signin';
  values.name = '';
  // 缺省停用：活动一旦启用就会真发奖，建完复核再开
  values.status = '0';
  values.rollout_percent = '100';
  values.config = '';
  return values;
}

function activityStatusText(status: number): string {
  if (status === 1) {
    return t('common.enabled');
  }
  return status === 2 ? t('activity.status.ended') : t('common.disabled');
}

function activityRow(item: ActivityItem): ResourceRow {
  const values: FormValues = {};
  values.type = item.type;
  values.name = item.name;
  values.status = `${item.status}`;
  values.rollout_percent = `${item.rollout_percent}`;
  values.config = jsonText(item.config);
  return buildRow(item.id, item.name, `${item.type} · ${t('activity.rollout_short')} ${item.rollout_percent}%`,
    activityStatusText(item.status), item.status === 1, item.status, values);
}

const ACTIVITY: ResourceModule = {
  key: 'activity',
  title: 'mod.activity.title',
  desc: 'mod.activity.desc',
  base: '/admin/v1/activities',
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/activities/list?${pageQuery(page, limit)}`,
  parseList: (data: Object): ResourceListResult => paged<ActivityItem>(data, activityRow),
  fields: activityFields(false),
  editFields: activityFields(true),
  createInitial: activityInitial,
  statusMode: '',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: ''
};

/* ================================= 4 成就 =================================
 * create: key*(≤50, ^[a-z0-9_]+$) name*(≤100) description(≤500) icon(≤200) condition_json*(JSON) points*(≥0)
 * update: name description icon condition_json points status（key 不可改）
 * 状态：POST /achievement/toggle（{id: hashid, status: 0/1}），停用只影响后续授予
 * ========================================================================== */

const ACHIEVEMENT_FIELDS: FormField[] = [
  { name: 'key', label: 'achievement.key', type: 'text', required: true, placeholder: 'achievement.key_placeholder' },
  { name: 'name', label: 'achievement.name', type: 'text', required: true, placeholder: 'achievement.name_placeholder' },
  { name: 'description', label: 'achievement.description', type: 'textarea', placeholder: 'achievement.description_placeholder' },
  {
    name: 'icon', label: 'achievement.icon', type: 'image', placeholder: 'achievement.icon_placeholder',
    hint: 'achievement.icon_hint'
  },
  { name: 'condition_json', label: 'achievement.condition', type: 'textarea', required: true, placeholder: 'achievement.condition_placeholder' },
  { name: 'points', label: 'achievement.points', type: 'number', required: true, placeholder: 'achievement.points_placeholder' }
];

function achievementFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < ACHIEVEMENT_FIELDS.length; i++) {
    const field = ACHIEVEMENT_FIELDS[i];
    if (isEdit && field.name === 'key') {
      fields.push({ name: 'key', label: 'achievement.key', type: 'text', placeholder: 'common.immutable', disabled: true });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function achievementInitial(): FormValues {
  const values: FormValues = {};
  values.key = '';
  values.name = '';
  values.description = '';
  values.icon = '';
  values.condition_json = '';
  values.points = '0';
  return values;
}

function achievementRow(item: AchievementItem): ResourceRow {
  const values: FormValues = {};
  values.key = item.key;
  values.name = item.name;
  values.description = item.description ?? '';
  values.icon = item.icon ?? '';
  values.condition_json = item.condition_json ?? '';
  values.points = `${item.points}`;
  return buildRow(item.id, item.name, `${item.key} · ${item.points} ${t('achievement.points_unit')}`,
    item.status === 1 ? t('common.enabled') : t('common.disabled'), item.status === 1, item.status, values);
}

const ACHIEVEMENT: ResourceModule = {
  key: 'achievement',
  title: 'mod.achievement.title',
  desc: 'mod.achievement.desc',
  base: '/admin/v1/achievement',
  paginated: false,
  listPath: (_page: number, _limit: number, _gameId: string): string => '/admin/v1/achievement/list',
  parseList: (data: Object): ResourceListResult => unpaged<AchievementItem>(data, achievementRow),
  fields: achievementFields(false),
  editFields: achievementFields(true),
  createInitial: achievementInitial,
  statusMode: 'toggle',
  togglePath: '/admin/v1/achievement/toggle',
  needsGame: false,
  deletePassword: false,
  extraText: ''
};

/** 本组模块（注册表按此顺序拼装） */
export const CONTENT_MODULES: ResourceModule[] = [CATEGORY, SERVER, ACTIVITY, ACHIEVEMENT];
