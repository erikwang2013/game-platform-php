/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 权限树的纯逻辑：解析 / 级联勾选 / 半选判定 / 摊平成表行。
 * 不 import React、不碰 DOM，供 node --test 直接覆盖（与 lib/crud.ts 同款）。
 *
 * 后端形状（PermissionController::buildTree，实测）：`data` 就是节点数组（无 list/total 包装、不分页）；
 * `id`/`parent_id` 都是 hashid，但**根节点的 parent_id 是数字 0**；`children` **只在非空时出现**，
 * 叶子没有这个键（不是 []）。故解析一律走 nodeOf()，两处都不假设 —— 拿 `children.length` 去读叶子会炸。
 */
import type { Row } from '../components/DataTable';
import type { FieldOption } from './crud';

export type TreeNode = { id: string; label: string; slug: string; children: TreeNode[] };

/** 一个节点：取不到 id 就丢弃（没有 id 的节点既勾不了也存不了）。 */
function nodeOf(raw: unknown): TreeNode | null {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const row = raw as Record<string, unknown>;
  const id = String(row.id ?? row.hashid ?? '').trim();
  if (id === '') return null;
  // children 只在非空时出现：Array.isArray 为假（含 undefined）即叶子
  const kids = Array.isArray(row.children) ? (row.children as unknown[]) : [];
  return {
    id,
    label: String(row.name ?? row.title ?? row.slug ?? id),
    slug: String(row.slug ?? ''),
    children: kids.map(nodeOf).filter((node): node is TreeNode => node !== null),
  };
}

/** 响应 → 树。认不出（不是数组）就当空树，不猜别的键 —— 猜错会给出看不见的勾选项。 */
export const treeNodes = (data: unknown): TreeNode[] =>
  Array.isArray(data) ? data.map(nodeOf).filter((node): node is TreeNode => node !== null) : [];

/**
 * 摊平成下拉选项：`父 / 名（slug）`（带完整路径，各模块都有叫「列表」的节点，只给 name 分不出是哪一个）。
 * 供权限表单的「父权限」下拉用；角色的「权限」走树控件（见 components/PermissionTree.tsx），不摊平。
 */
export function treeOptions(nodes: TreeNode[]): FieldOption[] {
  const out: FieldOption[] = [];
  const walk = (list: TreeNode[], prefix: string): void => {
    for (const node of list) {
      // 键按**形状**分两种（带 slug / 不带），路径与 slug 走 params：本函数在渲染期被调，
      // 但拼好的成品文案会冻在拼它的那一刻的语言上（同 lib/crud.ts 的 FieldOption.params）
      out.push({
        value: node.id,
        label: node.slug === '' ? 'form.permission_option_noslug' : 'form.permission_option',
        params: { path: `${prefix}${node.label}`, slug: node.slug },
      });
      walk(node.children, `${prefix}${node.label} / `);
    }
  };
  walk(nodes, '');
  return out;
}

/** 勾选值（换行分隔的 hashid 串，与 multi 字段同形）↔ 数组。 */
export const parseIds = (value: string): string[] =>
  value
    .split('\n')
    .map((id) => id.trim())
    .filter((id) => id !== '');

export const joinIds = (ids: Iterable<string>): string => [...ids].join('\n');

/**
 * 勾选集合的闭包：显式勾中的、以及「子级全勾」的父级都算勾中；父级勾中则其子级全勾。
 * 界面显示的勾选态与保存时提交的值都以闭包为准 —— 否则「父勾子不勾」这种半截状态存进库里，
 * 下次进来又是一棵自相矛盾的树（角色端点是整表替换 sync，存什么就是什么）。
 */
export function conduct(nodes: TreeNode[], selected: Iterable<string>): Set<string> {
  const sel = new Set(selected);
  const out = new Set<string>();
  // 勾父 ⇒ 子级全勾
  const push = (node: TreeNode): void => {
    for (const kid of node.children) {
      out.add(kid.id);
      push(kid);
    }
  };
  // 后序：先算子级，子级全勾 ⇒ 本节点勾（叶子只看显式勾选）
  const lift = (node: TreeNode): boolean => {
    const full = node.children.length > 0 && node.children.map(lift).every(Boolean);
    const on = sel.has(node.id) || full;
    if (!on) return false;
    out.add(node.id);
    push(node);
    return true;
  };
  nodes.forEach(lift);
  return out;
}

