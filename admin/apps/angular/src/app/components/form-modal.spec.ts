/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { describe, expect, it } from 'vitest';
import { Row } from '../core/api.service';
import { Field } from '../core/crud';
import { json } from '../core/render';
import { FormModal } from './form-modal';

const FIELDS: Field[] = [
  { name: 'name', label: '名称', type: 'text', required: true },
  {
    name: 'type',
    label: '类型',
    type: 'select',
    required: true,
    options: [
      { value: 'self', label: '自研' },
      { value: 'embedded', label: '内嵌' },
    ],
  },
  { name: 'status', label: '状态', type: 'switch' },
  { name: 'content', label: '内容', type: 'textarea' },
];

@Component({
  selector: 'app-form-host',
  imports: [FormModal],
  template: `
    <ui-form
      [open]="open()"
      [value]="value()"
      [fields]="fields()"
      [error]="error()"
      (save)="got = $event"
    />
  `,
})
class Host {
  readonly open = signal(false);
  readonly value = signal<Row | null>(null);
  readonly error = signal('');
  /** 信号而非常量：JSON 列那类字段要临时换一套字段描述 */
  readonly fields = signal<Field[]>(FIELDS);
  got: Row | null = null;
}

describe('FormModal（通用表单弹框）', () => {
  const setup = async (): Promise<ComponentFixture<Host>> => {
    TestBed.configureTestingModule({ imports: [Host] });
    const fixture = TestBed.createComponent(Host);
    await fixture.whenStable();
    return fixture;
  };

  const el = <T extends Element>(f: ComponentFixture<Host>, sel: string): T => {
    const node = f.nativeElement.querySelector(sel) as T | null;
    if (!node) throw new Error(`未找到 ${sel}`);
    return node;
  };

  /**
   * 必须冒泡（真实提交事件是冒泡的）：否则只走到组件内部 fire() 这一条路，
   * 测不出「宿主上另有一条 DOM 监听」的错——output 名若叫 submit，它会先收到正确 Row、
   * 再被冒泡上来的 SubmitEvent 覆盖（实测载荷 `{"type":"submit"}`）。见 form-modal.ts:112。
   */
  const submit = async (f: ComponentFixture<Host>): Promise<void> => {
    const form = el<HTMLFormElement>(f, 'form');
    form.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
    await f.whenStable();
  };

  it('关着时不渲染（@if 重建 ⇒ 预填不会残留上一次的输入）', async () => {
    const f = await setup();
    expect(f.nativeElement.querySelector('.modal')).toBeNull();
  });

  it('编辑预填：text/select/textarea 取值、switch 认 1（select 的预选是 DOM 时序敏感的）', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ name: 'a', type: 'embedded', status: 1, content: 'x' });
    f.detectChanges();

    expect(el<HTMLInputElement>(f, 'input[name="name"]').value).toBe('a');
    expect(el<HTMLSelectElement>(f, 'select[name="type"]').value).toBe('embedded');
    expect(el<HTMLTextAreaElement>(f, 'textarea[name="content"]').value).toBe('x');
    expect(el<HTMLInputElement>(f, 'input[name="status"]').checked).toBe(true);
  });

  it('提交读原生表单值：未勾的 switch 落 0，改过的 textarea 带上', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ name: 'a', type: 'embedded', status: 1, content: 'x' });
    f.detectChanges();

    el<HTMLInputElement>(f, 'input[name="name"]').value = 'b';
    el<HTMLTextAreaElement>(f, 'textarea[name="content"]').value = 'y';
    el<HTMLInputElement>(f, 'input[name="status"]').checked = false;
    await submit(f);

    expect(f.componentInstance.got).toEqual({
      name: 'b',
      type: 'embedded',
      status: 0,
      content: 'y',
    });
  });

  it('新建：没预填的 select 落空串（不静默取第一个选项让必填字段蒙混过关）', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.detectChanges();

    expect(el<HTMLSelectElement>(f, 'select[name="type"]').value).toBe('');
    await submit(f);
    expect(f.componentInstance.got).toEqual({ name: '', type: '', status: 0, content: '' });
  });

  it('存量值不在选项表里：置顶补一条并原样带回（不会被显示成「请选择」再被手滑改掉）', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ name: 'a', type: 'legacy', status: 0, content: '' });
    f.detectChanges();

    const sel = el<HTMLSelectElement>(f, 'select[name="type"]');
    expect(sel.value).toBe('legacy');
    expect(sel.options[0].textContent).toContain('legacy（当前值）');
    await submit(f);
    // 原值原样带回 ⇒ payload() 比对后不提交，存量数据不被改写
    expect(f.componentInstance.got?.['type']).toBe('legacy');
  });

  /**
   * JSON 列预填：值读回来是数组/对象，不能落成 "[object Object]" —— 那样用户没动它也会把
   * 这个字符串提交上去（后端 json_decode 直接 422）。且必须用 render.json 这个**同一个**
   * 序列化器，否则 crud.payload 的判等永远不等（详见 crud.spec.ts 的对应用例）。
   */
  it('JSON 列预填：对象落成 JSON 文本（不是 [object Object]）', async () => {
    const f = await setup();
    f.componentInstance.fields.set([{ name: 'config', label: '配置', type: 'textarea' }]);
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ config: [{ day: 1 }] });
    f.detectChanges();

    const text = el<HTMLTextAreaElement>(f, 'textarea[name="config"]').value;
    expect(text).toBe(json([{ day: 1 }]));
    expect(text).not.toContain('[object');
  });

  /**
   * Field.hint（风控规则的 config 键表）：多行说明要挂在**自己那个字段**下面。
   * 前端只提示不校验 —— 白名单/值域的真值在服务端，这里拼错一个字不能变成拦截规则。
   */
  it('hint：渲染在所属字段的格子里（多行照排，不挤到表单末尾）', async () => {
    const f = await setup();
    f.componentInstance.fields.set([
      { name: 'name', label: '名称', type: 'text' },
      { name: 'config', label: '配置', type: 'textarea', hint: '第一行\n第二行' },
    ]);
    f.componentInstance.open.set(true);
    f.detectChanges();

    const box = el<HTMLTextAreaElement>(f, 'textarea[name="config"]').closest('div')!;
    const hint = box.querySelector('.hint') as HTMLElement | null;
    expect(hint?.textContent?.trim()).toBe('第一行\n第二行');
    // 说明只对声明了 hint 的字段出（其余字段的格子里没有）
    expect(el<HTMLInputElement>(f, 'input[name="name"]').closest('div')!.querySelector('.hint')).toBeNull();
  });

  /**
   * multi（角色的 permission_ids 那类**数组**字段）：勾选的项全都要回来 ——
   * 不是第一个、也不是 JSON 字符串（后端 decodePermissionIds() 收的正是数组）。
   */
  it('multi：回填按下标勾中，提交时多个勾选项原样成数组（未勾的不掺进来）', async () => {
    const f = await setup();
    f.componentInstance.fields.set([
      {
        name: 'permission_ids',
        label: '权限',
        type: 'multi',
        options: [
          { value: 'PERM1', label: '系统' },
          { value: 'PERM2', label: '　用户' },
          { value: 'PERM3', label: '　　删除' },
        ],
      },
    ]);
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ permission_ids: ['PERM3'] });
    f.detectChanges();

    const sel = el<HTMLSelectElement>(f, 'select[name="permission_ids"]');
    expect(sel.multiple).toBe(true);
    // 回填：行里的 hashid 就是勾中项（不是「请选择」）
    expect(Array.from(sel.options).filter((o) => o.selected).map((o) => o.value)).toEqual(['PERM3']);
    (sel.options[0] as HTMLOptionElement).selected = true;
    await submit(f);
    expect(f.componentInstance.got?.['permission_ids']).toEqual(['PERM1', 'PERM3']);
  });

  it('multi：当前值不在选项表里置顶补项且保持勾选（树取失败也不能把已有授权勾没）', async () => {
    const f = await setup();
    f.componentInstance.fields.set([
      { name: 'permission_ids', label: '权限', type: 'multi', options: [] },
    ]);
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ permission_ids: ['GONE1'] });
    f.detectChanges();

    const sel = el<HTMLSelectElement>(f, 'select[name="permission_ids"]');
    expect(sel.options[0]!.textContent).toContain('GONE1（当前值）');
    expect(sel.options[0]!.selected).toBe(true);
    await submit(f);
    expect(f.componentInstance.got?.['permission_ids']).toEqual(['GONE1']);
  });

  it('开→关→再开：输入被重建，不带上一轮残留', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ name: 'a', type: 'self', status: 0, content: '' });
    f.detectChanges();
    el<HTMLInputElement>(f, 'input[name="name"]').value = '脏值';

    f.componentInstance.open.set(false);
    f.detectChanges();
    f.componentInstance.open.set(true);
    f.detectChanges();

    expect(el<HTMLInputElement>(f, 'input[name="name"]').value).toBe('a');
  });

  it('422 失败信息显示在框内且框不关（页面把服务端 message 灌回 error）', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.componentInstance.error.set('游戏标识已存在');
    f.detectChanges();

    expect(el<HTMLElement>(f, '.alert').textContent).toContain('游戏标识已存在');
    expect(f.nativeElement.querySelector('.modal')).toBeTruthy();
  });
});
