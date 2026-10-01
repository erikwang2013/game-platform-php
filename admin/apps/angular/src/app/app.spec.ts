/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { App } from './app';
import { I18n, use } from './core/i18n/i18n';

describe('App', () => {
  beforeEach(async () => {
    localStorage.clear();
    // 语言真值在模块级（模块加载时读一次偏好）⇒ 用例里要显式定，不能靠落盘
    use('zh');
    await TestBed.configureTestingModule({
      imports: [App],
      // 模板用了 RouterLink / RouterOutlet，测试宿主须自带 Router 依赖，否则渲染即 NG0201（ActivatedRoute）
      providers: [provideRouter([])],
    }).compileComponents();
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
