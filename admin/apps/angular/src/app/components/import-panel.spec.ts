/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Component } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { describe, expect, it } from 'vitest';
import { use } from '../core/i18n/i18n';
import { ImportPanel } from './import-panel';

@Component({
  selector: 'app-import-host',
  imports: [ImportPanel],
  template: `<ui-import path="/admin/v1/import/users" title="import.users" (done)="loads = loads + 1" />`,
})
class Host {
  loads = 0;
}

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
 * Excel 批量导入（POST /admin/v1/import/users，multipart 单文件段 `file`）。
 *
 * 这个端点回的是**统一信封**（`ImportController::users` 走 `$this->success`），
 * 所以它走 `Api.envelope` 而不是 aetherupload 那条 `Api.raw` 的裸路。
 */
describe('ImportPanel（Excel 批量导入）', () => {
  const setup = async (): Promise<ComponentFixture<Host>> => {
    use('zh');
    TestBed.configureTestingModule({
      imports: [Host],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    const f = TestBed.createComponent(Host);
    await f.whenStable();
    return f;
  };

  const el = <T extends Element>(f: ComponentFixture<Host>, sel: string): T => {
    const node = f.nativeElement.querySelector(sel) as T | null;
    if (!node) throw new Error(`未找到 ${sel}`);
    return node;
  };

  /** 打开导入弹框（首屏只有一个按钮：工具条那个） */
  const openForm = (f: ComponentFixture<Host>): void => {
    el<HTMLButtonElement>(f, 'button').click();
    f.detectChanges();
  };

  /** `whenStable()` 只等 Angular 自己排的任务；提交是 `await` 出来的裸 promise 链，要放一个宏任务 */
  const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));
  /** 请求应答之后：等 continuation 落地，再把信号写进 DOM */
  const settle = async (f: ComponentFixture<Host>): Promise<void> => {
    await tick();
    f.detectChanges();
  };

  const pick = (f: ComponentFixture<Host>, file: File): void => {
    pickInto(el<HTMLInputElement>(f, 'input[type="file"]'), file);
    f.detectChanges();
  };

  const submit = async (f: ComponentFixture<Host>): Promise<void> => {
    el<HTMLFormElement>(f, 'form').dispatchEvent(
      new SubmitEvent('submit', { bubbles: true, cancelable: true }),
    );
    await settle(f);
  };

  /**
   * 这条同时钉三件事，缺一条都会静默坏在真机上：
   *  1. 体是 `FormData`（不是 JSON 对象）；
   *  2. `file` 段的值是**那个 File 本体**（只断言段名 `file` 存在的话，值是 `undefined`
   *     或 `[object File]` 也照样绿）；
   *  3. **`Content-Type` 一个都不许手写** —— 手写 multipart/form-data 会把 boundary 丢掉，
   *     服务端解析不出任何段（`$request->file('file')` 恒为 null，界面只看到「请上传 Excel 文件」）。
   */
  it('POST 是 multipart：file 段带的是 File 本体，且没有手写 Content-Type', async () => {
    const f = await setup();
    const http = TestBed.inject(HttpTestingController);

    openForm(f);
    pick(f, new File(['username,password'], 'users.xlsx'));
    await submit(f);

    const req = http.expectOne('/admin/v1/import/users');
    expect(req.request.method).toBe('POST');
    const body = req.request.body as FormData;
    expect(body).toBeInstanceOf(FormData);
    const sent = body.get('file');
    expect(sent).toBeInstanceOf(File);
    expect((sent as File).name).toBe('users.xlsx');
    // **手写就会丢 boundary**：交给 HttpClient/浏览器自己定，这里只钉「没写」
    expect(req.request.headers.has('Content-Type')).toBe(false);

    req.flush({
      code: 0,
      message: '导入完成',
      data: {
        total: 3,
        success: 2,
        failed: 1,
        errors: [{ row: 4, reason: '用户名已存在' }],
      },
    });
    await settle(f);
  });

  /**
   * 没选文件：**不发那个段**（原生表单里它是个空文件），交给服务端报「请选择文件」——
   * 与 react 树同一条路（那边是 buildPayload 把空值跳过）。发一个空段只是让服务端收到一个
   * 没名字的文件，错误信息反而更含糊。
   */
  it('没选文件：不发 file 段（服务端回 422 原文，框不关）', async () => {
    const f = await setup();
    const http = TestBed.inject(HttpTestingController);

    openForm(f);
    await submit(f);

    const req = http.expectOne('/admin/v1/import/users');
    expect((req.request.body as FormData).has('file')).toBe(false);

    req.flush({ code: 422, message: '请上传 Excel 文件', data: null });
    await settle(f);

    expect(el<HTMLElement>(f, '.alert').textContent).toContain('请上传 Excel 文件');
    expect(f.nativeElement.querySelector('form')).toBeTruthy(); // 框不关，改文件重试
  });

  /**
   * 成功后的**读数**：summary 三数照服务端给的原样显示，逐行失败表照**服务端给的行号**显示
   * （前端不重算 —— 重算一次就多一个和后端对不上的口径）。且成功 > 0 时必须提「导入的账号
   * 没有角色」：这个端点不建角色关联，而鉴权是遍历 roles 聚合的 ⇒ 不提就是让运营把一批
   * 登进去什么都看不见的账号发下去。
   */
  it('成功后：报表显示三数与逐行原因，并提示新账号没有角色', async () => {
    const f = await setup();
    const http = TestBed.inject(HttpTestingController);

    openForm(f);
    pick(f, new File(['x'], 'users.xlsx'));
    await submit(f);
    http.expectOne('/admin/v1/import/users').flush({
      code: 0,
      message: '导入完成',
      data: { total: 3, success: 2, failed: 1, errors: [{ row: 4, reason: '用户名已存在' }] },
    });
    await settle(f);

    const text = f.nativeElement.textContent as string;
    expect(text).toContain('文件内 3 行 · 成功 2 行 · 失败 1 行');
    // 表头走 col.row / col.reason（不是裸字段名），行号是服务端给的 4
    expect(text).toContain('行号');
    expect(text).toContain('用户名已存在');
    expect(text).toContain('导入的账号不会自动获得角色');
    // 回读钩子发了一次（列表要刷出刚导进来那些账号）
    expect(f.componentInstance.loads).toBe(1);
    // 表单框已关（读数另开一个框）
    expect(f.nativeElement.querySelector('form')).toBeNull();
  });

  it('一条都没导进去时：不提角色那茬（没账号，警告是噪声）', async () => {
    const f = await setup();
    const http = TestBed.inject(HttpTestingController);

    openForm(f);
    pick(f, new File(['x'], 'users.xlsx'));
    await submit(f);
    http.expectOne('/admin/v1/import/users').flush({
      code: 0,
      message: '导入完成',
      data: { total: 1, success: 0, failed: 1, errors: [{ row: 2, reason: '密码不合规' }] },
    });
    await settle(f);

    const text = f.nativeElement.textContent as string;
    expect(text).toContain('文件内 1 行 · 成功 0 行 · 失败 1 行');
    expect(text).not.toContain('导入的账号不会自动获得角色');
    // `done` 照发（不是「成功才发」）：这里判的是「流程结束」，多刷一次列表无害，
    // 而按 success 分叉发信号只会多一个分支、多一份要记住的语义
    expect(f.componentInstance.loads).toBe(1);
  });
});
