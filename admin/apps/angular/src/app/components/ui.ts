/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, input, output } from '@angular/core';
import { T } from '../core/i18n/i18n';

/** 加载 / 出错 / 空 三态包裹器，正常内容走投影 */
@Component({
  selector: 'ui-state',
  imports: [T],
  template: `
    @if (loading()) {
      <div class="state"><span class="spinner"></span> {{ 'ui.loading' | t }}</div>
    } @else if (error()) {
      <div class="state error">{{ error() }}</div>
    } @else if (empty()) {
      <div class="state">
        <!-- 吉祥物小骰（Dicey）：相对 public/，由 <base href> 解析到子路径，无需硬编码前缀 -->
        <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
        <div>{{ text() || ('app.no_data' | t) }}</div>
      </div>
    } @else {
      <ng-content />
    }
  `,
})
export class StateBlock {
  readonly loading = input(false);
  readonly error = input('');
  readonly empty = input(false);
  /** 空态文案：留空则用通用「暂无数据」（模板里取默认值 ⇒ 切语言时跟着变） */
  readonly text = input('');
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
  imports: [T],
  template: `
    <div class="pager">
      <span>{{ 'app.pager' | t: { page: page(), pages: pages(), total: total() } }}</span>
      <div class="spacer"></div>
      <button class="btn" [disabled]="page() <= 1" (click)="jump.emit(page() - 1)">
        {{ 'app.prev_page' | t }}
      </button>
      <button class="btn" [disabled]="page() >= pages()" (click)="jump.emit(page() + 1)">
        {{ 'app.next_page' | t }}
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
  imports: [T],
  template: `
    @if (open()) {
      <div class="backdrop" (click)="close.emit()"></div>
      <aside class="drawer" role="dialog" aria-modal="true">
        <header>
          <b>{{ title() }}</b>
          <div class="spacer"></div>
          <button class="btn" (click)="close.emit()">{{ 'app.close' | t }}</button>
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

/** 标签页头：传 [{key,label}]，当前项 v-model 到 active；label 是词条键（过 `| t`） */
@Component({
  selector: 'ui-tabs',
  imports: [T],
  template: `
    <div class="tabs">
      @for (t of tabs(); track t.key) {
        <button [class.active]="t.key === active()" (click)="pick.emit(t.key)">
          {{ t.label | t }}
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
