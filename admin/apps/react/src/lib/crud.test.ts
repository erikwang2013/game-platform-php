/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { setCode } from '../i18n/index.ts';
import { buildPayload, draftFrom, firstMissing, labelOf, optionsWithCurrent, rowId, statusOf, type Field } from './crud.ts';

/** 与 pages/TabPage.tsx 的游戏字段同形的缩样：必填 text / 带值域的 select / 可空 number / switch / 只读。 */
const FIELDS: Field[] = [
  { name: 'name', label: 'f.game_name', type: 'text', required: true },
  { name: 'slug', label: 'f.game_slug', type: 'text', required: true },
  { name: 'type', label: 'f.game_type', type: 'select', required: true, options: [{ value: 'self' }] },
  { name: 'platform', label: 'f.client_platform', type: 'select', options: [{ value: 'h5' }], default: 'h5' },
  { name: 'sort', label: 'f.sort_order', type: 'number' },
  { name: 'status', label: 'f.listed', type: 'switch' },
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
  const fields: Field[] = [{ name: 'description', label: 'f.description', type: 'textarea' }, { name: 'sort', label: 'f.sort_order', type: 'number' }];
  const row = { description: '旧简介', sort: 5 };
  assert.deepEqual(buildPayload(fields, { description: '', sort: '5' }, row), { description: '' });
  assert.deepEqual(buildPayload(fields, { description: '旧简介', sort: '' }, row), { sort: null });
});

test('firstMissing：必填缺失/数字非法各报一条，只读字段不参与', () => {
  // 断言写成**英文成品**而不是 t(...)：比对同一个调用是自证；键换错、参数换错都要红
  assert.equal(firstMissing(FIELDS, draftFrom(FIELDS)), 'Please fill in Game Name');
  assert.equal(
    firstMissing(FIELDS, { name: 'x', slug: 'x', type: '', platform: 'h5', sort: '', status: '0' }),
    'Please select Game Type',
  );
  assert.equal(firstMissing(FIELDS, { name: 'x', slug: 'x', type: 'self', sort: 'abc', status: '0' }), 'Sort Order must be a number');
  const fields: Field[] = [{ name: 'slug', label: 'f.game_slug', type: 'text', required: true, readOnly: true }];
  assert.equal(firstMissing(fields, { slug: '' }), null);
});

test('labelOf：取对象标识，缺失/空白退回 app.this_record 的译文；statusOf 只认 1', () => {
  assert.equal(labelOf({ name: '贪吃蛇' }, 'name'), '贪吃蛇');
  assert.equal(labelOf({ name: '  ' }, 'name'), 'this record');
  assert.equal(labelOf({}, 'name'), 'this record');
  assert.equal(labelOf({ title: 0 }, 'title'), '0');

  assert.equal(statusOf({ status: 1 }), 1);
  assert.equal(statusOf({ status: '1' }), 1);
  assert.equal(statusOf({ status: 0 }), 0);
  assert.equal(statusOf({ status: undefined }), 0);
});

test('json 字段：库值是对象时转成可编辑 JSON 文本，字符串原样（JSON 列读回被规范化过）', () => {
  const field: Field = { name: 'config', label: 'f.rule_config_json', type: 'json' };
  // 活动 config 被模型 cast 成 array ⇒ 回编码成对象
  assert.equal(draftFrom([field], { config: { rewards: [{ day: 1 }] } }).config, '{\n  "rewards": [\n    {\n      "day": 1\n    }\n  ]\n}');
  // 已是字符串的（成就是 JSON 列、无 cast）原样带出，绝不重新序列化：规范化后的空白差异
  // 会被当成「改过」，每次保存都把没动过的字段回写一遍
  assert.equal(draftFrom([field], { config: '{"a": 1}' }).config, '{"a": 1}');
  assert.equal(draftFrom([field], { config: null }).config, '');
});

test('json 字段：编辑时未改动不发、改动按字符串发（服务端 json_decode 校验）', () => {
  const field: Field = { name: 'config', label: 'f.rule_config_json', type: 'json' };
  const row = { config: { target: 3 } };
  assert.deepEqual(buildPayload([field], draftFrom([field], row), row), {});
  assert.deepEqual(buildPayload([field], { config: '{"target": 5}' }, row), { config: '{"target": 5}' });
});

test('lines 字段：多行转数组（去空白、丢空行）；行值是数组时按行回显，未改动不发', () => {
  const field: Field = { name: 'game_ids', label: 'f.game_hashid', type: 'lines' };
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
  setCode('en');
  const field: Field = { name: 'context', label: 'f.rule_config_json', type: 'jsonobj' };
  assert.deepEqual(buildPayload([field], { context: '{"ip": "1.2.3.4", "amount": "100"}' }), {
    context: { ip: '1.2.3.4', amount: '100' },
  });
  // 留空 ⇒ 不发（服务端自己回落到空上下文）
  assert.deepEqual(buildPayload([field], { context: '' }), {});
  // 非对象（数组/标量）与服务端 is_array 口径一致：拦在提交前，而不是让它静默空转。
  // 断言是**英文成品**：报错文案里的字段名取自 label（键 → 译文），写成 t(...) 比对是自证。
  const bad = 'Rule Config (JSON) must be a JSON object (e.g. {"ip": "1.2.3.4"})';
  assert.equal(firstMissing([field], { context: '[1,2]' }), bad);
  assert.equal(firstMissing([field], { context: '"abc"' }), bad);
  assert.equal(firstMissing([field], { context: '{oops}' }), bad);
  assert.equal(firstMissing([field], { context: '{"ip": "1.2.3.4"}' }), null);
  assert.equal(firstMissing([field], { context: '' }), null);
});

