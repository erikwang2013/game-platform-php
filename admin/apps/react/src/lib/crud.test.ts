/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { buildPayload, draftFrom, firstMissing, labelOf, optionsWithCurrent, rowId, statusOf, type Field } from './crud.ts';

/** 与 pages/TabPage.tsx 的游戏字段同形的缩样：必填 text / 带值域的 select / 可空 number / switch / 只读。 */
const FIELDS: Field[] = [
  { name: 'name', label: '游戏名称', type: 'text', required: true },
  { name: 'slug', label: '游戏标识', type: 'text', required: true },
  { name: 'type', label: '游戏类型', type: 'select', required: true, options: [{ value: 'self', label: '自研' }] },
  { name: 'platform', label: '客户端平台', type: 'select', options: [{ value: 'h5', label: 'h5' }], default: 'h5' },
  { name: 'sort', label: '排序', type: 'number' },
  { name: 'status', label: '上架', type: 'switch' },
];

const ROW = {
  id: 'Xk9',
  name: '贪吃蛇',
  slug: 'snake',
  type: 'self',
  platform: 'h5',
  sort: 5,
  status: 1,
};

test('draftFrom：新建取 default（switch 缺省关），编辑按行值预填且值一律字符串', () => {
  assert.deepEqual(draftFrom(FIELDS), {
    name: '',
    slug: '',
    type: '',
    platform: 'h5',
    sort: '',
    status: '0',
  });
  assert.deepEqual(draftFrom(FIELDS, ROW), {
    name: '贪吃蛇',
    slug: 'snake',
    type: 'self',
    platform: 'h5',
    sort: '5',
    status: '1',
  });
  // TINYINT 读回是 '1' 也要认；NULL 落成空串
  assert.equal(draftFrom(FIELDS, { status: '1', sort: null }).status, '1');
  assert.equal(draftFrom(FIELDS, { sort: null }).sort, '');
});

test('buildPayload：新建跳过空值（后端默认值生效），switch 转 0/1，number 转数字', () => {
  const body = buildPayload(FIELDS, {
    name: '贪吃蛇',
    slug: 'snake',
    type: 'self',
    platform: 'h5',
    sort: '3',
    status: '1',
  });
  assert.deepEqual(body, { name: '贪吃蛇', slug: 'snake', type: 'self', platform: 'h5', sort: 3, status: 1 });
  // 空串字段整体不出现 ⇒ 由后端 input() 缺省值决定
  assert.deepEqual(Object.keys(buildPayload(FIELDS, { name: 'x', slug: 'x', type: 'self' })), ['name', 'slug', 'type']);
});

test('buildPayload：编辑只发改动过的字段，未改动/只读字段一个都不发', () => {
  const fields: Field[] = FIELDS.map((field) => (field.name === 'slug' ? { ...field, readOnly: true } : field));
  const draft = draftFrom(fields, ROW);
  assert.deepEqual(buildPayload(fields, draft, ROW), {});

  draft.name = '贪吃蛇 II';
  assert.deepEqual(buildPayload(fields, draft, ROW), { name: '贪吃蛇 II' });

  // 只读字段改了也不发（改动只能来自代码，正常路径下控件是 disabled）
  draft.slug = 'hacked';
  assert.deepEqual(buildPayload(fields, draft, ROW), { name: '贪吃蛇 II' });
});

test('buildPayload：编辑时清空——文本发空串，number 发 null（integer 规则不收空串）', () => {
  const fields: Field[] = [{ name: 'description', label: '简介', type: 'textarea' }, { name: 'sort', label: '排序', type: 'number' }];
  const row = { description: '旧简介', sort: 5 };
  assert.deepEqual(buildPayload(fields, { description: '', sort: '5' }, row), { description: '' });
  assert.deepEqual(buildPayload(fields, { description: '旧简介', sort: '' }, row), { sort: null });
});

test('firstMissing：必填缺失/数字非法各报一条，只读字段不参与', () => {
  assert.equal(firstMissing(FIELDS, draftFrom(FIELDS)), '请填写游戏名称');
  assert.equal(
    firstMissing(FIELDS, { name: 'x', slug: 'x', type: '', platform: 'h5', sort: '', status: '0' }),
    '请选择游戏类型',
  );
  assert.equal(firstMissing(FIELDS, { name: 'x', slug: 'x', type: 'self', sort: 'abc', status: '0' }), '排序必须是数字');
  const fields: Field[] = [{ name: 'slug', label: '游戏标识', type: 'text', required: true, readOnly: true }];
  assert.equal(firstMissing(fields, { slug: '' }), null);
});

