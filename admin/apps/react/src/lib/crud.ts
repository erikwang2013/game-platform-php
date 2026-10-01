/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 写操作底座的纯逻辑：字段描述 → 表单草稿 → 请求体 → 提交前预检。
 * 不 import React、不碰 DOM，供 node --test 直接覆盖：四类动作的取值口径只有这一处，
 * 各模块只提供「字段描述」这一份声明（见 pages/modules.ts）。
 */
import { t, type MessageKey } from '../i18n/index.ts';
import type { TreeNode } from './tree';

/**
 * `json` = 文本框里的 JSON **字符串**原样上送（服务端自己 json_decode，如活动 config）；
 * `jsonobj` = 文本框里的 JSON 解成**对象**再上送（服务端把该字段当数组读，收到字符串会静默丢弃，
 * 如风控试算的 context —— 传字符串不报错、只是当成 {} 评估，等于悄悄空转）；
 * `jsonarr` = 文本框里的 JSON 解成**数组**再上送（值域是「一组对象」的端点，如游戏币种的
 * `currencies: [{id?, name, symbol, exchange_rate…}]`）。单独立一条而不是复用 jsonobj：
 * 它明确拒收数组（`!Array.isArray(parsed)`），而 `lines` 只给得出字符串数组 ——
 * 「一行里有好几个字段」这件事两者都表达不了；
 * `image` = 文本框 + 上传按钮（见 lib/upload.ts）：值仍是字符串，只是能由上传结果写回，
 * 库里的存量值（手输 URL / 图标名）照旧可编辑，改动比较也照旧；
 * `tree` = 树形多选（见 components/PermissionTree.tsx），值的形态与 `multi` 完全相同
 * （换行分隔的 id 串 ↔ 数组），只是候选项是棵树、勾选有父子联动。
 * `password` = 与 `text` 完全同形（值仍是字符串），只是控件遮挡输入：口令字段不该在屏幕上明文摆着。
 * `file` = 选本地文件（见 `Field.accept`）。**草稿里放的是文件名（字符串）**，真正上送的 `File`
 * 由 FormModal 在 `buildPayload` 之后塞进请求体（见 FormModal 的 `picked`）——
 * 草稿保持字符串有两处非它不可：必填预检与「编辑时该字段是否改动过」都按字符串比。
 */
export type FieldType = 'text' | 'textarea' | 'number' | 'select' | 'switch' | 'json' | 'jsonobj' | 'jsonarr' | 'lines' | 'multi' | 'image' | 'tree' | 'password' | 'file';

/**
 * `label` 是**文案键**不是译文：取译文只在渲染期（见下面 `Field.label` 的说明）。
 * 可以缺省 —— 那表示**显示名就是值本身**（技术枚举：h5/web、string/int、cloudfront…），
 * 硬凑一条「键=值、译文=值」的表项是噪声，渲染层直接回落 `value`（见 `optionLabel`）。
 */
export type FieldOption = {
  value: string;
  label?: MessageKey;
  /**
   * 占位符实参。选项文案是**逐行现拼**的（角色候选要带上服务端给的 name/slug 与启停态），
   * 键必须是静态的、值才是动态的 —— 故给键配一份实参，而不是给一条拼好的成品文案：
   * 成品文案会冻在拼它的那一刻的语言上（同 `Field.label` 的理由）。
   */
  params?: Record<string, string | number>;
};

/**
 * 控件值域：静态数组，或「开框时才拉」的异步来源（权限树这类端点给的值域，
 * 字段描述是模块级常量、拉不到数据）。异步来源由 FormModal 在挂载时解析。
 */
export type FieldOptions = FieldOption[] | (() => Promise<FieldOption[]>);

/**
 * **非译文**的字面量：JSON 样例、日期格式、`≥ 0`、`1.2.3.4` —— 各语言下逐字相同，
 * 不是可译文案，不进译文表（硬凑一条「键=值、译文=值」的表项是噪声，同 `FieldOption.label` 缺省那条理由）。
 * 包一层 `raw()` 而不是直接放行任意字符串：键写错仍要 tsc 报错，只有显式标注的才原样输出。
 */
export type RawText = { raw: string };
export const raw = (text: string): RawText => ({ raw: text });

/** 字段描述里的文案：可译键，或显式标注的非译文字面量。 */
export type FieldText = MessageKey | RawText;

/**
 * 表单字段描述：type 决定控件形态与提交时的类型转换。
 *
 * `label` / `placeholder` / `hint` / 选项的 `label` 一律是**文案键**（`f.*`，见 `i18n/en.fields.ts`），
 * 不是译文。理由有两条，缺一不可：
 * 1. `pages/modules.ts` 是**模块级常量**，在模块顶层求 `t()` 会把文案冻在首次求值的语言上
 *    （换语言后这些字段名不跟着变）；取译文只能在渲染期现取。
 * 2. 类型是 `MessageKey` ⇒ 键写错是**编译期报错**，357 条字段文案不会有一条悄悄漏翻。
 */
