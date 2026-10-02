/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ModalFocus } from './modal-focus';

/**
 * `uiModal` 的四条可及性行为，每条都是**能红**的（把指令从 `imports` 里摘掉即全红）。
 *
 * 钉它的理由（真机实测口径）：不挂这条指令时，开框后 `document.activeElement` 仍是 `BODY`，
 * 连按 Tab 全落在**被遮罩盖住的背景**上（列表行 / 刷新 / 加载更多），此时回车操作的是背景页；
 * Esc 关不掉，只能鼠标点「关闭」。所以四条都要有独立判据，不能只测「不报错」。
 *
 * ⚠ 断言读 `document.activeElement` 而不是 `fixture.nativeElement.querySelector(':focus')`：
 * 后者在「焦点其实在 BODY」时**同样返回 null**，会把「没设焦点」和「设了但掉出去」混成一种红。
 * 另：`focus()` 只对**连在文档上**的元素生效 ⇒ 测试里显式把宿主挂到 `document.body`。
 */
@Component({
  imports: [ModalFocus],
  template: `
    <button id="opener" type="button" (click)="open.set(true)">打开弹框</button>
    <button id="outside" type="button" (click)="open.set(true)">背景页的另一个按钮</button>
    @if (open()) {
      <div id="box" uiModal (dismiss)="open.set(false)">
        <button id="first" type="button">第一个</button>
        <button id="last" type="button">最后一个</button>
      </div>
    }
  `,
})
class Host {
  readonly open = signal(false);
}

const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;

const press = (target: HTMLElement, key: string, shiftKey = false): void => {
  target.dispatchEvent(new KeyboardEvent('keydown', { key, shiftKey, bubbles: true, cancelable: true }));
};

describe('ModalFocus（uiModal）', () => {
  let fixture: ComponentFixture<Host>;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [Host] });
    fixture = TestBed.createComponent(Host);
    document.body.appendChild(fixture.nativeElement);
    fixture.detectChanges();
  });

  afterEach(() => {
    fixture.destroy();
    document.body.innerHTML = '';
  });

  /** 开框：点「打开弹框」这个真实按钮（焦点在它身上），再跑一轮变更检测把框挂出来 */
  const openBox = (): HTMLElement => {
    el('opener').focus();
    el('opener').click();
    fixture.detectChanges();
    return el('box');
  };

  it('① 挂载即把焦点移进框内首个可聚焦元素（不是留在背景页）', () => {
    openBox();
    // 关键读数：不挂指令时这里是 BODY
    expect(document.activeElement).toBe(el('first'));
    expect(document.activeElement).not.toBe(document.body);
  });

  it('② Tab / Shift+Tab 圈在框内：末→首、首(Shift)→末，背景按钮进不来', () => {
    openBox();

    el('last').focus();
    press(el('last'), 'Tab');
    expect(document.activeElement).toBe(el('first')); // 最后一个 Tab ⇒ 回第一个

    press(el('first'), 'Tab', true);
    expect(document.activeElement).toBe(el('last')); // 第一个 Shift+Tab ⇒ 去最后一个

    // 焦点跑到框外之后，一旦指令**看到**这次 Tab，必须把焦点拉回框内。
    // ⚠ 事件派发到宿主（`#box`）而不是 `#outside`：真浏览器里从背景元素按 Tab，keydown 的 target
    //   就是那个背景元素，它与弹框是**兄弟**、不会冒泡进宿主 ⇒ 指令根本看不到这次按键
    //   （实测：派发到 `#outside` 时这条断言必红，而那不是实现缺陷，是我把场景写错了）。
    //   所以这条钉的是「指令一旦看到就必须拉回来」这条不变式，属**防御分支**。
    el('outside').focus();
    press(el('box'), 'Tab');
    expect(document.activeElement).toBe(el('first'));
  });

  it('③ Esc 触发 dismiss（宿主自己决定怎么关），并 preventDefault', () => {
    openBox();
    const ev = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    el('first').dispatchEvent(ev);
    fixture.detectChanges();
    expect(ev.defaultPrevented).toBe(true);
    expect(fixture.componentInstance.open()).toBe(false); // dismiss 已 emit
    expect(document.getElementById('box')).toBeNull();
  });

  it('④ 卸载时把焦点还给打开它的那个元素', () => {
    const opener = el('opener');
    openBox();
    expect(document.activeElement).toBe(el('first'));

    press(el('first'), 'Escape');
    fixture.detectChanges();
    expect(document.activeElement).toBe(opener); // 不还给打开者的话这里会是 BODY
  });
});
