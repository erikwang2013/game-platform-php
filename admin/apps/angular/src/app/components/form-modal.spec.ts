/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { describe, expect, it } from 'vitest';
import { Row } from '../core/api.service';
import { Field } from '../core/crud';
import { json } from '../core/render';
import { use } from '../core/i18n/i18n';
import { ImageUpload } from '../core/upload';
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
    <button id="opener" type="button" (click)="open.set(true)">打开</button>
    <ui-form
      [open]="open()"
      [value]="value()"
      [fields]="fields()"
      [error]="error()"
      (save)="got = $event"
      (close)="open.set(false)"
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
  /** image 字段要注入上传服务；不传 = 一个必定失败的空壳（只关心渲染的用例不碰它） */
  const setup = async (image?: (f: File) => Promise<string>): Promise<ComponentFixture<Host>> => {
    // 界面文案断言用中文：本文件多处断言译文（「当前值」「请选择」…），语言必须显式定，
    // 否则跟着默认语言（en）走 —— 断言的是 UI 文案，就按某个具体语言断
    use('zh');
    TestBed.configureTestingModule({
      imports: [Host],
      providers: [
        { provide: ImageUpload, useValue: { image: image ?? (() => Promise.reject(new Error('未配置'))) } },
      ],
    });
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

  it('字段名与控件程序化关联：label[for] 指向同名 id（点标签聚焦、读屏播字段名）', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ name: 'a', type: 'embedded', status: 1, content: 'x' });
    f.detectChanges();

    // 除 tree 外每个字段名标签都要真的指到那个控件：少写 id、名字写错、指到别的元素都红
    const pairs: [string, string][] = [
      ['name', 'input[name="name"]'],
      ['type', 'select[name="type"]'],
      ['content', 'textarea[name="content"]'],
      ['status', 'input[name="status"]'],
    ];
    for (const [field, sel] of pairs) {
      const label = el<HTMLLabelElement>(f, `label[for="${field}"]`);
      const control = el<HTMLElement>(f, sel);
      expect(label.htmlFor).toBe(field);
      expect(control.id).toBe(field);
      expect(f.nativeElement.querySelector(`#${field}`)).toBe(control);
    }

    // multi 照旧关联；tree 是自定义组件（for/id 到不了它内部的 checkbox）⇒ **不写** for
    f.componentInstance.fields.set([
      {
        name: 'permission_ids',
        label: '权限',
        type: 'multi',
        options: [{ value: 'P1', label: '系统' }],
      },
      {
        name: 'perms',
        label: '权限树',
        type: 'tree',
        tree: [{ id: 'P1', name: '系统', row: {}, children: [] }],
      },
    ]);
    f.detectChanges();
    expect(el<HTMLLabelElement>(f, 'label[for="permission_ids"]').htmlFor).toBe('permission_ids');
    expect(el<HTMLElement>(f, 'select[name="permission_ids"]').id).toBe('permission_ids');
    // 悬空引用比不写更坏：读屏会播一个不存在的关联
    expect(f.nativeElement.querySelector('label[for="perms"]')).toBeNull();
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

  /**
   * **一个都不勾** ⇒ 键必须还在，值是 `[]`。
   *
   * 这条钉的是「撤销」这个方向，与上面两条（勾中的要带回来）是两回事：原生
   * `select[multiple]` 一个都不选时**不进 FormData**，全靠 `fire()` 用 `fd.getAll(name)`
   * 兜住。若哪天图省事改成 `Object.fromEntries(new FormData(form))`，键会**整个消失** ——
   * 而服务端判的是 `$request->has('role_ids')`（`UserController::update:211`），
   * 于是「收回全部角色」**静默失效**：提交看着成功、授权原封不动。
   *
   * 受影响的不止管理端的「分配角色」，角色表单的权限多选同吃这一条。
   */
  it('multi：一个都不勾 ⇒ 键仍在、值为空数组（清空授权不能被静默吃掉）', async () => {
    const f = await setup();
    f.componentInstance.fields.set([
      {
        name: 'role_ids',
        label: '角色',
        type: 'multi',
        options: [
          { value: 'R1', label: '运营' },
          { value: 'R2', label: '客服' },
        ],
      },
    ]);
    f.componentInstance.open.set(true);
    // 预填勾上 R1 —— 正是「本来有角色，现在要全收回」的现场
    f.componentInstance.value.set({ role_ids: ['R1'] });
    f.detectChanges();

    const sel = el<HTMLSelectElement>(f, 'select[name="role_ids"]');
    expect(Array.from(sel.options).filter((o) => o.selected).map((o) => o.value)).toEqual(['R1']);

    // 界面上全部取消勾选
    for (const option of Array.from(sel.options)) {
      option.selected = false;
    }
    await submit(f);

    // 键在不在是这条用例的全部意义：只断言 `toEqual({role_ids: []})` 不够直观，故显式再钉一次
    expect(Object.hasOwn(f.componentInstance.got ?? {}, 'role_ids')).toBe(true);
    expect(f.componentInstance.got).toEqual({ role_ids: [] });
  });

  /**
   * 把 `file` 塞进 `input[type=file]`。jsdom 没有 `DataTransfer`，`input.files = 任意对象` 也走不通
   * （IDL setter 要真 `FileList`）⇒ 必须落到 **impl** 上：`new FormData(form)` 是在 impl 层组条目的
   * （xhr/FormData-impl.js 读 `field.files.length` 与 `.item(i)`），且条目按 `File.isImpl` 认形状 ——
   * 喂包装对象会被 `USVString()` 成字面量 `"[object File]"`，正好是这条用例要防的那个 bug。
   * 索引 `0` 与 `item(i)` **两条路都得给**：组件自己读 `files[0]`，jsdom 组 FormData 用 `.item(i)`。
   */
  const pickInto = (input: HTMLInputElement, file: File): void => {
    const implOf = (o: object): Record<string, unknown> =>
      (o as unknown as Record<symbol, Record<string, unknown>>)[
        Object.getOwnPropertySymbols(o).find((s) => s.description === 'impl')!
      ]!;
    const f = implOf(file);
    implOf(input)['files'] = { length: 1, item: (i: number) => (i === 0 ? f : null), 0: f };
    input.dispatchEvent(new Event('change'));
  };

  /**
   * file 字段（Excel 导入）：**交出去的是 `File` 本体**，不是文件名、更不是 `[object File]`。
   *
   * 这条钉的是「别把 File 当字符串塞」：一旦这里落回 `String(fd.get(name))`，服务端收到的
   * 就是一段文本，`$request->file('file')` 恒为 null ⇒ 导入永远 422，而界面上看不出异常
   * （只读框里明明显示着文件名）。
   */
  it('file：提交的是 File 本体（不是文件名，也不是 [object File]）', async () => {
    const f = await setup();
    f.componentInstance.fields.set([
      { name: 'file', label: '文件', type: 'file', required: true, accept: '.xlsx,.xls' },
    ]);
    f.componentInstance.open.set(true);
    f.detectChanges();

    const input = el<HTMLInputElement>(f, 'input[type="file"]');
    expect(input.accept).toBe('.xlsx,.xls');
    // 文件名那个框是**只读**的：手输一个名字拿不到文件，提交时服务端只会收到一段文本
    expect(el<HTMLInputElement>(f, 'input[type="text"]').readOnly).toBe(true);

    pickInto(input, new File(['a,b'], 'users.xlsx'));
    f.detectChanges();
    expect(el<HTMLInputElement>(f, 'input[type="text"]').value).toBe('users.xlsx');

    await submit(f);
    const got = f.componentInstance.got?.['file'];
    expect(got).toBeInstanceOf(File);
    expect((got as File).name).toBe('users.xlsx');
  });

  /**
   * 没选文件：值是一个**空文件**（浏览器与 jsdom 都这么填 `FormData`），不是 `null`、也不是
   * 空串 —— 认错形状的那一方（`import-panel` 按 name 判空、`payload()` 按 file 跳过）就漏了。
   * 它不能是字符串：字符串会让 multipart 里出现一个名叫 `file` 的**文本字段**。
   */
  it('file：没选文件时是一个空 File（不是字符串、不是 null）', async () => {
    const f = await setup();
    f.componentInstance.fields.set([{ name: 'file', label: '文件', type: 'file' }]);
    f.componentInstance.open.set(true);
    f.detectChanges();

    await submit(f);
    const got = f.componentInstance.got?.['file'];
    expect(got).toBeInstanceOf(File);
    expect((got as File).name).toBe('');
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

  /**
   * 弹框的键盘可及性（`uiModal` 指令，components/ui.ts）。真机读数见 /tmp/ux_probe3.mjs 的
   * M2/M3/M4 —— 改前三条全 FAIL：activeElement 停在 BODY、连按 16 次 Tab 全落在**遮罩下的背景**
   * 上（回车操作的是背景页）、Esc 关不掉。
   *
   * jsdom 不会自己执行 Tab 的默认行为（不移动焦点）⇒ 断言必须落在**陷阱的两端**：
   * 焦点已经在首/末元素上时是**我们**在 preventDefault 并搬焦点，那一步能分辨真假；
   * 中间那些「浏览器自己会走」的情况在这里测不出名堂。
   */
  const press = (from: Element, key: string, shiftKey = false): KeyboardEvent => {
    const ev = new KeyboardEvent('keydown', { key, shiftKey, bubbles: true, cancelable: true });
    from.dispatchEvent(ev);
    return ev;
  };

  /** 框内可聚焦元素（[hidden] 的那两个选文件输入要排掉，同 ModalFocus.items） */
  const focusables = (f: ComponentFixture<Host>): HTMLElement[] =>
    [...el<HTMLElement>(f, '.modal').querySelectorAll<HTMLElement>('button, input')].filter(
      (e) => !e.closest('[hidden]'),
    );

  it('开框：焦点移进框内（不留在 BODY，键盘用户不必先 Tab 过一串背景链接）', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.detectChanges();

    const modal = el<HTMLElement>(f, '.modal');
    expect(modal.contains(document.activeElement)).toBe(true);
    expect(document.activeElement).toBe(focusables(f)[0]);
  });

  it('焦点陷阱：末个元素上 Tab 回首元素、首个元素上 Shift+Tab 回末元素（默认行为被吃掉）', async () => {
    const f = await setup();
    f.componentInstance.open.set(true);
    f.detectChanges();

    const items = focusables(f);
    const first = items[0];
    const last = items[items.length - 1];
    expect(first).not.toBe(last);

    last.focus();
    expect(press(last, 'Tab').defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(first);

    first.focus();
    expect(press(first, 'Tab', true).defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(last);
  });

  it('Esc 关框，并把焦点还回打开它的那个元素', async () => {
    const f = await setup();
    const opener = el<HTMLButtonElement>(f, '#opener');
    opener.focus();
    f.componentInstance.open.set(true);
    f.detectChanges();

    const ev = press(el<HTMLElement>(f, '.modal'), 'Escape');
    expect(ev.defaultPrevented).toBe(true);
    f.detectChanges();

    expect(f.nativeElement.querySelector('.modal')).toBeNull();
    expect(document.activeElement).toBe(opener);
  });

  /** 等 pick() 里那串裸 promise 走完（whenStable 只等 Angular 自己排的任务） */
  const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

  /** 把文件塞进 input[type=file]（jsdom 没有 DataTransfer：直接定义 files，组件只读 files[0]） */
  const choose = async (f: ComponentFixture<Host>, name = 'a.png'): Promise<void> => {
    const input = el<HTMLInputElement>(f, 'input[type="file"]');
    Object.defineProperty(input, 'files', {
      configurable: true,
      value: [new File(['x'], name, { type: 'image/png' })],
    });
    input.dispatchEvent(new Event('change'));
    await tick();
    f.detectChanges();
  };

  /**
   * image 字段（游戏封面 / 分类图标 / 成就图标）：**文本框必须保住** ——
   * 存量数据里分类 icon 是一列图标名而不是 URL，换成纯上传控件就编辑不了了。
   */
  it('image：仍是带 name 的文本框（存量手输值随提交原样回来）+ 上传按钮 + file 输入', async () => {
    const f = await setup();
    f.componentInstance.fields.set([{ name: 'icon', label: '图标', type: 'image' }]);
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ icon: 'icon_sword' });
    f.detectChanges();

    const box = el<HTMLInputElement>(f, 'input[name="icon"]');
    expect(box.type).toBe('text');
    expect(box.value).toBe('icon_sword');
    // 图标名不是 URL ⇒ 不出缩略图（否则就是一条 404 请求）
    expect(f.nativeElement.querySelector('img.thumb')).toBeNull();
    expect(el<HTMLInputElement>(f, 'input[type="file"]').accept).toContain('image/png');
    await submit(f);
    expect(f.componentInstance.got).toEqual({ icon: 'icon_sword' });
  });

  it('image：值形如 URL 时才出缩略图', async () => {
    const f = await setup();
    f.componentInstance.fields.set([{ name: 'cover', label: '封面', type: 'image' }]);
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ cover: 'https://cdn.test/a.png' });
    f.detectChanges();

    expect(el<HTMLImageElement>(f, 'img.thumb').getAttribute('src')).toBe('https://cdn.test/a.png');
  });

  it('上传成功：绝对 URL 写回原生输入框（提交读到它）+ 立刻出缩略图', async () => {
    const url = 'http://admin.test/admin/v1/aetherupload/display/image_202610_ab.png';
    const f = await setup(() => Promise.resolve(url));
    f.componentInstance.fields.set([{ name: 'cover', label: '封面', type: 'image' }]);
    f.componentInstance.open.set(true);
    f.detectChanges();

    await choose(f);

    const box = el<HTMLInputElement>(f, 'input[name="cover"]');
    expect(box.value).toBe(url);
    expect(el<HTMLImageElement>(f, 'img.thumb').getAttribute('src')).toBe(url);
    // 落库走的是表单值 ⇒ 上传结果和手输 URL 在 payload() 眼里没有区别
    await submit(f);
    expect(f.componentInstance.got).toEqual({ cover: url });
  });

  it('上传失败：服务端文案挂在字段下方，框里旧值不动（可重选或手输兜底）', async () => {
    const f = await setup(() => Promise.reject(new Error('invalid_resource_type')));
    f.componentInstance.fields.set([{ name: 'cover', label: '封面', type: 'image' }]);
    f.componentInstance.open.set(true);
    f.componentInstance.value.set({ cover: 'https://cdn.test/old.png' });
    f.detectChanges();

    await choose(f);

    expect(el<HTMLElement>(f, '.err').textContent).toContain('invalid_resource_type');
    expect(el<HTMLInputElement>(f, 'input[name="cover"]').value).toBe('https://cdn.test/old.png');
    expect(f.nativeElement.querySelector('.modal')).toBeTruthy();
  });

  /**
   * Field.placeholder 与 label 一样是**词条键**（页面侧只写键，见 pages/*-fields.ts）：
   * 渲染时必须过 `| t`。漏了这一步既不报错也不影响提交 —— 输入框里明晃晃地摆着
   * `risk.rule.name_hint` 给运营看，只有盯着界面才发现。
   */
  it('placeholder 是词条键时渲染译文（不是把键名摆给用户看）', async () => {
    const f = await setup();
    // 三个分支各一条：text 走 @default、textarea 走自己的分支 —— 两处各写了一遍绑定，
    // 漏一处就只在一半字段上露出键名（实测：只钉 text 的话，把 textarea 那处改回裸值照样全绿）
    f.componentInstance.fields.set([
      { name: 'name', label: '规则名', type: 'text', placeholder: 'risk.rule.name_hint' },
      { name: 'note', label: '说明', type: 'textarea', placeholder: 'withdraw.set_min_hint' },
      { name: 'sort', label: '排序', type: 'number', placeholder: 'payment.sort_hint' },
      { name: 'raw', label: '未抽取', type: 'text', placeholder: '还没抽取的字面量' },
    ]);
    f.componentInstance.open.set(true);
    f.detectChanges();

    expect(el<HTMLInputElement>(f, 'input[name="name"]').placeholder).toBe(
      '评估器日志与命中记录里回显的就是它',
    );
    expect(el<HTMLTextAreaElement>(f, 'textarea[name="note"]').placeholder).toBe(
      '高于某档单笔最高时整笔拒绝',
    );
    expect(el<HTMLInputElement>(f, 'input[name="sort"]').placeholder).toBe('数字越小越靠前');
    // 词条表里没有的键原样返回（迁移期那些还没抽取的 placeholder 照旧显示）
    expect(el<HTMLInputElement>(f, 'input[name="raw"]').placeholder).toBe('还没抽取的字面量');
  });

  /**
   * ④ 必填要落在**原生控件**上。审计实测（真 Chrome，/admins 的「+ 新建」）：空表单
   * `requestSubmit()` **真把 POST /admin/v1/user 发出去了**，6 个字段里没有一个带
   * `required` / `aria-required` / `aria-invalid`（`{"n":"username","required":false,
   * "ariaRequired":null}`）；界面上只有一枚装饰性的 `.req` 星号。
   *
   * ⚠ file / switch 两种**故意不发**（`req()` 里挡掉）：导入弹框「没选文件」要照发请求、
   * 由服务端回 422「请上传 Excel 文件」说明原因（/tmp/angular_admins_e2e.mjs:577 钉着这条）。
   * 绑定照写在这两处，所以这条断言测的是 `req()` 的挡板，不是「那两处忘了绑」。
   */
  it('required 落到原生控件上：text/textarea/select/multi/number 都带，file/switch 故意不带', async () => {
    const f = await setup();
    f.componentInstance.fields.set([
      { name: 'name', label: '名称', type: 'text', required: true },
      { name: 'note', label: '说明', type: 'textarea', required: true },
      {
        name: 'lang',
        label: '语言',
        type: 'select',
        required: true,
        options: [{ value: 'zh', label: '中文' }],
      },
      {
        name: 'roles',
        label: '角色',
        type: 'multi',
        required: true,
        options: [{ value: 'r1', label: '甲' }],
      },
      { name: 'num', label: '排序', type: 'number', required: true },
      { name: 'opt', label: '选填', type: 'text' },
      { name: 'upfile', label: '文件', type: 'file', required: true },
      { name: 'on', label: '状态', type: 'switch', required: true },
    ]);
    f.componentInstance.open.set(true);
    f.detectChanges();

    expect(el<HTMLInputElement>(f, 'input[name="name"]').required).toBe(true);
    expect(el<HTMLTextAreaElement>(f, 'textarea[name="note"]').required).toBe(true);
    expect(el<HTMLSelectElement>(f, 'select[name="lang"]').required).toBe(true);
    expect(el<HTMLSelectElement>(f, 'select[name="roles"]').required).toBe(true);
    expect(el<HTMLInputElement>(f, 'input[name="num"]').required).toBe(true);
    // 选填的：一个都不许有（required 全发就是换了一种骗人）
    expect(el<HTMLInputElement>(f, 'input[name="opt"]').required).toBe(false);
    // 两种故意不发的：file 归服务端判（导入弹框那条真机读数），switch 没有「必填」语义
    expect(el<HTMLInputElement>(f, 'input[name="upfile"]').required).toBe(false);
    expect(el<HTMLInputElement>(f, 'input[name="on"]').required).toBe(false);
  });

  /**
   * ⑥ 有上限的字段：原生 `maxlength` + `n/上限` 字数指示。
   * 起因是实名驳回：`prompt` 没有 maxlength 也没有计数器，501 字按确定 ⇒ 服务端 422
   * 「note 不能超过 500 个字符」，而 **prompt 一关、整段理由就没了**（驳回还是不可逆操作）。
   */
  it('maxlength：上限落在框上、字数指示随输入走；没有上限的字段一个指示都不出', async () => {
    const f = await setup();
    f.componentInstance.fields.set([
      { name: 'note', label: '驳回原因', type: 'textarea', maxlength: 500 },
      { name: 'free', label: '无上限', type: 'text' },
    ]);
    f.componentInstance.open.set(true);
    f.detectChanges();

    const ta = el<HTMLTextAreaElement>(f, 'textarea[name="note"]');
    expect(ta.getAttribute('maxlength')).toBe('500');
    expect(el<HTMLElement>(f, '.chars').textContent).toBe('0/500');
    // 指示只跟着 maxlength 字段走：全渲染出来的数量正是 1
    expect(f.nativeElement.querySelectorAll('.chars').length).toBe(1);
    expect(el<HTMLInputElement>(f, 'input[name="free"]').getAttribute('maxlength')).toBeNull();

    ta.value = '证件模糊';
    ta.dispatchEvent(new Event('input', { bubbles: true }));
    f.detectChanges();
    expect(el<HTMLElement>(f, '.chars').textContent).toBe('4/500');
  });
});
