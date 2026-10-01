/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { App } from './app';
import { I18n, use } from './core/i18n/i18n';

/** 跳转落点（退出后 router.navigate(['/login'])）：没有这条路由，navigate 会以「无法匹配」被拒 */
@Component({ selector: 'app-blank', template: '' })
class Blank {}

describe('App', () => {
  let http: HttpTestingController;

  beforeEach(async () => {
    localStorage.clear();
    // 语言真值在模块级（模块加载时读一次偏好）⇒ 用例里要显式定，不能靠落盘
    use('zh');
    await TestBed.configureTestingModule({
      imports: [App],
      // 模板用了 RouterLink / RouterOutlet，测试宿主须自带 Router 依赖，否则渲染即 NG0201（ActivatedRoute）；
      // 外壳现在自己注入了 Api（退出要打服务端吊销）⇒ 还得有 HttpClient
      providers: [
        provideRouter([{ path: 'login', component: Blank }]),
        provideHttpClient(),
        provideHttpClientTesting(),
      ],
    }).compileComponents();
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    localStorage.clear();
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(App);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  it('should render the console brand', async () => {
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    // 原断言指向 CLI 脚手架的 <h1>Hello, game-admin-angular</h1>，当前 app.html 已无该节点（只剩 .brand / 侧栏外壳）
    expect(compiled.querySelector('.brand')?.textContent).toContain('游戏运营后台');
  });

  /**
   * 语言切换器的行为钉子（这棵树原先零 i18n）。三条一起断：
   *  1. 13 种**平铺**且用**母语名**（用户看不懂当前界面语言时也要能选对），当前项打点；
   *  2. 选中即改界面 —— 且**不手工触发变更检测**，靠非纯管道读语言信号自己重绘
   *     （把 `pure: false` 改回纯管道这条就红：纯管道按入参缓存，切语言时入参没变 ⇒ 界面不动）；
   *  3. 选中即持久化（刷新后还在）。
   */
  it('顶栏语言菜单：13 种平铺 + 母语名 + 当前打点；选中即重绘并持久化', async () => {
    const fixture = TestBed.createComponent(App);
    fixture.autoDetectChanges();
    await fixture.whenStable();
    const el = fixture.nativeElement as HTMLElement;

    // 服务端/落盘的语言是 zh ⇒ 侧栏是中文
    expect(el.querySelector('.nav a')?.textContent).toContain('仪表盘');

    el.querySelector<HTMLButtonElement>('.lang .btn')!.click();
    await fixture.whenStable();

    const items = [...el.querySelectorAll<HTMLButtonElement>('.lang-menu button')];
    expect(items.length).toBe(13);
    expect(items.map((b) => b.textContent!.trim())).toEqual([
      'English',
      '● 简体中文',
      '日本語',
      '한국어',
      'Русский',
      'Deutsch',
      'Français',
      'Español',
      'Português',
      'हिन्दी',
      'العربية',
      'বাংলা',
      'Bahasa Indonesia',
    ]);

    // 点「English」：界面上没被手工 detectChanges，全靠管道读信号自己重绘
    items[0]!.click();
    await fixture.whenStable();
    expect(el.querySelector('.nav a')?.textContent).toContain('Dashboard');
    expect(el.querySelector('.lang-menu')).toBeNull(); // 选完自动收起
    expect(localStorage.getItem('ga_lang')).toBe('en');
    expect(TestBed.inject(I18n).lang()).toBe('en');
  });

  /**
   * 账号菜单（个人资料 / 修改密码 / 退出）。退出这一条尤其要断：
   * 原实现 `logout()` 只清本地（`auth.clear()`）就完事，**服务端一次都没被打到** ——
   * access token 到期前仍然有效、还能继续换新 token，refresh token 也没被吊销。
   */
  it('账号菜单：三项都在；退出先打服务端吊销，再清本地并跳登录页', async () => {
    localStorage.setItem('ga_access_token', 'T0KEN');
    localStorage.setItem('ga_refresh_token', 'REFRESH');
    const fixture = TestBed.createComponent(App);
    fixture.autoDetectChanges();
    await fixture.whenStable();
    const el = fixture.nativeElement as HTMLElement;

    el.querySelector<HTMLButtonElement>('.umenu .btn')!.click();
    await fixture.whenStable();
    const items = [...el.querySelectorAll<HTMLButtonElement>('.umenu-panel button')];
    expect(items.map((b) => b.textContent!.trim())).toEqual(['个人资料', '修改密码', '退出']);

    items[2]!.click();
    const req = http.expectOne((r) => r.url === '/admin/v1/profile/logout');
    expect(req.request.method).toBe('POST');
    // 吊销请求必须**带令牌**发出去（不带就是匿名请求，服务端吊销不了任何东西）
    expect(req.request.headers.get('Authorization')).toBe('Bearer T0KEN');
    req.flush({ code: 0, message: 'ok', data: {} });
    await fixture.whenStable();

    expect(localStorage.getItem('ga_access_token')).toBeNull();
    expect(localStorage.getItem('ga_refresh_token')).toBeNull();
    expect(localStorage.getItem('ga_user')).toBeNull();
    expect(TestBed.inject(Router).url).toBe('/login');
  });

  /**
   * 改密码：字段是 `password` 类型 ⇒ **不回显**（预填一个密码进 DOM 等于把它写在了页面上），
   * 提交走 PUT /admin/v1/profile/password，两个字段都必填。
   * 顺带钉住弹框是 @defer 的：没点开之前这套字段渲染器不在首屏 bundle 里（也就不会在 DOM 里）。
   */
  it('修改密码：弹框按需加载，两个密码框不回显；提交走 PUT /profile/password', async () => {
    localStorage.setItem('ga_access_token', 'T0KEN');
    const fixture = TestBed.createComponent(App);
    fixture.autoDetectChanges();
    await fixture.whenStable();
    const el = fixture.nativeElement as HTMLElement;

    expect(el.querySelector('ui-form')).toBeNull(); // @defer：没点开就不在 DOM 里

    el.querySelector<HTMLButtonElement>('.umenu .btn')!.click();
    await fixture.whenStable();
    [...el.querySelectorAll<HTMLButtonElement>('.umenu-panel button')][1]!.click();
    // @defer 的块要等动态 import 落地，多等一轮
    await fixture.whenStable();
    await fixture.whenStable();

    const inputs = [...el.querySelectorAll<HTMLInputElement>('ui-form input')];
    expect(inputs.map((i) => i.getAttribute('name'))).toEqual(['old_password', 'new_password']);
    expect(inputs.map((i) => i.type)).toEqual(['password', 'password']);
    expect(inputs.map((i) => i.value)).toEqual(['', '']);

    inputs[0]!.value = 'OldPass123';
    inputs[1]!.value = 'NewPass456';
    el.querySelector<HTMLButtonElement>('ui-form button[type=submit]')!.click();

    const req = http.expectOne((r) => r.url === '/admin/v1/profile/password');
    expect(req.request.method).toBe('PUT');
    // 改密码是**全量**提交（不像改资料那样跳过未改动的键）
    expect(req.request.body).toEqual({ old_password: 'OldPass123', new_password: 'NewPass456' });
    req.flush({ code: 0, message: '密码修改成功', data: {} });
    await fixture.whenStable();

    // 成功即关框（ui-form 的宿主元素在 @defer 触发后就常驻了，要看它内部的 .modal 有没有收）
    expect(el.querySelector('ui-form .modal')).toBeNull();
    expect(el.querySelector('.shell-note')?.textContent).toContain('密码修改成功');
  });

  it('点遮罩收起语言菜单，不切语言', async () => {
    const fixture = TestBed.createComponent(App);
    fixture.autoDetectChanges();
    await fixture.whenStable();
    const el = fixture.nativeElement as HTMLElement;

    el.querySelector<HTMLButtonElement>('.lang .btn')!.click();
    await fixture.whenStable();
    expect(el.querySelector('.lang-menu')).not.toBeNull();

    el.querySelector<HTMLElement>('.lang-catch')!.click();
    await fixture.whenStable();
    expect(el.querySelector('.lang-menu')).toBeNull();
    expect(TestBed.inject(I18n).lang()).toBe('zh'); // 遮罩只关菜单
  });
});
