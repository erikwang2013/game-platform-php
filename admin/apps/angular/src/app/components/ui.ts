/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, input, output } from '@angular/core';

/** 加载 / 出错 / 空 三态包裹器，正常内容走投影 */
@Component({
  selector: 'ui-state',
  template: `
    @if (loading()) {
      <div class="state"><span class="spinner"></span> 加载中…</div>
    } @else if (error()) {
      <div class="state error">{{ error() }}</div>
    } @else if (empty()) {
      <div class="state">{{ text() }}</div>
    } @else {
      <ng-content />
    }
  `,
})
export class StateBlock {
  readonly loading = input(false);
  readonly error = input('');
  readonly empty = input(false);
  readonly text = input('暂无数据');
}

/** 指标卡 */
@Component({
  selector: 'ui-stat',
  template: `
    <div class="tile">
      <div class="tile-label">{{ label() }}</div>
      <div class="tile-value">{{ value() }}</div>
      @if (trend()) {
        <div class="tile-trend">{{ trend() }}</div>
      }
    </div>
  `,
})
export class StatCard {
  readonly label = input.required<string>();
  readonly value = input<string | number>('—');
  readonly trend = input<string>('');
}

/** 分页条 */
@Component({
  selector: 'ui-pager',
  template: `
    <div class="pager">
      <span>共 {{ total() }} 条 · 第 {{ page() }}/{{ pages() }} 页</span>
      <div class="spacer"></div>
      <button class="btn" [disabled]="page() <= 1" (click)="jump.emit(page() - 1)">上一页</button>
      <button class="btn" [disabled]="page() >= pages()" (click)="jump.emit(page() + 1)">
        下一页
      </button>
    </div>
  `,
})
export class Pager {
  readonly page = input(1);
  readonly pages = input(1);
  readonly total = input(0);
  readonly jump = output<number>();
}

/** 右侧详情抽屉 */
@Component({
  selector: 'ui-drawer',
  template: `
    @if (open()) {
      <div class="backdrop" (click)="close.emit()"></div>
      <aside class="drawer" role="dialog" aria-modal="true">
        <header>
          <b>{{ title() }}</b>
          <div class="spacer"></div>
          <button class="btn" (click)="close.emit()">关闭</button>
        </header>
        <div class="drawer-body"><ng-content /></div>
      </aside>
    }
  `,
})
export class Drawer {
  readonly open = input(false);
  readonly title = input('');
  readonly close = output<void>();
}

/** 标签页头：传 [{key,label}]，当前项 v-model 到 active */
@Component({
  selector: 'ui-tabs',
  template: `
    <div class="tabs">
      @for (t of tabs(); track t.key) {
        <button [class.active]="t.key === active()" (click)="pick.emit(t.key)">
          {{ t.label }}
        </button>
      }
    </div>
  `,
})
export class Tabs {
  readonly tabs = input.required<{ key: string; label: string }[]>();
  readonly active = input('');
  readonly pick = output<string>();
}
