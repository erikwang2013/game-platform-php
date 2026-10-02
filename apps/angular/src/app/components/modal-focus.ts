/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { AfterViewInit, Directive, ElementRef, OnDestroy, inject, output } from '@angular/core';

/**
 * 弹框的键盘可及性，四条一次给全（流水 / 工单 / 赛事 / 公告四个详情框共用这一份实现）：
 * ① 挂载时把焦点移进框内首个可聚焦元素；② Tab / Shift+Tab 循环，圈在框内；③ Esc 触发 dismiss；
 * ④ 卸载时把焦点还给打开它的那个元素。
 *
 * 挂**弹框容器**（`.modal`）上，与 `@if (cur() || ...)` 同生共死 —— 挂载即开框、卸载即关框，
 * 不用再接一个 open 信号去追。Esc 听**宿主**上的 keydown 而不是 document（本树 `core/captcha.ts`
 * 那份是 document 级）：焦点本就在框内（①+②），且万一将来套两层框，只有焦点所在的那个会响应。
 *
 * ⚠ 不写这段的话，实测（真 Chrome）是：开框后 activeElement 仍是 BODY、连按 Tab 全落在**被遮罩
 * 盖住的背景**上（列表行/刷新/加载更多），此时回车操作的是背景页 —— 用户能在弹框开着的时候
 * 触发鼠标点不到的按钮、页面在弹框下面刷新。Esc 关不掉，只能鼠标点「关闭」。
 *
 * 本文件是 `admin/apps/angular/src/app/components/ui.ts` 里 `ModalFocus` 的**逐字移植**
 * （admin 树已在真机验收过），只去掉对 admin i18n 的依赖 —— 该指令本来就只用 Angular core。
 * 两棵树各留一份是有意的：这是 C 端独立构建的产物，没法跨树 import；改一处请同步另一处。
 */
@Directive({
  selector: '[uiModal]',
  host: { '(keydown)': 'onKey($event)' },
})
export class ModalFocus implements AfterViewInit, OnDestroy {
  /** Esc：宿主自己决定怎么关（`(dismiss)="close()"`），指令不认识调用方的关闭语义 */
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