export type Field = {
  name: string;
  label: MessageKey;
  type: FieldType;
  required?: boolean;
  options?: FieldOptions;
  /**
   * 树形字段（`type: 'tree'`）的树本身 —— 与 options 同理，模块级常量里拿不到数据，开框时才拉。
   * 不摊平成 options：值的父子联动要靠树（见 lib/tree.ts）。
   */
  tree?: () => Promise<TreeNode[]>;
  placeholder?: FieldText;
  /** 只读字段渲染成禁用控件，且永不提交（如游戏的 slug：后端 update 不接受它）。 */
  readOnly?: boolean;
  /** 新建时的初值，应与服务端 input() 的缺省值一致；缺省空串（switch 缺省 '0'）。 */
  default?: string;
  /** 控件下方的常驻说明（值域/格式/留空语义）；placeholder 打完字就看不见了，约束放这里。 */
  hint?: FieldText;
  /**
   * `file` 字段的 `<input accept>`（如 `.xlsx,.xls`）。**只是给系统选文件对话框的过滤提示**，
   * 不是校验：用户仍能强行选中别的类型。真值域由服务端判（`ImportController` 认扩展名白名单），
   * 这里再实现一遍就是第二个真值源，迟早和后端漂开。
   */
  accept?: string;
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
  if ((field.type === 'json' || field.type === 'jsonobj' || field.type === 'jsonarr') && typeof raw === 'object') {
    return JSON.stringify(raw, null, 2);
  }
  // multi / tree 与 lines 同形：控件里是「一行一个值」的字符串（multi 是 <select multiple> 的拼装结果，
  // tree 是树控件勾选结果的拼装）
  if ((field.type === 'lines' || field.type === 'multi' || field.type === 'tree') && Array.isArray(raw)) {
    return raw.join('\n');
  }
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
  // 多行文本 → 字符串数组（每行一个值，空行丢弃）：批量分配类端点收的是数组不是字符串
  // （tree 的勾选结果也走这条：角色端点的 permission_ids 收 hashid 数组）。
  if (field.type === 'lines' || field.type === 'multi' || field.type === 'tree') return lines(value);
  // jsonobj 解成对象上送（非空且合法的前提由 firstMissing 保证，走到这里空值已被跳过）
  if (field.type === 'jsonobj') return jsonObject(value) ?? {};
  // jsonarr 解成数组上送（同上：合法性已预检过）
  if (field.type === 'jsonarr') return jsonArray(value) ?? [];
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

/**
 * JSON 文本 → **数组**（jsonObject 的数组版，口径同理）：空、语法错、解出来不是数组都算「没有」。
 * 与 jsonObject 一样不抛错：调用方要么已经预检过（firstMissing），要么在「没有」时该走降级分支。
 */
function jsonArray(value: string): unknown[] | null {
  if (value.trim() === '') return null;
  try {
    const parsed: unknown = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : null;
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

/** 提交前预检（必填 / 数字格式 / JSON 对象）。返回第一条提示（当前语言），通过返回 null。 */
export function firstMissing(fields: Field[], draft: Draft): string | null {
  for (const field of fields) {
    if (field.readOnly) continue;
    const value = (draft[field.name] ?? '').trim();
    const name = t(field.label);
    if (field.required && value === '') {
      return t(field.type === 'select' ? 'app.field_required_select' : 'app.field_required_input', { name });
    }
    if (field.type === 'number' && value !== '' && !Number.isFinite(Number(value))) {
      return t('app.field_must_be_number', { name });
    }
    if (field.type === 'jsonobj' && value !== '' && jsonObject(value) === null) {
      return t('app.field_must_be_json_object', { name });
    }
    if (field.type === 'jsonarr' && value !== '' && jsonArray(value) === null) {
      return t('app.field_must_be_json_array', { name });
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
  return text === '' ? t('app.this_record') : text;
}

/** 行内 status 归一化为 0|1（仅认 1，其余按 0）。 */
export const statusOf = (row: Record<string, unknown>): 0 | 1 => (Number(row.status) === 1 ? 1 : 0);

/** 控件的候选项：**译文**（`FieldOption` 是键，这里是渲染用的成品）。 */
export type OptionView = { value: string; label: string };

/** 选项显示名：键 → 当前语言；没给键就用值本身。**所有**渲染路径都过这里，别在组件里各写一份。 */
export const optionLabel = (option: FieldOption): string =>
  option.label === undefined ? option.value : t(option.label, option.params);

/** 字段描述里的文案 → 当前语言；`undefined` 原样透传（控件的 placeholder 等要它保持缺省）。 */
export const fieldText = (key: FieldText | undefined): string | undefined =>
  key === undefined ? undefined : typeof key === 'string' ? t(key) : key.raw;

/**
 * select 选项。行值不在描述的值域内时补一条「当前值」置顶：库里可能存着后端已不再收的旧枚举/
 * 历史脏值，也可能只是前端描述的值域写得比库里窄。让用户看得见原值，
 * 且原样不动 ⇒ 该字段不算改动、不会被发出去改成别的值。
 */
export function optionsWithCurrent(field: Field, value: string): OptionView[] {
  // 异步值域：FormModal 已把它解析成数组再交给控件，走到这里说明是没解析的原始字段，按空值域处理
  const options = Array.isArray(field.options) ? field.options : [];
  const views = options.map((option) => ({ value: option.value, label: optionLabel(option) }));
  if (value === '' || options.some((option) => option.value === value)) return views;
  return [{ value, label: t('app.current_value', { value }) }, ...views];
}
