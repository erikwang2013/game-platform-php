/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, OnInit, computed, input, signal } from '@angular/core';
import { T } from '../core/i18n/i18n';
import { PNode, checkStates, flatten, toggle } from '../core/tree';

/**
 * 树多选（权限树用）：缩进 + 展开箭头 + 三态勾选 + 父子联动，值 = 勾中的 hashid 数组。
 *
 * **提交仍走原生表单**：勾中的节点是一个真的 `<input type="checkbox" [name]>`，form-modal 的
 * fire() 用 `fd.getAll(name)` 收数组 —— 组件自己再存一份提交值就会变成第二个真值源。
 * 三态只影响显示：半选/未勾的 checkbox 不进 FormData（checked=false），这正是要的。
 *
 * 唯一的例外见 hiddenExtra()：存量值里「勾了父节点但子节点没勾满」渲染成 half（checked=false），
 * 光靠 checkbox 提交就会把父节点当成「被取消勾选」静默丢掉 —— 补一条 hidden input 原样带回。
 * 同款理由见 form-modal 的 multi()（当前值置顶补项）。
 *
 * 值只在 ngOnInit 读一次：弹框是非受控的（@if 重建 DOM），打开后再变的是用户的勾选，不是 value。
 */
@Component({
  selector: 'ui-tree-select',
  imports: [T],
  template: `
    <div class="tree-select">
      @for (f of view(); track f.node.id) {
        <div class="tree-row" [style.paddingLeft.px]="f.depth * 18">
          @if (f.hasKids) {
            <button type="button" class="tree-arrow" (click)="fold(f.node.id)">
              {{ folded().has(f.node.id) ? '▶' : '▼' }}
            </button>
          } @else {
            <span class="tree-arrow"></span>
          }
          <label class="tree-label">
            <input
              type="checkbox"
              [attr.name]="name()"
              [value]="f.node.id"
              [checked]="state(f.node.id) === 'on'"
              [indeterminate]="state(f.node.id) === 'half'"
              (change)="pick(f.node.id)"
            />
            <span>{{ f.node.name }}</span>
          </label>
        </div>
      }

      <!-- 半选但确实在集合里（存量值：勾了父级、子级没勾满）：checkbox 不勾选 ⇒ 靠 hidden 带回 -->
      @for (id of hiddenExtra(); track id) {
        <input type="hidden" [attr.name]="name()" [value]="id" />
      }

      <!-- 值里有、树里没有的 hashid（权限被删/后端换口径）：按「当前值」平铺列出，别让它悄悄消失 -->
      @if (extras().length) {
        <div class="tree-extra">
          <div class="hint">{{ 'form.tree_orphan_hint' | t }}</div>
          @for (id of extras(); track id) {
            <label class="tree-label">
              <input
                type="checkbox"
                [attr.name]="name()"
                [value]="id"
                [checked]="sel().has(id)"
                (change)="mark(id, $any($event.target).checked)"
              />
              <span>{{ id }}</span>
            </label>
          }
        </div>
      }
    </div>
  `,
})
export class TreeSelect implements OnInit {
  readonly name = input.required<string>();
  readonly nodes = input<PNode[]>([]);
  /** 当前值：hashid 数组（角色行的 permission_ids） */
  readonly value = input<string[]>([]);

  /** 勾选集合（组件内的真值；DOM 只是它的投影） */
  protected readonly sel = signal<ReadonlySet<string>>(new Set());
  /** 折叠的节点（缺省全展开：权限树的层级本身就是要看的信息） */
  protected readonly folded = signal<ReadonlySet<string>>(new Set());

  protected readonly view = computed(() => flatten(this.nodes(), this.folded()));

  /** 状态表整棵算一次；模板里逐个取（每行各算一次就是 O(n²)） */
  private readonly states = computed(() => checkStates(this.nodes(), this.sel()));

  protected state(id: string): string {
    return this.states().get(id) ?? 'off';
  }

  /** 值里不在树中的 id（集合里也得有它们，否则一并被当成「取消勾选」） */
  protected readonly extras = computed(() => {
    const inTree = new Set(flatten(this.nodes()).map((f) => f.node.id));
    return this.value().filter((id) => !inTree.has(id));
  });

  /** 半选却在集合里 = 会被 checkbox 漏掉的存量值，补 hidden（见类注释） */
  protected readonly hiddenExtra = computed(() => {
    const extras = new Set(this.extras());
    return [...this.sel()].filter((id) => this.state(id) === 'half' && !extras.has(id));
  });

  ngOnInit(): void {
    this.sel.set(new Set(this.value().map(String)));
  }

  /** 点父级连带整棵子树；再沿祖先链收敛（子节点全勾 ⇒ 祖先勾上）—— 逻辑全在 core/tree */
  protected pick(id: string): void {
    this.sel.update((s) => toggle(this.nodes(), s, id));
  }

  /** 「树外」项的勾选：不在树里，没有父子可联动，就只有加减 */
  protected mark(id: string, on: boolean): void {
    this.sel.update((s) => {
      const next = new Set(s);
      if (on) next.add(id);
      else next.delete(id);
      return next;
    });
  }

  protected fold(id: string): void {
    this.folded.update((s) => {
      const next = new Set(s);
      if (!next.delete(id)) next.add(id);
      return next;
    });
  }
}
