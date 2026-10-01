/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import {
  DASHBOARD_KEYS,
  FALLBACK_ICON,
  ICONS,
  LOG_COLUMNS,
  hasDashboardShape,
  iconFor,
  isCumulative,
} from '../lib/dashboard.ts';

/**
 * 仪表盘渲染器的四条真分支：**形状回落 / 累计判定 / 图标回落 / 列序**。
 *
 * 分开两层钉，因为两层会以不同方式坏掉：
 * - **纯逻辑**（`lib/dashboard.ts`）行为级钉 —— 搬出 `.tsx` 就是为了这个（本树 `npm test` 是
 *   `node --test` + `--experimental-strip-types`，**不认 `.tsx`**，断言不进 import 图）；
 * - **调用点**（判定为假时到底渲不渲兜底、累计判据接没接上轴、映射表有没有被用上）只能读源码钉，
 *   与 `i18n/wiring.test.ts` 同一套理由与手法：这类「接错线」是静默的 —— 函数全绿、界面照旧。
 *
 * 每条断言都必须能红（变异后跑一次验过，见提交说明）：只钉「串在文件里」而不钉位置关系或行为的
 * 写法（比如 `assert.ok(src.includes('user_name'))`）挡不住任何东西，这里一条都没有。
 */
const page = readFileSync(fileURLToPath(new URL('./dashboard.tsx', import.meta.url)), 'utf8');

/**
 * 只看**代码**（剥掉注释与字符串里不会出现的 `//`）。不是洁癖：本文件的说明性注释里就写着
 * `zeroBased={!cumulative}` 这段字面串 —— 直接在原文上断言，把那行代码写成 `{cumulative}`
 * 也照样绿（**实测咬到过**：变异后 121/121 全过，等于这条钉子不存在）。断言读散文不读代码
 * 就是恒真式。手法与 `i18n/coverage.test.ts` 剥注释同一套。
 */
const code = page.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '');


/** 退化体的可读标签（`JSON.stringify(undefined)` 是 undefined 不是串，这里要的是「看得出是哪个」）。 */
const tag = (value: unknown): string => (value === undefined ? 'undefined' : JSON.stringify(value));

test('形状回落：四个容器键任一在就认；一个都不在 ⇒ 判假并**真**渲 AutoView（不是白屏）', () => {
  for (const key of DASHBOARD_KEYS) {
    assert.equal(hasDashboardShape({ [key]: null }), true, `只有 ${key} 时不认这个形状（那一块会整块消失）`);
  }
  // 退化体一个都不许误判成「认识」：误判的后果是拿空数据画四张空卡，真契约变了也看不出来
  for (const other of [{}, { ok: true }, { code: 0, message: 'success', data: {} }, [], 'x', 0, null, undefined]) {
    assert.equal(hasDashboardShape(other), false, `${tag(other)} 被误判成仪表盘形状`);
  }
  // `in` 对裸标量直接抛 TypeError ⇒ 那不是「摊平」而是整页白屏，兜底就白写了
  assert.doesNotThrow(() => hasDashboardShape('boom'), '裸标量把判定函数打挂了');
  // 判假时**真的**走兜底：把这一行改成 return null，上面全绿而页面白屏 —— 这条才咬得住
  assert.equal(code.split('<AutoView data={data} />').length - 1, 1, '形状不认识的回落不是（或不止一处是）AutoView');
  assert.match(code, /if \(!known\)[\s\S]{0,40}<AutoView/, 'AutoView 不在 !known 分支里（判假时渲染别的东西）');
});

test('累计判定：非递减才算累计（判据取自数据，不看 series 名字）；平盘也是累计', () => {
  // 累计用户中间某天没涨（平盘）仍然是累计量 —— 写成 `>` 会让它掉进零基轴，同一屏两条线两种口径
  assert.equal(isCumulative([47000, 47000, 48200]), true, '平盘的累计线被判成计数');
  assert.equal(isCumulative([0, 1, 2]), true);
  assert.equal(isCumulative([5]), true, '单点没有斜率，算累计');
  assert.equal(isCumulative([]), true);
  // 有起有落 ⇒ 计数类，必须走含 0 的轴（不含 0 会把 300→400 画成暴涨）
  assert.equal(isCumulative([300, 420, 310]), false, '有起有落的计数被判成累计');
  assert.equal(isCumulative([2, 1]), false);
  // 口径必须真接在渲染上，否则「判对了」和「画对了」之间还隔着一行
  assert.equal(code.split('isCumulative(').length - 1, 1, 'cumulative 不是由 isCumulative 算的');
  assert.match(code, /zeroBased=\{!cumulative\}/, 'zeroBased 没接上累计判据（取反写反 = 轴口径整个倒过来）');
});

test('图标回落：认不出的 icon 落 FALLBACK_ICON 而非空块（后端加图标不该让卡片缺一角）', () => {
  assert.deepEqual(Object.keys(ICONS), ['people', 'person_add', 'bolt', 'description'], '映射表被改过（后端给的就是这四个名字）');
  for (const [name, glyph] of Object.entries(ICONS)) {
    assert.equal(iconFor(name), glyph, `${name} 的图标变了`);
    assert.notEqual(glyph, FALLBACK_ICON, `${name} 与兜底字形同形，回落就看不出来了`);
  }
  // 没见过的名字 / 字段缺失 / 类型不对：一律落兜底字形，且**非空**（空串就是「空一块」）
  for (const weird of ['new_icon_from_backend', '', undefined, null, 42, {}, []]) {
    assert.equal(iconFor(weird), FALLBACK_ICON, `${tag(weird)} 没落兜底字形`);
    assert.notEqual(iconFor(weird), '', `${tag(weird)} 落成了空串（卡片缺一角）`);
  }
  // 映射表抽出去之后必须真被调用点用上：否则上面全绿，卡片上照样没有图标
  assert.equal(code.split('iconFor(').length - 1, 1, 'Stats 没调 iconFor（映射表挂空了）');
});

test('列序：显式给死 8 列且 user_name 在第 2 列 —— 裸顺序 + 8 列上限正好切掉「谁干的」', () => {
  assert.deepEqual(
    LOG_COLUMNS.map((column) => column.key),
    ['id', 'user_name', 'action', 'method', 'path', 'ip', 'source', 'created_at'],
  );
  // 「位置」比「在不在」更贴近这条的理由：响应键序里 user_name 排第 9，正好被 8 列上限切掉
  assert.equal(LOG_COLUMNS[1].key, 'user_name', 'user_name 不在第 2 列（挪到后面就会被上限切掉）');
  assert.equal(LOG_COLUMNS.length, 8);
  // input 是一整段 JSON 请求参数（cell 会截成 48 字），摘要表里不要它
  assert.ok(!LOG_COLUMNS.some((column) => column.key === 'input'), 'input 又回到摘要表里了');
  // 每列都挂词条键：写成字段名原文（label: 'user_name'）会让表头露裸键，13 种语言下都是它
  for (const column of LOG_COLUMNS) {
    assert.match(column.label, /^f\.[a-z_]+$/, `${column.key} 的标签不是 f.* 词条键`);
  }
});
