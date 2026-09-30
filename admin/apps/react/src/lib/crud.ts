/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 写操作底座的纯逻辑：字段描述 → 表单草稿 → 请求体 → 提交前预检。
 * 不 import React、不碰 DOM，供 node --test 直接覆盖：四类动作的取值口径只有这一处，
 * 各模块只提供「字段描述」这一份声明（见 pages/modules.ts）。
 */

/**
 * `json` = 文本框里的 JSON **字符串**原样上送（服务端自己 json_decode，如活动 config）；
 * `jsonobj` = 文本框里的 JSON 解成**对象**再上送（服务端把该字段当数组读，收到字符串会静默丢弃，
 * 如风控试算的 context —— 传字符串不报错、只是当成 {} 评估，等于悄悄空转）。
 */
export type FieldType = 'text' | 'textarea' | 'number' | 'select' | 'switch' | 'json' | 'jsonobj' | 'lines' | 'multi';

export type FieldOption = { value: string; label: string };

/**
 * 控件值域：静态数组，或「开框时才拉」的异步来源（权限树这类端点给的值域，
 * 字段描述是模块级常量、拉不到数据）。异步来源由 FormModal 在挂载时解析。
 */
export type FieldOptions = FieldOption[] | (() => Promise<FieldOption[]>);

/** 表单字段描述：type 决定控件形态与提交时的类型转换。 */
export type Field = {
  name: string;
  label: string;
  type: FieldType;
  required?: boolean;
  options?: FieldOptions;
  placeholder?: string;
  /** 只读字段渲染成禁用控件，且永不提交（如游戏的 slug：后端 update 不接受它）。 */
  readOnly?: boolean;
  /** 新建时的初值，应与服务端 input() 的缺省值一致；缺省空串（switch 缺省 '0'）。 */
  default?: string;
  /** 控件下方的常驻说明（值域/格式/留空语义）；placeholder 打完字就看不见了，约束放这里。 */
  hint?: string;
};

/** 表单草稿：值一律字符串（switch 用 '1'/'0'），与 DOM 控件取值同形，便于比较与断言。 */
export type Draft = Record<string, string>;

/**
 * 库值 → 控件里显示的字符串。
 * - json 字段的库值可能是结构化数组/对象（模型 cast 成 array 后回编码成对象，如活动 config），
 *   控件里要的是可编辑的 JSON 文本；**已是字符串的一律原样**——JSON 列读回会被 MySQL 规范化
 *   （多余空格全被改写），再 stringify 一次就会让「没改过」的字段看起来改过、每次保存都回写一遍。
 * - null/undefined 落成空串（与控件取值同形）。
 */
function displayValue(field: Field, raw: unknown): string {
  if (raw === null || raw === undefined) return '';
  if ((field.type === 'json' || field.type === 'jsonobj') && typeof raw === 'object') return JSON.stringify(raw, null, 2);
  // multi 与 lines 同形：控件里是「一行一个值」的字符串（multi 是 <select multiple> 的拼装结果）
  if ((field.type === 'lines' || field.type === 'multi') && Array.isArray(raw)) return raw.join('\n');
  return String(raw);
}

/**
 * 行数据 → 表单草稿。row 缺省即新建：取字段描述里的 default，否则留空。
 * switch 一律归一化（TINYINT 读回可能是 0/1/'0'/'1'），编辑时以行值为准。
 */
export function draftFrom(fields: Field[], row?: Record<string, unknown>): Draft {
  const draft: Draft = {};
  for (const field of fields) {
    if (field.type === 'switch') {
      const on = row ? Number(row[field.name]) === 1 : (field.default ?? '0') === '1';
      draft[field.name] = on ? '1' : '0';
      continue;
    }
    draft[field.name] = displayValue(field, row ? row[field.name] : field.default);
  }
  return draft;
}

/** 控件值 → 提交值。number 空串转 null（validator 的 integer 规则不收 ''），switch 转 0/1。 */
function cast(field: Field, value: string): unknown {
  if (field.type === 'switch') return value === '1' ? 1 : 0;
  if (field.type === 'number') return value.trim() === '' ? null : Number(value);
  // 多行文本 → 字符串数组（每行一个值，空行丢弃）：批量分配类端点收的是数组不是字符串。
  if (field.type === 'lines' || field.type === 'multi') return lines(value);
  // jsonobj 解成对象上送（非空且合法的前提由 firstMissing 保证，走到这里空值已被跳过）
  if (field.type === 'jsonobj') return jsonObject(value) ?? {};
  // json 与普通文本一样原样上送：字符串由服务端 json_decode 校验，前端不另立一套规则。
  return value;
}