test('labelOf：取对象标识，缺失/空白退回「该记录」；statusOf 只认 1', () => {
  assert.equal(labelOf({ name: '贪吃蛇' }, 'name'), '贪吃蛇');
  assert.equal(labelOf({ name: '  ' }, 'name'), '该记录');
  assert.equal(labelOf({}, 'name'), '该记录');
  assert.equal(labelOf({ title: 0 }, 'title'), '0');

  assert.equal(statusOf({ status: 1 }), 1);
  assert.equal(statusOf({ status: '1' }), 1);
  assert.equal(statusOf({ status: 0 }), 0);
  assert.equal(statusOf({ status: undefined }), 0);
});

test('json 字段：库值是对象时转成可编辑 JSON 文本，字符串原样（JSON 列读回被规范化过）', () => {
  const field: Field = { name: 'config', label: '配置', type: 'json' };
  // 活动 config 被模型 cast 成 array ⇒ 回编码成对象
  assert.equal(draftFrom([field], { config: { rewards: [{ day: 1 }] } }).config, '{\n  "rewards": [\n    {\n      "day": 1\n    }\n  ]\n}');
  // 已是字符串的（成就是 JSON 列、无 cast）原样带出，绝不重新序列化：规范化后的空白差异
  // 会被当成「改过」，每次保存都把没动过的字段回写一遍
  assert.equal(draftFrom([field], { config: '{"a": 1}' }).config, '{"a": 1}');
  assert.equal(draftFrom([field], { config: null }).config, '');
});

test('json 字段：编辑时未改动不发、改动按字符串发（服务端 json_decode 校验）', () => {
  const field: Field = { name: 'config', label: '配置', type: 'json' };
  const row = { config: { target: 3 } };
  assert.deepEqual(buildPayload([field], draftFrom([field], row), row), {});
  assert.deepEqual(buildPayload([field], { config: '{"target": 5}' }, row), { config: '{"target": 5}' });
});

test('lines 字段：多行转数组（去空白、丢空行）；行值是数组时按行回显，未改动不发', () => {
  const field: Field = { name: 'game_ids', label: '游戏 hashid', type: 'lines' };
  assert.deepEqual(buildPayload([field], { game_ids: 'a\n  b  \n\nc\n' }), { game_ids: ['a', 'b', 'c'] });
  assert.deepEqual(buildPayload([field], { game_ids: '   ' }), {});

  const row = { game_ids: ['a', 'b'] };
  assert.equal(draftFrom([field], row).game_ids, 'a\nb');
  assert.deepEqual(buildPayload([field], draftFrom([field], row), row), {});
  assert.deepEqual(buildPayload([field], { game_ids: 'a\nb\nc' }, row), { game_ids: ['a', 'b', 'c'] });
});

/* ------------------------------ B5：全量编辑 / JSON 对象 / 行 id ------------------------------ */

/** 风控规则同形的缩样：update 走的是与 create 同一个 fill()，三个必填字段一律从请求体读。 */
const RULE_FIELDS: Field[] = [
  { name: 'name', label: '规则名称', type: 'text', required: true },
  { name: 'type', label: '规则类型', type: 'select', required: true, options: [{ value: 'frequency', label: 'frequency' }] },
  { name: 'action', label: '命中动作', type: 'select', required: true, options: [{ value: 'block', label: 'block' }] },
  { name: 'scope', label: '生效范围', type: 'select', default: 'all', options: [{ value: 'all', label: 'all' }] },
  { name: 'config', label: '配置', type: 'json', required: true, default: '{}' },
  { name: 'priority', label: '优先级', type: 'number', default: '100' },
  { name: 'status', label: '启用', type: 'switch', default: '1' },
];

const RULE_ROW = { id: 'R1', name: '频率限制', type: 'frequency', action: 'block', scope: 'all', config: '{"max_count":5}', priority: 100, status: 1 };

test('buildPayload：fullEdit 下编辑也发全量（未改动字段照样发，空值仍跳过）', () => {
  const draft = draftFrom(RULE_FIELDS, RULE_ROW);
  // 默认（局部编辑）：一个字段都没动 ⇒ 空体
  assert.deepEqual(buildPayload(RULE_FIELDS, draft, RULE_ROW), {});
  // 全量编辑：必填三件套一个都不能少，否则服务端 fill() 抛「name/type/action 必填」→ 422
  assert.deepEqual(buildPayload(RULE_FIELDS, draft, RULE_ROW, true), {
    name: '频率限制',
    type: 'frequency',
    action: 'block',
    scope: 'all',
    config: '{"max_count":5}',
    priority: 100,
    status: 1,
  });
  // 只改一处，其余仍要带上：这正是「只发改动字段必被 422」的模块所必需的口径
  const changed = { ...draft, priority: '200' };
  assert.deepEqual(Object.keys(buildPayload(RULE_FIELDS, changed, RULE_ROW, true)), Object.keys(draft));
  // 空值仍跳过（服务端的列默认值 / input() 缺省值说了算）
  assert.deepEqual(buildPayload(RULE_FIELDS, { name: 'x', type: 'frequency', action: 'block', config: '' }, RULE_ROW, true), {
    name: 'x',
    type: 'frequency',
    action: 'block',
  });
});

