/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, input, output, signal } from '@angular/core';
import { Row } from '../core/api.service';
import { T } from '../core/i18n/i18n';
import { idOf } from '../core/render';
import { colsOf, dash, num } from '../core/util';

export interface Act {
  key: string;
  /** i18n 键或字面量（查不到原样显示）；渲染时过 `| t` */
  label: string;
  danger?: boolean;
  /**
   * 该行是否出这个按钮（缺省 = 恒出）。用于「按钮只对某些状态的行有意义」的行内动作：
   * 反作弊审核每种状态各自的按钮、团伙三种状态各自一个 —— 状态已经等于目标的那一条上再摆
   * 一个按钮，点下去是把同一状态再写一遍（后端照收，界面在骗人）。
   */
  when?: (row: Row) => boolean;
}

/**
 * 通用数据表。列 = heads 的键序（给了 heads 就按它，没给则从数据里推）。
 * 后端结构未确认，所以单元格一律 dash() 防御性渲染。
 */
@Component({
  selector: 'ui-table',
  imports: [T],
  template: `
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            @if (selectable()) {
              <th class="pick">{{ 'table.pick' | t }}</th>
            }
            @for (c of cols(); track c.key) {
              <th>{{ c.label }}</th>
            }
            @if (actions().length) {
              <th>{{ 'table.actions' | t }}</th>
            }
          </tr>
        </thead>
        <tbody>
          @for (r of view(); track $index) {
            <tr [class.clickable]="clickable()" (click)="pick.emit(r.row)">
              @if (selectable()) {
                <td class="pick">
                  <input
                    type="checkbox"
                    [attr.aria-label]="'table.pick' | t"
                    [checked]="checked().includes(idOf(r.row))"
                    [disabled]="!can(r.row)"
                    (click)="$event.stopPropagation()"
                    (change)="hit(r.row)"
                  />
                </td>
              }
              @for (c of cols(); track c.key) {
                <td [class.num]="isNum(r.row[c.key])">
                  @if (c.key === treeKey()) {
                    <span class="tree-cell" [style.paddingLeft.px]="r.depth * 18">
                      <!-- 箭头对整行可见（有子节点就画），但折叠只藏子树、不藏自己 —— 还能再展开 -->
                      @if (r.kids) {
                        <button type="button" class="tree-arrow" (click)="fold($event, r.row)">
                          {{ folded().has(idOf(r.row)) ? '▶' : '▼' }}
                        </button>
                      }
                      {{ dash(r.row[c.key]) }}
                    </span>
                  } @else {
                    {{ dash(r.row[c.key]) }}
                  }
                </td>
              }
              @if (actions().length) {
                <td class="acts">
                  @for (a of acts(r.row); track a.key) {
                    <button class="btn" [class.danger]="a.danger" (click)="fire($event, r.row, a.key)">
                      {{ a.label | t }}
                    </button>
                  }
                </td>
              }
            </tr>
          } @empty {
            <tr>
              <td
                class="state"
                [attr.colspan]="cols().length + (actions().length ? 1 : 0) + (selectable() ? 1 : 0)"
              >
                {{ 'app.no_data' | t }}
              </td>
            </tr>
          }
        </tbody>
      </table>
    </div>
  `,
})
export class Table {
  readonly rows = input<Row[]>([]);
  /** 列顺序 + 表头（值是 i18n 键或字面量 —— 查不到原样显示）；留空则自动推导 */
  readonly heads = input<Record<string, string>>({});
  readonly actions = input<Act[]>([]);
  readonly clickable = input(false);
  /**
   * 树形列（列键）：该列渲染成「缩进 + 展开箭头」。行的 `depth` 字段是层级（从 0 起），
   * 且必须按 DFS 序排（权限页签由 core/tree 的 flatten 产出）；折叠状态组件自持，
   * 折叠只影响显示，`rows` 始终是整棵树 —— 重新取数不会重置展开状态。
   */
  readonly treeKey = input('');

  readonly pick = output<Row>();
  readonly act = output<{ row: Row; key: string }>();

  /**
   * 勾选列（表格级批量动作的入参）。缺省 false：绝大多数页没有批量端点，不摆复选框。
   * 勾选态由**父组件受控**（`checked` 进、`picked` 出）—— 批量提交成功后要清空勾选，
   * 状态藏在表里的话父组件清不掉，界面上会留着一排已勾但已生效的框。
   */
  readonly selectable = input(false);
  /** 已勾选的 id（`idOf(row)`）。父组件持有，清空即全部取消勾选 */
  readonly checked = input<readonly string[]>([]);
  /** 这一行能不能被勾选（缺省全部可勾）。管理员页拿它挡「批量停用自己」 */
  readonly pickable = input<(row: Row) => boolean>(() => true);

  readonly picked = output<string[]>();

  /** 模板作用域只认类成员，模块级 import 不可见 */
  protected readonly dash = dash;
  protected readonly idOf = idOf;

  /** 折叠的节点 id（缺省全展开） */
  protected readonly folded = signal<ReadonlySet<string>>(new Set());

  protected readonly cols = computed(() => colsOf(this.heads(), this.rows()));

  /**
   * 实际渲染的行。非树形表就是原样一行不多、一行不少（depth 缺省 0 ⇒ kids 恒 false）。
   * 树形表：被折叠节点盖住的整段（depth 大于它）跳过；某行有没有子节点看**下一行的 depth**，
   * 不比当前行深就是叶子 —— DFS 序保证后代紧跟其后，省掉在行上再挂一个 has_children 字段。
   */
  protected readonly view = computed(() => {
    const all = this.rows();
    const folded = this.folded();
    const out: { row: Row; depth: number; kids: boolean }[] = [];
    let hideBelow = -1;
    for (let i = 0; i < all.length; i++) {
      const row = all[i]!;
      const depth = num(row['depth']);
      if (hideBelow >= 0 && depth > hideBelow) continue;
      hideBelow = -1;
      const next = all[i + 1];
      out.push({ row, depth, kids: !!next && num(next['depth']) > depth });
      if (folded.has(idOf(row))) hideBelow = depth;
    }
    return out;
  });

  protected fold(ev: Event, row: Row): void {
    ev.stopPropagation(); // 别把点箭头当成点行（行点击开详情）
    const id = idOf(row);
    if (!id) return;
    this.folded.update((s) => {
      const next = new Set(s);
      if (!next.delete(id)) next.add(id);
      return next;
    });
  }

  protected isNum(v: unknown): boolean {
    return typeof v === 'number';
  }

  /** 该行是否可勾选（无 id 的行勾不了：提交的是 id 数组，勾了也发不出去） */
  protected can(row: Row): boolean {
    return !!idOf(row) && this.pickable()(row);
  }

  /** 勾/取消勾：值在集合里就删，否则加（顺序即勾选顺序） */
  protected hit(row: Row): void {
    const id = idOf(row);
    if (!id || !this.can(row)) return;
    const next = this.checked().slice();
    const at = next.indexOf(id);
    if (at >= 0) next.splice(at, 1);
    else next.push(id);
    this.picked.emit(next);
  }

  /** 该行实际出哪些动作（act.when 缺省即恒出） */
  protected acts(row: Row): Act[] {
    return this.actions().filter((a) => !a.when || a.when(row));
  }

  protected fire(ev: Event, row: Row, key: string): void {
    ev.stopPropagation();
    this.act.emit({ row, key });
  }
}
