/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { t, setCode } from '../i18n/index.ts';
import { checkState, conduct, joinIds, parseIds, toggle, treeNodes, treeOptions, treeRows, visibleTreeRows } from './tree.ts';

/**
 * 权限树的纯逻辑。所有断言都照后端**实测形状**写（PermissionController::buildTree 的结果）：
 * 根节点 `parent_id` 是数字 0、叶子**没有** children 键 —— 用 [] 当叶子会让「拿 children.length 判空」
 * 这类写法在生产形状上崩掉。
 */

/** 与响应同形的裸行（交叉 Record 是为了能直接喂给 treeRows 的 Row[]）。 */
type Raw = Record<string, unknown> & { id: string; parent_id: number | string; name: string; slug: string; children?: Raw[] };

/** A(用户) ├ B(列表) ├ C(导出) └ F(另存) ｜ D(详情) ｜ E(游戏) —— B 有两个子级，半选才测得出来 */
const RESPONSE: Raw[] = [
  {
    id: 'A',
    parent_id: 0,
    name: '用户',
    slug: 'user',
    children: [
      {
        id: 'B',
        parent_id: 'A',
        name: '列表',
        slug: 'user.list',
        children: [
          { id: 'C', parent_id: 'B', name: '导出', slug: 'user.export' },
          { id: 'F', parent_id: 'B', name: '另存', slug: 'user.save' },
        ],
      },
      { id: 'D', parent_id: 'A', name: '详情', slug: '' },
    ],
  },
  { id: 'E', parent_id: 0, name: '游戏', slug: 'game' },
];

const TREE = treeNodes(RESPONSE);
const [A, E] = TREE;
const [B, D] = A.children;
const [C, F] = B.children;

const ids = (set: Set<string>): string[] => [...set].sort();

test('treeNodes：叶子没有 children 键、根 parent_id 是数字 0，都照收；认不出就当空树', () => {
  assert.deepEqual(
    TREE.map((node) => [node.id, node.label, node.slug, node.children.length]),
    [
      ['A', '用户', 'user', 2],
      ['E', '游戏', 'game', 0],
    ],
  );
  assert.deepEqual(B.children.map((node) => node.id), ['C', 'F']);
  // 叶子的 children 是解析补的空数组（原响应里没有这个键，不是 []）
  assert.ok(!('children' in RESPONSE[0].children![1]!));
  assert.equal(D.children.length, 0);
  // 形状不认识（信封没解成数组）⇒ 空树，别去猜 data.list/data.items
  assert.deepEqual(treeNodes({ list: [{ id: 'A' }] }), []);
  assert.deepEqual(treeNodes(null), []);
  // 没有 id 的节点勾不了也存不了，丢弃
  assert.deepEqual(treeNodes([{ name: '孤儿' }, { id: '' }]), []);
});

test('treeOptions：标签带完整父路径与 slug（各模块都有叫「列表」的节点，只给 name 分不出）', () => {
  setCode('en');
  // 这里断言的是**键 + params 渲染出来的英文成品**（不是对照组），因为 treeOptions 返回的就
  // 是「键 + 动态参数」两件套：键换错（比如漏了 noslug 分支）或多带/少带一个参数都会红。
  // 半角括号 `(` 本身就是判据：zh 表用的是全角 `（`，取错语言这里立刻红。
  const render = treeOptions(TREE).map((option) => t(option.label, option.params));
  assert.deepEqual(render, [
    '用户 (user)',
    '用户 / 列表 (user.list)',
    '用户 / 列表 / 导出 (user.export)',
    '用户 / 列表 / 另存 (user.save)',
    '用户 / 详情',
    '游戏 (game)',
  ]);
  // 叶子没有 slug（'' 不是缺省）⇒ 走 noslug 分支，不该拼出空括号
  assert.deepEqual(
    treeOptions(TREE).map((option) => option.label),
    [
      'form.permission_option',
      'form.permission_option',
      'form.permission_option',
      'form.permission_option',
      'form.permission_option_noslug',
      'form.permission_option',
    ],
  );
  assert.deepEqual(treeOptions(TREE).map((option) => option.value), ['A', 'B', 'C', 'F', 'D', 'E']);
  setCode('zh');
});

test('parseIds / joinIds：换行串 ↔ 数组（与 multi 字段同形），空行与空白丢弃', () => {
  assert.deepEqual(parseIds('A\n\n B \n'), ['A', 'B']);
  assert.deepEqual(parseIds(''), []);
  assert.equal(joinIds(['A', 'B']), 'A\nB');
  assert.equal(joinIds(parseIds('A\nB')), 'A\nB');
});

