/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 权限树控件（批次 6）：递归渲染 + 展开/折叠 + 三态勾选（勾 / 半选 / 未勾）+ 父子联动。
 *
 * 契约口径（后端 PermissionController::buildTree 与 RoleController 的真值）：
 *  1. `children` 键**只在非空时出现**（叶子没有这个键，不是 `[]`）⇒ 一律走 childrenOf() 兜底。
 *  2. 根节点的 `parent_id` 是字面量 int 0（hashid 编不出 0）；非根是 hashid。
 *     本文件**不用 parent_id**：树已经是嵌套结构，父子关系从 children 走，父链由索引现场重建。
 *  3. 值一律是**勾中节点的 hashid**：RoleController 的 permission_ids 就是 hashid 数组，
 *     半选的祖先**不进集合**（半选只表示「后代里有勾中的」，它自己没被授权）。
 *
 * 逻辑（级联 / 半选判定 / 可见行展平）都写成纯函数，组件只做渲染与事件转发 ——
 * 页面（ResourceManagePage 对权限模块的行渲染）与表单（FormDialog 的 tree 字段）各取所需。
 *
 * ArkTS 限制（本树实测表，见 memory arkts-restrictions-and-compile-harness）：
 *  索引容器用 Map 而不是 `Record<K, Node>` —— Record 的索引读取在类型上不返回 undefined，
 *  「节点不存在」这条分支写不出来；对象字面量只允许赋给**声明过的 interface**（TreeIndex）。
 */

import { PermissionNode } from '../model/DataModels';

/** 勾选态：全选 / 半选（后代里有勾中的，自己没勾）/ 未选 */
export const CHECK_NONE: number = 0;
export const CHECK_ALL: number = 1;
export const CHECK_PART: number = 2;

/** 树的一行（按展开状态可见的行，深度优先序） */
export interface PermissionTreeRow {
  id: string;
  name: string;
  slug: string;
  type: number;
  /** 层级，0 = 根 */
  depth: number;
  hasChildren: boolean;
  expanded: boolean;
  checkState: number;
}

/** id → 节点 / id → 父 id 两张索引（父子联动要往上走；根的父 id 记空串） */
interface TreeIndex {
  byId: Map<string, PermissionNode>;
  parentById: Map<string, string>;
}

/** 叶子没有 children 键，故每处取子节点都必须过这里 */
function childrenOf(node: PermissionNode): PermissionNode[] {
  return node.children ?? [];
}

function buildIndex(nodes: PermissionNode[], parentId: string, byId: Map<string, PermissionNode>,
  parentById: Map<string, string>): void {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    byId.set(node.id, node);
    parentById.set(node.id, parentId);
    buildIndex(childrenOf(node), node.id, byId, parentById);
  }
}

/** 后代 hashid（不含自身）—— 勾父即勾全枝 */
export function descendantIds(node: PermissionNode): string[] {
  const ids: string[] = [];
  const kids = childrenOf(node);
  for (let i = 0; i < kids.length; i++) {
    ids.push(kids[i].id);
    const deeper = descendantIds(kids[i]);
    for (let j = 0; j < deeper.length; j++) {
      ids.push(deeper[j]);
    }
  }
  return ids;
}

/**
 * 勾选态。全选 = 自身与全部后代都在集合里；半选 = 子树里有勾中的（含「自己在但后代不全」）；
 * 未选 = 一个都没有。半选的节点**不在集合里**（它没被授权，只是后代有），与提交值同一口径。
 */
export function checkStateOf(checked: string[], node: PermissionNode): number {
  const self = checked.indexOf(node.id) >= 0;
  const kids = childrenOf(node);
  if (kids.length === 0) {
    return self ? CHECK_ALL : CHECK_NONE;
  }
  let all = self;
  let any = self;
  for (let i = 0; i < kids.length; i++) {
    const childState = checkStateOf(checked, kids[i]);
    if (childState !== CHECK_ALL) {
      all = false;
    }
    if (childState !== CHECK_NONE) {
      any = true;
    }
  }
  if (all) {
    return CHECK_ALL;
  }
  return any ? CHECK_PART : CHECK_NONE;
}

