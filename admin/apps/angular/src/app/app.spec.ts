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
        provideRouter([
          { path: 'login', component: Blank },
          // 侧栏选中态那条用例要真导航一次，得有落点路由
          { path: 'dashboard', component: Blank },
        ]),
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

  /**
   * 侧栏选中态（`app.html:11` 的 `<a [routerLink] routerLinkActive="active">`）。
   *
   * 这颗钉子是**行为级**的，因为缺陷正是"行为不存在"：`app.ts` 原先只 import 了
   * `RouterLink` 而**没有 `RouterLinkActive`**，那个 attribute 于是是个惰性 attribute ——
   * 不报错、不警告，`.nav a.active` 永远命中 0 个。后果是侧栏没有"我在哪一页"，
   * 而 `styles.scss` 里为它写的底色 + 左侧指示条两条规则**从来没跑到过**。
   *
   * 只断"源码里有 RouterLinkActive 这个词"是不够的（那是恒真式，而且 attribute 名里就有），
   * 所以这里真导航一次、查渲染树。
   */
  it('侧栏选中态：当前路由那一条带 .active，且只有一条', async () => {
    const fixture = TestBed.createComponent(App);
    fixture.autoDetectChanges();
    await fixture.whenStable();
    const el = fixture.nativeElement as HTMLElement;

    // 起点：url 是 `/`，没有任何一条该被选中 —— 少了这条，"全都选中"也会让下面绿
    expect(el.querySelectorAll('.nav a.active').length).toBe(0);

    await TestBed.inject(Router).navigate(['/dashboard']);
    await fixture.whenStable();

    const active = [...el.querySelectorAll<HTMLAnchorElement>('.nav a.active')];
    expect(active.length).toBe(1);
    expect(active[0]!.textContent).toContain('仪表盘');
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

  /**
   * 遮罩的**包含块** —— 本文件里唯一一条 jsdom 验不了几何、只能钉结构的用例。
   *
   * `.topbar` 带 `backdrop-filter`（`src/styles.scss:243`），按 CSS Filter Effects L2 它会成为
   * fixed 后代的**包含块** —— 这与「层叠上下文」是两个机制：原实现按层叠上下文推理（注释还在
   * 旧版里），把 `.lang-catch` 挂在 `.lang`/`.umenu` 里，于是 `inset: 0` 对的是顶栏的 padding box
   * 而不是视口，遮罩只盖住顶栏那一条、在下面点内容关不掉菜单（真机实测修前 rect 1200×53 @240,0）。
   *
   * jsdom 不算布局也不算包含块：上面那条 `.click()` 的用例对修前修后一样绿，对这条缺陷是**盲的**
   * （实测 jsdom 连 `getComputedStyle(el).backdropFilter` 都读不到 —— 常量空串 ⇒「祖先链上有没有
   * 包含块属性」这类断言在 jsdom 里恒真，写了就是假绿，不能写）。所以这里只钉结构那一半：
   * 遮罩必须挂在外壳根、且不在 `.topbar` 子树里。把它移回顶栏，这条立刻红。
   * 几何那一半只能真机验（起 dev server 后开菜单：`document.elementFromPoint(720, 600)` 应命中
   * `.lang-catch`，遮罩 rect 应等于视口、且点下去菜单真的关）—— jsdom 里这两条都是零信息。
   */
  it('遮罩挂在外壳根、不在 .topbar 子树里（顶栏的 backdrop-filter 是 fixed 后代的包含块）', async () => {
    const fixture = TestBed.createComponent(App);
    fixture.autoDetectChanges();
    await fixture.whenStable();
    const el = fixture.nativeElement as HTMLElement;

    expect(el.querySelector('.lang-catch')).toBeNull(); // 两个菜单都没开时不该有遮罩

    el.querySelector<HTMLButtonElement>('.lang .btn')!.click();
    el.querySelector<HTMLButtonElement>('.umenu .btn')!.click();
    await fixture.whenStable();

    const catchEls = [...el.querySelectorAll<HTMLElement>('.lang-catch')];
    expect(catchEls.length).toBeGreaterThan(0);
    for (const c of catchEls) {
      // 先断这一条：变异的失败信息才会直指「遮罩又回到包含块里了」
      expect(c.closest('.topbar')).toBeNull();
      expect(c.parentElement).toBe(el); // 直接挂在外壳根上（与 .main 平级）
    }

    // 遮罩收的是「视口坐标系里的点击」，与哪个菜单开着无关
    catchEls.forEach((c) => c.click());
    await fixture.whenStable();
    expect(el.querySelector('.lang-menu')).toBeNull();
    expect(el.querySelector('.umenu-panel')).toBeNull();
  });
});