test('conduct：子级全勾 ⇒ 父级自动勾；父级勾 ⇒ 子级全勾', () => {
  // B 的两个子级都勾了 ⇒ B 自动勾；A 还有子级 D 没勾 ⇒ A 不进集合
  assert.deepEqual(ids(conduct(TREE, ['C', 'F'])), ['B', 'C', 'F']);
  // A 的子级全勾（B 与 D）⇒ 一路自动勾上
  assert.deepEqual(ids(conduct(TREE, ['C', 'F', 'D'])), ['A', 'B', 'C', 'D', 'F']);
  // 显式勾父级 ⇒ 子级全被带上（后端存的是「菜单 + 按钮」的组合授权，父勾子不勾在界面上无意义）
  assert.deepEqual(ids(conduct(TREE, ['A'])), ['A', 'B', 'C', 'D', 'F']);
  // 子级只勾了一半 ⇒ 父级不进集合（界面上显示半选，由 checkState 现算）
  assert.deepEqual(ids(conduct(TREE, ['C'])), ['C']);
  assert.deepEqual(ids(conduct(TREE, ['E'])), ['E']);
  // 认不出的 id 原样忽略，不报错
  assert.deepEqual(ids(conduct(TREE, ['不存在'])), []);
});

test('toggle：勾父级连带子级全勾；去掉一个子级会把父级摘掉', () => {
  const on = toggle(TREE, [], 'A', true);
  assert.deepEqual(ids(on), ['A', 'B', 'C', 'D', 'F']);
  // C 去掉 ⇒ B 不再「子级全勾」⇒ B、A 一并摘掉（界面上 A、B 变半选），只剩 D 与 F
  const off = toggle(TREE, on, 'C', false);
  assert.deepEqual(ids(off), ['D', 'F']);
  // 勾回来 ⇒ B（子级全勾）与 A（子级全勾）自动勾上
  assert.deepEqual(ids(toggle(TREE, off, 'C', true)), ['A', 'B', 'C', 'D', 'F']);
  // 勾叶子：父级不满足「全勾」⇒ 只有它自己
  assert.deepEqual(ids(toggle(TREE, [], 'D', true)), ['D']);
  // 认不出的 id：集合按闭包归一后原样返回，不抛
  assert.deepEqual(ids(toggle(TREE, ['C', 'F', 'D'], '不存在', true)), ['A', 'B', 'C', 'D', 'F']);
});

test('toggle：入参是存储态（可能不是闭包）时，先按界面所见归一', () => {
  // 库里存着「父勾子不勾」这种半截状态（旧数据）：确认一下把子级去掉后父级也被摘掉
  assert.deepEqual(ids(toggle(TREE, ['A'], 'D', false)), ['B', 'C', 'F']);
});

test('checkState：勾中 / 半选（子树里有勾中的）/ 未勾', () => {
  const half = conduct(TREE, ['C']);
  assert.equal(checkState(A, half), 'half');
  assert.equal(checkState(B, half), 'half');
  assert.equal(checkState(C, half), 'on');
  assert.equal(checkState(D, half), 'off');
  assert.equal(checkState(E, half), 'off');
  // 同一个父级下的兄弟没被勾 ⇒ 它是未勾，不是半选
  assert.equal(checkState(F, half), 'off');

  const all = conduct(TREE, ['A']);
  assert.equal(checkState(A, all), 'on');
  assert.equal(checkState(C, all), 'on');
  assert.equal(checkState(E, all), 'off');
});

test('treeRows：children 递归展开成平铺行，补父名与树标记，输入不被改动', () => {
  const rows = treeRows(RESPONSE);
  assert.deepEqual(
    rows.map((row) => [row.id, row.parent_name, row.__depth, row.__kids, 'children' in row]),
    [
      ['A', '', 0, 2, false],
      ['B', '用户', 1, 2, false],
      // 孙节点的父名由内层递归给出，不被外层的「用户」覆盖
      ['C', '列表', 2, 0, false],
      ['F', '列表', 2, 0, false],
      ['D', '用户', 1, 0, false],
      ['E', '', 0, 0, false],
    ],
  );
  // 就地改会污染 useApi 手里的数据，下次渲染又是旧值
  assert.equal(RESPONSE[0].children!.length, 2);
  // 自定义 children 键
  assert.deepEqual(
    treeRows([{ id: 'x', name: '父', kids: [{ id: 'y', name: '子' }] }], 'kids').map((row) => [row.id, row.parent_name, row.__kids]),
    [
      ['x', '', 1],
      ['y', '父', 0],
    ],
  );
});

test('visibleTreeRows：折叠一个节点只藏它的子树，别的分支照旧', () => {
  const rows = treeRows(RESPONSE);
  assert.deepEqual(visibleTreeRows(rows, new Set()).map((row) => row.id), ['A', 'B', 'C', 'F', 'D', 'E']);
  // 折叠根：只剩顶层（含被折叠的那一行自己）
  assert.deepEqual(visibleTreeRows(rows, new Set(['A'])).map((row) => row.id), ['A', 'E']);
  // 折叠中间层：只藏它的子树（C、F）
  assert.deepEqual(visibleTreeRows(rows, new Set(['B'])).map((row) => row.id), ['A', 'B', 'D', 'E']);
});