/**
 * JSON 文本 → 对象。空、语法错、或解出来不是对象（数组/标量）都算「没有」——
 * 服务端普遍是 `is_array($request->post('x'))` 才收，给字符串会被当成没传从而静默空转。
 */
function jsonObject(value: string): Record<string, unknown> | null {
  if (value.trim() === '') return null;
  try {
    const parsed: unknown = JSON.parse(value);
    return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)
      ? (parsed as Record<string, unknown>)
      : null;
  } catch {
    return null;
  }
}

/** 多行文本 → 去空白、丢空行的数组。 */
const lines = (value: string): string[] =>
  value
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line !== '');

/** 行里现值的草稿形态，用于判定编辑时某字段是否改动过（与 draftFrom 同一套换算）。 */
function current(field: Field, row: Record<string, unknown>): string {
  if (field.type === 'switch') return Number(row[field.name]) === 1 ? '1' : '0';
  return displayValue(field, row[field.name]);
}

/**
 * 草稿 → 请求体。
 * - 编辑（给了 row）：只提交**改动过**且非只读的字段 —— 后端 update 是局部更新（sometimes 规则），
 *   且部分字段本就不接受覆盖（slug）；未改动即不发，避免把回显不出的密钥类字段空写回去。
 * - 新建：跳过空值，让服务端的列默认值与 input() 缺省值生效（默认值只写在后端一处）。
 * - fullEdit：**编辑也按新建那套发**（不做改动比较、跳过空值），只给「update 是全量语义」的模块用：
 *   风控规则的 update 与 create 共用同一个 fill()，name/type/action 一律从请求体读且必填，
 *   只发改动字段必然 422（见 RiskRuleController::fill）。
 */
export function buildPayload(
  fields: Field[],
  draft: Draft,
  row?: Record<string, unknown>,
  fullEdit = false,
): Record<string, unknown> {
  const body: Record<string, unknown> = {};
  for (const field of fields) {
    if (field.readOnly) continue;
    const value = draft[field.name] ?? '';
    if (row && !fullEdit) {
      if (value === current(field, row)) continue;
    } else if (value.trim() === '') {
      continue;
    }
    body[field.name] = cast(field, value);
  }
  return body;
}

/** 提交前预检（必填 / 数字格式 / JSON 对象）。返回第一条中文提示，通过返回 null。 */
export function firstMissing(fields: Field[], draft: Draft): string | null {
  for (const field of fields) {
    if (field.readOnly) continue;
    const value = (draft[field.name] ?? '').trim();
    if (field.required && value === '') return `请${field.type === 'select' ? '选择' : '填写'}${field.label}`;
    if (field.type === 'number' && value !== '' && !Number.isFinite(Number(value))) {
      return `${field.label}必须是数字`;
    }
    if (field.type === 'jsonobj' && value !== '' && jsonObject(value) === null) {
      return `${field.label}必须是 JSON 对象（形如 {"ip": "1.2.3.4"}）`;
    }
  }
  return null;
}

/**
 * 列表行的 hashid。缺省认 id / hashid 两个键；`key` 是 CrudConfig.rowKey ——
 * 有的列表把 hashid 放在别的列上（风控用户列表叫 user_id），不认这个键就取不到 id，
 * 行内动作整排按钮会一个都不渲染（看着像「没做」）。
 */
export function rowId(row: Record<string, unknown>, key?: string): string {
  for (const name of key ? [key] : ['id', 'hashid']) {
    const raw = row[name];
    if (raw !== null && raw !== undefined && raw !== '') return String(raw);
  }
  return '';
}

/** 删除确认里的对象标识（名称/标题），取不到时退回「该记录」。 */
export function labelOf(row: Record<string, unknown>, key: string): string {
  const raw = row[key];
  const text = raw === null || raw === undefined ? '' : String(raw).trim();
  return text === '' ? '该记录' : text;
}

/** 行内 status 归一化为 0|1（仅认 1，其余按 0）。 */
export const statusOf = (row: Record<string, unknown>): 0 | 1 => (Number(row.status) === 1 ? 1 : 0);

/**
 * select 选项。行值不在描述的值域内时补一条「当前值」置顶：库里可能存着后端已不再收的旧枚举/
 * 历史脏值，也可能只是前端描述的值域写得比库里窄。让用户看得见原值，
 * 且原样不动 ⇒ 该字段不算改动、不会被发出去改成别的值。
 */
export function optionsWithCurrent(field: Field, value: string): FieldOption[] {
  // 异步值域：FormModal 已把它解析成数组再交给控件，走到这里说明是没解析的原始字段，按空值域处理
  const options = Array.isArray(field.options) ? field.options : [];
  if (value === '' || options.some((option) => option.value === value)) return options;
  return [{ value, label: `${value}（当前值）` }, ...options];
}
