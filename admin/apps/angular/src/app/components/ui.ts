/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import {
  AfterViewInit,
  Component,
  Directive,
  ElementRef,
  OnDestroy,
  inject,
  input,
  output,
} from '@angular/core';
import { T } from '../core/i18n/i18n';

/**
 * 弹框的键盘可及性，四条一次给全（表单框 / 抽屉 / 导入报表 / 登录验证码**共用这一份实现**）：
 * ① 挂载时把焦点移进框内首个可聚焦元素；② Tab / Shift+Tab 循环，圈在框内；③ Esc 触发 dismiss；
 * ④ 卸载时把焦点还给打开它的那个元素。
 *
 * 挂**弹框容器**（`.modal` / `.drawer`）上，与 `@if (open())` 同生共死 —— 挂载即开框、
 * 卸载即关框，不用再接一个 open 信号去追。Esc 听宿主上的 keydown 而不是 react 那样的 window：
 * 焦点本就在框内（①+②），且万一将来套两层框，只有焦点所在的那个会响应。
 *
 * ⚠ 不写这段的话，实测（真 Chrome，`.data` 表格页）是：开框后 activeElement 仍是 BODY、
 * 连按 16 次 Tab 全落在**被遮罩盖住的背景**上（侧栏/语言/账号/顶栏搜索），此时回车操作的是
 * 背景页；Esc 关不掉，只能鼠标点「关闭」。见 /tmp/ux_probe3.mjs 的 M2/M3/M4。
 */
@Directive({
  selector: '[uiModal]',
  host: { '(keydown)': 'onKey($event)' },
})
export class ModalFocus implements AfterViewInit, OnDestroy {
  /** Esc：宿主自己决定怎么关（`(dismiss)="close.emit()"`），指令不认识调用方的关闭语义 */
  readonly dismiss = output<void>();

  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);
  /** 打开弹框前的焦点位置（关框还回去）。字段初始化早于 ngAfterViewInit ⇒ 拿到的还是打开者 */
  private readonly home = document.activeElement instanceof HTMLElement ? document.activeElement : null;

  ngAfterViewInit(): void {
    this.items()[0]?.focus();
  }

  ngOnDestroy(): void {
    // 元素已随视图移除时 focus() 是空操作（路由切走也走这条路），不报错
    this.home?.focus();
  }

  /**
   * 框内可聚焦元素，按 DOM 顺序。`[hidden]` 要排掉：image/file 字段那两个选文件输入就是隐藏的
   * （对隐藏元素 focus() 不生效 ⇒「首个可聚焦」会落空，焦点仍留在 BODY）。
   */
  private items(): HTMLElement[] {
    const sel =
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    return [...this.host.nativeElement.querySelectorAll<HTMLElement>(sel)].filter(
      (el) => !el.closest('[hidden]'),
    );
  }

  protected onKey(ev: KeyboardEvent): void {
    if (ev.key === 'Escape') {
      ev.preventDefault();
      this.dismiss.emit();
      return;
    }
    if (ev.key !== 'Tab') return;
    const list = this.items();
    if (!list.length) return;
    const first = list[0];
    const last = list[list.length - 1];
    const active = document.activeElement;
    // 焦点不在框内（鼠标点过遮罩等）时也拉回来，否则这一次 Tab 就直接走进背景页
    const inBox = this.host.nativeElement.contains(active);
    if (ev.shiftKey && (!inBox || active === first)) {
      ev.preventDefault();
      last.focus();
    } else if (!ev.shiftKey && (!inBox || active === last)) {
      ev.preventDefault();
      first.focus();
    }
  }
}

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
  imports: [T, ModalFocus],
  template: `
    @if (open()) {
      <div class="backdrop" (click)="close.emit()"></div>
      <aside class="drawer" role="dialog" aria-modal="true" uiModal (dismiss)="close.emit()">
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