test('jsonarr 字段：文本框里的 JSON 解成数组上送（jsonobj 拒收数组，lines 只给得出字符串）', () => {
  setCode('en');
  const field: Field = { name: 'currencies', label: 'f.game_currencies', type: 'jsonarr' };
  // 关键的一条：数组**逐元素原样**上送，id 与数值字符串一个都不能被改写
  assert.deepEqual(
    buildPayload([field], { currencies: '[{"id": "Xk9", "exchange_rate": "7.20000000"}]' }),
    { currencies: [{ id: 'Xk9', exchange_rate: '7.20000000' }] },
  );
  // 留空 ⇒ 不发（必填由 firstMissing 管，这里只管类型转换）
  assert.deepEqual(buildPayload([field], { currencies: '' }), {});
  // 非数组与服务端 `required|array` 口径一致：拦在提交前。
  // 断言是**英文成品**：字段名取自 label（键 → 译文），写成 t(...) 比对是自证。
  const bad = 'Game currencies must be a JSON array';
  assert.equal(firstMissing([field], { currencies: '{"name": "gold"}' }), bad);
  assert.equal(firstMissing([field], { currencies: '"abc"' }), bad);
  assert.equal(firstMissing([field], { currencies: '[{oops}]' }), bad);
  assert.equal(firstMissing([field], { currencies: '[]' }), null);
  assert.equal(firstMissing([field], { currencies: '' }), null);
});

test('jsonarr 字段：库值（数组）在控件里是可编辑的 JSON 文本，未改动则编辑态不发', () => {
  setCode('en');
  const field: Field = { name: 'currencies', label: 'f.game_currencies', type: 'jsonarr' };
  const row = { currencies: [{ id: 'Xk9', name: 'Gold' }] };
  const text = draftFrom([field], row).currencies;
  assert.equal(text, JSON.stringify([{ id: 'Xk9', name: 'Gold' }], null, 2));
  // 编辑态：与行值逐字相同时不发（JSON 列读回会被规范化，故比较的是文本而不是重新 stringify 的结果）
  assert.deepEqual(buildPayload([field], { currencies: text }, row), {});
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
  setCode('en');
  // 选项 label 是**键**（渲染期才取译文）⇒ 这里断言渲染后的英文成品，键换错/漏取译文都会红
  const field: Field = { name: 'type', label: 'f.type', type: 'select', options: [{ value: 'approve', label: 'funds.approve' }] };
  assert.deepEqual(optionsWithCurrent(field, 'approve'), [{ value: 'approve', label: 'Approve' }]);
  assert.deepEqual(optionsWithCurrent(field, ''), [{ value: 'approve', label: 'Approve' }]);
  assert.deepEqual(optionsWithCurrent(field, 'legacy'), [
    { value: 'legacy', label: 'legacy (current value)' },
    { value: 'approve', label: 'Approve' },
  ]);
  // 补的这条是**原值**（只加标签不改值），否则「编辑时不碰该字段」的判等会误判成有改动
  assert.equal(optionsWithCurrent(field, 'legacy')[0].value, 'legacy');
});

test('file 字段：草稿里放的是**文件名**（必填预检与「编辑态只发改动」都按字符串走）', () => {
  setCode('en');
  const field: Field = { name: 'file', label: 'f.file', type: 'file', required: true, accept: '.xlsx,.xls' };
  // 新建：没选文件 ⇒ 草稿空串 ⇒ 必填预检拦下（后端拿不到 file 段会回「请选择文件」，这里先省一个来回）
  assert.equal(draftFrom([field]).file, '');
  assert.equal(firstMissing([field], { file: '' }), 'Please fill in File');
  // 选了文件：草稿里只有名字（`Draft` 是字符串表，File 进不来 —— 它由 FormModal 在提交时塞进请求体）
  assert.deepEqual(buildPayload([field], { file: 'admins.xlsx' }), { file: 'admins.xlsx' });
  assert.equal(firstMissing([field], { file: 'admins.xlsx' }), null);
  // 编辑态没换文件 ⇒ 一个字段都不发（不会被那个文件名串当成改动空写回去）
  assert.deepEqual(buildPayload([field], { file: 'old.xlsx' }, { file: 'old.xlsx' }), {});
  // 换了一个 ⇒ 发（值先是个串，FormModal 随后用同名 File 覆盖掉它）
  assert.deepEqual(buildPayload([field], { file: 'new.xlsx' }, { file: 'old.xlsx' }), { file: 'new.xlsx' });
});