/**
 * 勾/取消一个节点：先整枝加/减（父子联动向下），再沿父链向上补：
 * 任一祖先的**每个**子节点都被勾中 ⇒ 该祖先也进集合，否则移出集合。
 * 往上走是自下而上的，故「孙子全勾 ⇒ 儿子补上 ⇒ 儿子的兄弟也全 ⇒ 根补上」一趟走完。
 * 返回新数组（不改入参）；id 不在树里（理论不可达）原样返回。
 */
export function toggleCheck(nodes: PermissionNode[], checked: string[], id: string, on: boolean): string[] {
  const byId: Map<string, PermissionNode> = new Map();
  const parentById: Map<string, string> = new Map();
  buildIndex(nodes, '', byId, parentById);
  const node = byId.get(id);
  if (node === undefined) {
    return checked;
  }
  const affected: string[] = [node.id].concat(descendantIds(node));
  const next: string[] = [];
  for (let i = 0; i < checked.length; i++) {
    if (affected.indexOf(checked[i]) < 0) {
      next.push(checked[i]);
    }
  }
  if (on) {
    for (let i = 0; i < affected.length; i++) {
      next.push(affected[i]);
    }
  }
  let cursor = parentById.get(node.id) ?? '';
  while (cursor.length > 0) {
    const parent = byId.get(cursor);
    if (parent === undefined) {
      break;
    }
    const kids = childrenOf(parent);
    let allKids = kids.length > 0;
    for (let i = 0; i < kids.length; i++) {
      if (next.indexOf(kids[i].id) < 0) {
        allKids = false;
        break;
      }
    }
    const at = next.indexOf(cursor);
    if (allKids && at < 0) {
      next.push(cursor);
    } else if (!allKids && at >= 0) {
      next.splice(at, 1);
    }
    cursor = parentById.get(cursor) ?? '';
  }
  return next;
}

/** 待渲染的行（折叠节点的整枝不产出）；纯函数，展开/勾选状态由调用方持有 */
export function flattenTree(nodes: PermissionNode[], expanded: string[], checked: string[]): PermissionTreeRow[] {
  const rows: PermissionTreeRow[] = [];
  appendRows(nodes, 0, expanded, checked, rows);
  return rows;
}

function appendRows(nodes: PermissionNode[], depth: number, expanded: string[],
  checked: string[], rows: PermissionTreeRow[]): void {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    const kids = childrenOf(node);
    const open = expanded.indexOf(node.id) >= 0;
    const row: PermissionTreeRow = {
      id: node.id,
      name: node.name,
      slug: node.slug,
      type: node.type,
      depth: depth,
      hasChildren: kids.length > 0,
      expanded: open,
      checkState: checkStateOf(checked, node)
    };
    rows.push(row);
    if (kids.length > 0 && open) {
      appendRows(kids, depth + 1, expanded, checked, rows);
    }
  }
}

/**
 * 打开时的默认展开集：根层一律展开 + 任何「子树里有勾中项」的节点全部展开。
 * 不展开祖先的话，回填的权限全在折叠枝里，运营看不到自己改之前勾了什么。
 */
export function initialExpanded(nodes: PermissionNode[], checked: string[]): string[] {
  const out: string[] = [];
  markExpanded(nodes, checked, 0, out);
  return out;
}

function markExpanded(nodes: PermissionNode[], checked: string[], depth: number, out: string[]): boolean {
  let anyChecked = false;
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    const kids = childrenOf(node);
    let hit = checked.indexOf(node.id) >= 0;
    if (kids.length > 0) {
      const childHit = markExpanded(kids, checked, depth + 1, out);
      hit = hit || childHit;
      if (childHit || depth === 0) {
        out.push(node.id);
      }
    }
    anyChecked = anyChecked || hit;
  }
  return anyChecked;
}

