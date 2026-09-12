/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, input, output } from '@angular/core';
import { Row } from '../core/api.service';
import { dash } from '../core/util';

export interface Act {
  key: string;
  label: string;
  danger?: boolean;
}

/**
 * 通用数据表。列 = heads 的键序（给了 heads 就按它，没给则从数据里推）。
 * 后端结构未确认，所以单元格一律 dash() 防御性渲染。
 */
@Component({
  selector: 'ui-table',
  template: `
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            @for (c of cols(); track c) {
              <th>{{ head(c) }}</th>
            }
            @if (actions().length) {
              <th>操作</th>
            }
          </tr>
        </thead>
        <tbody>
          @for (r of rows(); track $index) {
            <tr [class.clickable]="clickable()" (click)="pick.emit(r)">
              @for (c of cols(); track c) {
                <td [class.num]="isNum(r[c])">{{ dash(r[c]) }}</td>
              }
              @if (actions().length) {
                <td class="acts">
                  @for (a of actions(); track a.key) {
                    <button class="btn" [class.danger]="a.danger" (click)="fire($event, r, a.key)">
                      {{ a.label }}
                    </button>
                  }
                </td>
              }
            </tr>
          } @empty {
            <tr>
              <td class="state" [attr.colspan]="cols().length + (actions().length ? 1 : 0)">
                暂无数据
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
  /** 列顺序 + 中文表头；留空则自动推导 */
  readonly heads = input<Record<string, string>>({});
  readonly actions = input<Act[]>([]);
  readonly clickable = input(false);

  readonly pick = output<Row>();
  readonly act = output<{ row: Row; key: string }>();

  /** 模板作用域只认类成员，模块级 import 不可见 */
  protected readonly dash = dash;

  protected readonly cols = computed(() => {
    const keys = Object.keys(this.heads());
    if (keys.length) return keys;
    const out: string[] = [];
    for (const r of this.rows().slice(0, 20)) {
      for (const k of Object.keys(r)) if (!out.includes(k)) out.push(k);
    }
    return out.slice(0, 10); // ponytail: 自动列最多 10 列，超出靠 heads 显式指定
  });

  protected head(c: string): string {
    return this.heads()[c] ?? c;
  }

  protected isNum(v: unknown): boolean {
    return typeof v === 'number';
  }

  protected fire(ev: Event, row: Row, key: string): void {
    ev.stopPropagation();
    this.act.emit({ row, key });
  }
}