/** id → 节点 + 祖先链（从根到父）。 */
function locate(nodes: TreeNode[], id: string): { node: TreeNode; ancestors: TreeNode[] } | null {
  const walk = (list: TreeNode[], ancestors: TreeNode[]): { node: TreeNode; ancestors: TreeNode[] } | null => {
    for (const node of list) {
      if (node.id === id) return { node, ancestors };
      const hit = walk(node.children, [...ancestors, node]);
      if (hit) return hit;
    }
    return null;
  };
  return walk(nodes, []);
}

/**
 * 勾 / 取消一个节点，返回新的勾选集合（调用方用 parseIds/joinIds 往返字符串）。
 * 以「界面所见」为基准：先按闭包算出当前看到的勾选，再整棵子树勾上/去掉，最后自底向上重算祖先 ——
 * 子级全勾 ⇒ 父级勾，否则父级不勾（部分勾的父级显示半选；半选不进集合，由 checkState 现算，
 * 免得存出「父勾子不勾」）。
 */
export function toggle(nodes: TreeNode[], selected: Iterable<string>, id: string, on: boolean): Set<string> {
  const next = conduct(nodes, selected);
  const hit = locate(nodes, id);
  if (!hit) return next;
  const mark = (node: TreeNode): void => {
    if (on) next.add(node.id);
    else next.delete(node.id);
    node.children.forEach(mark);
  };
  mark(hit.node);
  // 近的祖先先算，逐层向上；顺序其实无关（只看子级的最终状态），但从近到远读起来是「往上传导」
  for (const ancestor of [...hit.ancestors].reverse()) {
    if (ancestor.children.every((kid) => next.has(kid.id))) next.add(ancestor.id);
    else next.delete(ancestor.id);
  }
  return next;
}

export type CheckState = 'on' | 'half' | 'off';

/** 三态：勾中 / 半选（自己没勾但子树里有勾中的）/ 未勾。conducted 传 conduct() 的结果（整棵树只算一次）。 */
export function checkState(node: TreeNode, conducted: ReadonlySet<string>): CheckState {
  if (conducted.has(node.id)) return 'on';
  const anyDescendant = (list: TreeNode[]): boolean =>
    list.some((kid) => conducted.has(kid.id) || anyDescendant(kid.children));
  return anyDescendant(node.children) ? 'half' : 'off';
}

/** 摊平后的表行：除原字段外补 `parent_name`（表格列）与 `__depth`/`__kids`/`__lineage`（树标记，见 columnsFrom 的前缀过滤）。 */
export type TreeRow = Row & { parent_name: string; __depth: number; __kids: number; __lineage: string[] };

/**
 * 树 → 表行：children 递归展开成平铺行（不展开的话子节点根本不出现在表里，也就改不到、删不到）。
 * 每行补父名：`parent_id` 在界面上对不上任何一行（根是数字 0，其余是 hashid 但表里不显示），
 * 父名在展开时天然在手上。顶层也给空串（显示成「—」）：列是按**首行**字段推导的。
 * `__lineage` 是祖先 id 链，供 visibleTreeRows 判「祖先有没有被折叠」。
 * 不就地改传入的响应（改了会污染 useApi 手里的数据，下次渲染还是旧值）。
 */
export function treeRows(rows: Row[], childrenKey = 'children'): TreeRow[] {
  const out: TreeRow[] = [];
  const walk = (list: Row[], depth: number, parentName: string, lineage: string[]): void => {
    for (const row of list) {
      const { [childrenKey]: children, ...rest } = row;
      const kids = Array.isArray(children) ? (children as Row[]) : [];
      const id = String(row.id ?? row.hashid ?? '');
      out.push({ ...rest, parent_name: parentName, __depth: depth, __kids: kids.length, __lineage: lineage });
      walk(kids, depth + 1, String(row.name ?? ''), [...lineage, id]);
    }
  };
  walk(rows, 0, '', []);
  return out;
}

/** 只显示祖先都没被折叠的行（折叠态是个集合，缺省空集 = 全展开）。 */
export const visibleTreeRows = (rows: TreeRow[], collapsed: ReadonlySet<string>): TreeRow[] =>
  rows.filter((row) => row.__lineage.every((id) => !collapsed.has(id)));
