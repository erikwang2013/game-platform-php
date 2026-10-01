/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Row } from './api.service';
import { idOf } from './render';

/**
 * 权限树（GET /admin/v1/permission）的纯逻辑：展开/折叠、三态判定、父子联动。
 *
 * 后端回的 `data` 就是节点数组本身（没有 list/total、**不分页**），根节点的 `parent_id` 是
 * **数字 0**（不是 hashid），**叶子没有 `children` 键**（不是空数组）——两处都别按教科书形状写，
 * 容错在 toTree 里。放 core/ 而不是页面里：这些都是纯函数，spec 直接钉。
 */
export interface PNode {
  /** hashid（后端只认它；裸 BIGINT 会被 decodeParentId/decodePermissionIds 判 400） */
  id: string;
  name: string;
  /** 原始行：列表要展示 slug/type/path/sort，编辑/删除要按 idOf(row) 取 hashid */
  row: Row;
  children: PNode[];
}

export interface FlatNode {
  node: PNode;
  /** 从 0 起的层级（缩进、选项前缀都用它） */
  depth: number;
  /** 父节点名；根节点为空串（调用方决定显示成「（根）」还是留白） */
  parent: string;
  hasKids: boolean;
}

export type CheckState = 'on' | 'half' | 'off';

/** 原始节点数组 → 规范树。children 非数组（含缺键 = 叶子）⇒ 空；没有 hashid 的行丢弃 */
export function toTree(rows: unknown): PNode[] {
  if (!Array.isArray(rows)) return [];
  const out: PNode[] = [];
  for (const raw of rows) {
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) continue;
    const row = raw as Row;
    // 没有 hashid 的节点勾不了、也提交不上去（后端要 hashid）——丢掉，别渲染成一个假节点
    const id = idOf(row);
    if (!id) continue;
    out.push({ id, name: String(row['name'] ?? id), row, children: toTree(row['children']) });
  }
  return out;
}

/**
 * DFS 展开成平铺行。collapsed 命中的节点**自己仍在**（箭头还要画、还能再展开），
 * 只是它的子树整段不展开 —— 平铺 + 折叠比递归渲染少一层自引用组件。
 */
export function flatten(nodes: PNode[], collapsed?: ReadonlySet<string>): FlatNode[] {
  const out: FlatNode[] = [];
  const walk = (list: PNode[], depth: number, parent: string): void => {
    for (const n of list) {
      out.push({ node: n, depth, parent, hasKids: n.children.length > 0 });
      if (n.children.length && !collapsed?.has(n.id)) walk(n.children, depth + 1, n.name);
    }
  };
  walk(nodes, 0, '');
  return out;
}

/**
 * 每个节点的勾选态。
 * on   = 自己勾了**且**子节点全勾（叶子只看自己）；
 * half = 没满但自己或某个后代勾了（含「自己勾了、子节点没勾满」这种存量值，见 toggle 的注释）。
 * 自底向上一次算完：父级要等子节点结果，不能先判自己。
 */
export function checkStates(nodes: PNode[], sel: ReadonlySet<string>): Map<string, CheckState> {
  const out = new Map<string, CheckState>();
  const walk = (n: PNode): CheckState => {
    const kids = n.children.map(walk);
    const self = sel.has(n.id);
    const st: CheckState =
      self && kids.every((k) => k === 'on')
        ? 'on'
        : self || kids.some((k) => k !== 'off')
          ? 'half'
          : 'off';
    out.set(n.id, st);
    return st;
  };
  for (const n of nodes) walk(n);
  return out;
}

/** 根 → 该节点的整条链（含自身）；不在树里返回空 */
function pathTo(nodes: PNode[], id: string): PNode[] {
  for (const n of nodes) {
    if (n.id === id) return [n];
    const sub = pathTo(n.children, id);
    if (sub.length) return [n, ...sub];
  }
  return [];
}

/**
 * 点一个节点 → 新的勾选集合（不可变，页面/组件拿它 set 进 signal）。
 * - 当前是 on ⇒ 取消整棵子树；否则勾上整棵子树（半选的点一下就勾满，各家树控件同款）。
 * - 再沿**这条链**收敛祖先：子节点全勾 ⇒ 祖先也勾上，否则取消祖先。
 *   只动这条链，别的分支原样保留 —— 用户没碰过的部分不该被顺手改写。
 *
 * 注意「取消祖先」会丢掉一种存量值：某角色只有父节点没有子节点（后端 sync 只存提交过的 id，
 * 老的多选界面可以勾出这种组合）。它渲染成 half，提交时由组件补一条 hidden input 兜住（见
 * tree-select），所以这里丢掉的那份会由 hidden 项带回；**别把这条收敛改成不动祖先**，
 * 否则「取消子权限后父级仍勾着」会让界面与提交值对不上。
 */
export function toggle(nodes: PNode[], sel: ReadonlySet<string>, id: string): Set<string> {
  const path = pathTo(nodes, id);
  const node = path[path.length - 1];
  if (!node) return new Set(sel);
  const turnOn = checkStates(nodes, sel).get(id) !== 'on';
  const next = new Set(sel);
  const apply = (n: PNode): void => {
    if (turnOn) next.add(n.id);
    else next.delete(n.id);
    for (const c of n.children) apply(c);
  };
  apply(node);
  const st = checkStates(nodes, next);
  for (let i = path.length - 2; i >= 0; i--) {
    const a = path[i]!;
    if (a.children.every((c) => st.get(c.id) === 'on')) next.add(a.id);
    else next.delete(a.id);
  }
  return next;
}
