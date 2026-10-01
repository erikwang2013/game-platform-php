/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { toTree } from '../core/tree';
import { TreeSelect } from './tree-select';

/** 与后端同形状：P2 是**没有 children 键**的叶子，P1 的 parent_id 是数字 0 */
const RAW = [
  {
    id: 'P1',
    name: '系统',
    parent_id: 0,
    children: [
      { id: 'P2', name: '用户' },
      { id: 'P3', name: '角色', children: [{ id: 'P4', name: '改角色' }] },
    ],
  },
];

/** 外面套一层**真 form**：提交值必须能从 FormData 里读出来（form-modal.fire() 就是这么读的） */
@Component({
  selector: 'app-tree-host',
  imports: [TreeSelect],
  template: `<form><ui-tree-select name="permission_ids" [nodes]="nodes" [value]="value" /></form>`,
})
class Host {
  nodes = toTree(RAW);
  value: string[] = [];
}

describe('ui-tree-select（三态勾选 + 父子联动 + 提交值）', () => {
  let fixture: ComponentFixture<Host>;
  let form: HTMLFormElement;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [Host] });
  });

  afterEach(() => {
    fixture?.destroy();
    TestBed.resetTestingModule();
  });

  const mount = (value: string[]): void => {
    fixture = TestBed.createComponent(Host);
    fixture.componentInstance.value = value;
    fixture.detectChanges();
    form = (fixture.nativeElement as HTMLElement).querySelector('form')!;
  };

  /** [value] 绑的是 DOM property（不反映到 attribute）⇒ 按 property 找，别用 CSS 属性选择器 */
  const boxes = (): HTMLInputElement[] =>
    [...(fixture.nativeElement as HTMLElement).querySelectorAll<HTMLInputElement>('input')];
  const box = (id: string): HTMLInputElement => boxes().find((i) => i.value === id)!;
  /** 提交值 = 表单里真正被勾中的项（没勾中的 checkbox 不进 FormData，这正是三态的表达方式） */
  const picked = (): string[] => new FormData(form).getAll('permission_ids').map(String).sort();
  const click = (id: string): void => {
    box(id).click();
    fixture.detectChanges();
  };
  const rows = (): string[] => [...(fixture.nativeElement as HTMLElement).querySelectorAll('.tree-row')].length
    ? boxes().filter((i) => i.type === 'checkbox').map((i) => i.value)
    : [];

  it('回填：勾中的勾上、后代勾了但没勾满的父级是**半选**、其余未勾', () => {
    mount(['P2']);
    expect(box('P2').checked).toBe(true);
    expect(box('P1').checked).toBe(false);
    expect(box('P1').indeterminate).toBe(true);
    expect(box('P3').indeterminate).toBe(false);
    expect(box('P3').checked).toBe(false);
    expect(picked()).toEqual(['P2']);
  });

  it('勾父级 ⇒ 连带整棵子树，提交值跟着变（表单读的就是这些 checkbox）', () => {
    mount([]);
    click('P1');
    expect(picked()).toEqual(['P1', 'P2', 'P3', 'P4']);
    expect(box('P1').indeterminate).toBe(false);
  });

  it('取消一个孙节点 ⇒ 它那一支整条掉出集合（P3 没后代可勾了 = off），提交值只剩没被碰过的那支', () => {
    mount(['P1', 'P2', 'P3', 'P4']);
    click('P4');
    expect(picked()).toEqual(['P2']);
    expect(box('P3').checked).toBe(false);
    expect(box('P3').indeterminate).toBe(false);
    // P1 还有 P2 挂着 ⇒ 降级成半选（不是 off，也不是 on）
    expect(box('P1').indeterminate).toBe(true);
  });

  it('半选的点一下勾满，兄弟已勾满时父级自动补上', () => {
    mount(['P3', 'P4']);
    expect(box('P1').indeterminate).toBe(true);
    click('P2');
    expect(picked()).toEqual(['P1', 'P2', 'P3', 'P4']);
  });

  /**
   * 存量值「只有父节点」：它渲染成半选（checked=false），光靠 checkbox 提交就会被当成
   * 「被取消勾选」静默吊销授权 —— hidden input 兜住它。这条是 ui-form.multi()「当前值」的同款规矩。
   */
  it('值里有父节点而子级没勾 ⇒ 半选但仍提交得出去（不许静默吊销）', () => {
    mount(['P1']);
    expect(box('P1').checked).toBe(false);
    expect(box('P1').indeterminate).toBe(true);
    expect(picked()).toEqual(['P1']);
  });

  it('值里有树里没有的 hashid（权限被删）⇒ 平铺成「树外」项并保留，取消勾选才回收', () => {
    mount(['P1', 'GONE']);
    expect(picked()).toEqual(['GONE', 'P1']);
    click('GONE');
    expect(picked()).toEqual(['P1']);
  });

  it('折叠：子树的行从 DOM 消失，箭头还能再展开（父行留着）', () => {
    mount([]);
    const arrow = (fixture.nativeElement as HTMLElement).querySelector<HTMLButtonElement>('.tree-row .tree-arrow')!;
    expect(rows()).toEqual(['P1', 'P2', 'P3', 'P4']);
    arrow.click();
    fixture.detectChanges();
    expect(rows()).toEqual(['P1']);
    arrow.click();
    fixture.detectChanges();
    expect(rows()).toEqual(['P1', 'P2', 'P3', 'P4']);
  });
});