/**
 * 权限树多选（勾中值为 hashid 数组）。
 *
 * 单向数据流：**勾选态是 @Prop**，真值源在父组件（表单的 FormValues 逗号串）；
 * 本组件只算「点了之后该是哪些 id」并把全量结果交给 onChange，自己不另存一份 ——
 * 两份状态各自演化是这类控件最容易出的错（显示与提交值悄悄不一致）。
 * 只有 `expanded` 是本地状态（纯展示，丢了也只是重新折起来，不影响提交值）。
 */
@Component
export struct PermissionTreeView {
  /** 整棵树（响应原样；children 键只在非空时出现） */
  nodes: PermissionNode[] = [];
  /** 当前勾选（父组件的表单值，单向流入） */
  @Prop checked: string[] = [];
  /** 禁用（提交中）：不响应点击 */
  @Prop disabled: boolean = false;
  /** 勾选变化：回传**全量勾中的 hashid**（含父子联动补上的祖先） */
  onChange?: (checked: string[]) => void;

  @State expanded: string[] = [];

  aboutToAppear(): void {
    this.expanded = initialExpanded(this.nodes, this.checked);
  }

  private toggleRow(id: string): void {
    if (this.disabled) {
      return;
    }
    const on = this.checked.indexOf(id) < 0;
    const next = toggleCheck(this.nodes, this.checked, id, on);
    // 勾上折叠枝里的节点在界面上是看不见的：顺手把它展开
    if (on && this.expanded.indexOf(id) < 0) {
      const node = this.findNode(this.nodes, id);
      if (node !== null && childrenOf(node).length > 0) {
        this.expanded = this.expanded.concat(id);
      }
    }
    if (this.onChange) {
      this.onChange(next);
    }
  }

  private toggleExpand(id: string): void {
    const at = this.expanded.indexOf(id);
    if (at >= 0) {
      const next = this.expanded.slice();
      next.splice(at, 1);
      this.expanded = next;
    } else {
      this.expanded = this.expanded.concat(id);
    }
  }

  private findNode(nodes: PermissionNode[], id: string): PermissionNode | null {
    for (let i = 0; i < nodes.length; i++) {
      if (nodes[i].id === id) {
        return nodes[i];
      }
      const deeper = this.findNode(childrenOf(nodes[i]), id);
      if (deeper !== null) {
        return deeper;
      }
    }
    return null;
  }

  /** 勾选框：ArkUI 的 Checkbox 只有两态，半选自己画一个（勾 / 半选横杠 / 空） */
  @Builder
  checkBox(row: PermissionTreeRow) {
    Stack() {
      Text(row.checkState === CHECK_ALL ? '✓' : (row.checkState === CHECK_PART ? '−' : ''))
        .fontSize(12)
        .fontColor(Color.White)
    }
    .width(18)
    .height(18)
    .borderRadius(3)
    .backgroundColor(row.checkState === CHECK_NONE ? '#FFFFFF' : '#1677FF')
    .border({ width: 1, color: row.checkState === CHECK_NONE ? '#CCCCCC' : '#1677FF' })
    .onClick(() => {
      this.toggleRow(row.id);
    })
  }

  build() {
    Column() {
      ForEach(flattenTree(this.nodes, this.expanded, this.checked), (row: PermissionTreeRow) => {
        Row() {
          if (row.hasChildren) {
            Text(row.expanded ? '▾' : '▸')
              .fontSize(12)
              .fontColor('#999999')
              .width(20)
              .textAlign(TextAlign.Center)
              .onClick(() => {
                this.toggleExpand(row.id);
              })
          } else {
            // 占位：叶子的勾选框与父节点标题左对齐（缩进只加在行容器上）
            Text('')
              .width(20)
          }

          this.checkBox(row)

          Text(row.name)
            .fontSize(13)
            .fontColor('#333333')
            .layoutWeight(1)
            .margin({ left: 6 })
            .onClick(() => {
              this.toggleRow(row.id);
            })
        }
        .width('100%')
        .height(32)
        // 缩进加在行容器上（ForEach 没有 width/padding 这类属性，必须挂在行里）
        .padding({ left: row.depth * 14, right: 4 })
        .alignItems(VerticalAlign.Center)
      }, (row: PermissionTreeRow) => `${row.id}:${row.checkState}:${row.expanded}`)
    }
    .width('100%')
  }
}