test('jsonobj 字段：文本框里的 JSON 解成对象上送（字符串会被服务端当没传）', () => {
  const field: Field = { name: 'context', label: '试算上下文', type: 'jsonobj' };
  assert.deepEqual(buildPayload([field], { context: '{"ip": "1.2.3.4", "amount": "100"}' }), {
    context: { ip: '1.2.3.4', amount: '100' },
  });
  // 留空 ⇒ 不发（服务端自己回落到空上下文）
  assert.deepEqual(buildPayload([field], { context: '' }), {});
  // 非对象（数组/标量）与服务端 is_array 口径一致：拦在提交前，而不是让它静默空转
  assert.equal(firstMissing([field], { context: '[1,2]' }), '试算上下文必须是 JSON 对象（形如 {"ip": "1.2.3.4"}）');
  assert.equal(firstMissing([field], { context: '"abc"' }), '试算上下文必须是 JSON 对象（形如 {"ip": "1.2.3.4"}）');
  assert.equal(firstMissing([field], { context: '{oops}' }), '试算上下文必须是 JSON 对象（形如 {"ip": "1.2.3.4"}）');
  assert.equal(firstMissing([field], { context: '{"ip": "1.2.3.4"}' }), null);
  assert.equal(firstMissing([field], { context: '' }), null);
});

test('rowId：缺省认 id/hashid，rowKey 指到别的列（风控用户列表的 hashid 在 user_id 上）', () => {
  assert.equal(rowId({ id: 'Xk9' }), 'Xk9');
  assert.equal(rowId({ hashid: 'Xk9' }), 'Xk9');
  assert.equal(rowId({ id: 'Xk9', hashid: 'other' }), 'Xk9');
  // 空串按「没有」处理，回落到 hashid（与 pick 同口径：后端可能把空 id 回成 ""）
  assert.equal(rowId({ id: '', hashid: 'Xk9' }), 'Xk9');
  assert.equal(rowId({}), '');
  assert.equal(rowId({ id: 0 }), '0');
  // 行里没有 id 列，hashid 在 user_id 上：不认 rowKey 就一个按钮都长不出来
  assert.equal(rowId({ user_id: 'Uk9' }), '');
  assert.equal(rowId({ user_id: 'Uk9' }, 'user_id'), 'Uk9');
  // 指到了不存在的列 ⇒ 空串（行内动作整排不渲染），而不是悄悄退回 id
  assert.equal(rowId({ id: 'Xk9' }, 'user_id'), '');
});

test('image 字段：值仍是字符串，照旧参与「编辑态只发改动」（上传结果与原值不同就会被发出去）', () => {
  const field: Field = { name: 'cover_image', label: '封面图', type: 'image' };
  const row = { cover_image: 'https://cdn.example.com/old.png' };
  // 存量值（手输 URL / 图标名）原样带进控件
  assert.equal(draftFrom([field], row).cover_image, 'https://cdn.example.com/old.png');
  // 没动它 ⇒ 一个字段都不发（上传只在用户选了文件时才写值）
  assert.deepEqual(buildPayload([field], draftFrom([field], row), row), {});
  // 上传把它换成新的绝对展示 URL ⇒ 与原值不同，按字符串发出去
  const uploaded = 'http://admin.games.test/admin/v1/aetherupload/display/image_202610_ab12.png';
  assert.deepEqual(buildPayload([field], { cover_image: uploaded }, row), { cover_image: uploaded });
  // 库值是 NULL（从没设过封面）时控件里是空串，不是 "null"
  assert.equal(draftFrom([field], { cover_image: null }).cover_image, '');
});

test('optionsWithCurrent：行值不在值域内时置顶补一条「当前值」，在值域内或为空则原样', () => {
  const field: Field = { name: 'type', label: '类型', type: 'select', options: [{ value: 'system', label: '系统' }] };
  assert.deepEqual(optionsWithCurrent(field, 'system'), [{ value: 'system', label: '系统' }]);
  assert.deepEqual(optionsWithCurrent(field, ''), [{ value: 'system', label: '系统' }]);
  assert.deepEqual(optionsWithCurrent(field, 'legacy'), [
    { value: 'legacy', label: 'legacy（当前值）' },
    { value: 'system', label: '系统' },
  ]);
});
