/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { MeNick } from './me-nick';

/**
 * 改昵称的钉子（对齐 apps/react 的 Me.tsx:36-56，此前本树 `updateProfile` 零消费方）。
 *
 * 钉四件：
 * ① 空值/超长在**本地**挡住、请求一个都不发 —— 服务端 nickname 是 `nullable`，
 *    送空串会把昵称**清成空**，本地不挡就是个静默的数据丢失口子；
 * ② 保存发 PUT /user/profile，请求体只带 trim 过的 nickname；
 * ③ 服务端拒绝时原因原样透出，且弹框**不关**、按钮不卡死（还能接着改）；
 * ④ 取消之后再打开，预填的是**服务端资料**而不是上次输了一半的值。
 */
describe('MeNick 改昵称', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<MeNick>;

  const el = (): HTMLElement => fixture.nativeElement as HTMLElement;

  /** 按文案找按钮：模板改了文案用例该一起红，比按下标取稳 */
  const btn = (text: string): HTMLButtonElement => {
    const hit = [...el().querySelectorAll('button')].find((b) => b.textContent?.trim() === text);
    if (!hit) throw new Error(`没有文案为「${text}」的按钮`);
    return hit as HTMLButtonElement;
  };
  const alert = (): string => el().querySelector('.alert')?.textContent?.trim() ?? '';
  const modal = (): Element | null => el().querySelector('.modal');

  const input = (): HTMLInputElement => {
    const hit = el().querySelector('input');
    if (!hit) throw new Error('没有输入框（弹框没打开？）');
    return hit as HTMLInputElement;
  };

  const open = (): void => {
    btn('改昵称').click();
    fixture.detectChanges();
  };
  const type = (v: string): void => {
    input().value = v;
    input().dispatchEvent(new Event('input'));
    fixture.detectChanges();
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        // ApiBase 的字段初始化器要 inject(Router)（401 单飞刷新后跳登录）
        provideRouter([]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(MeNick);
    fixture.componentRef.setInput('nickname', '旧昵称');
    fixture.detectChanges();
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('首屏只有按钮：弹框与输入框都不在 DOM 里，且页面自己不抓数据', () => {
    http.expectNone(() => true);
    expect(btn('改昵称')).toBeTruthy();
    expect(modal()).toBeNull();
    expect(el().querySelector('input')).toBeNull();
  });

  it('打开时用当前昵称预填 —— 不让用户在一个空框里重打一遍', () => {
    open();
    expect(modal()).not.toBeNull();
    expect(input().value).toBe('旧昵称');
  });

  it('空昵称（含只有空白）本地挡住：出提示，且一个请求都不发', () => {
    open();
    type('   ');
    btn('保存').click();
    fixture.detectChanges();

    expect(alert()).toBe('昵称不能为空');
    http.expectNone(() => true);
  });

  it('超过 50 字本地挡住：出提示，且一个请求都不发', () => {
    open();
    type('长'.repeat(51));
    btn('保存').click();
    fixture.detectChanges();

    expect(alert()).toBe('昵称不能超过 50 字');
    http.expectNone(() => true);
  });

  it('保存发 PUT /api/v1/user/profile，请求体只带 trim 过的 nickname；成功后收起弹框并通知父组件', () => {
    let saved = 0;
    fixture.componentInstance.saved.subscribe(() => (saved += 1));
    open();
    type('  新昵称  ');
    btn('保存').click();
    fixture.detectChanges();

    const req = http.expectOne((r) => r.url === '/api/v1/user/profile');
    expect(req.request.method).toBe('PUT');
    // 请求体逐字对：多塞字段会被服务端白名单忽略，少带 nickname 则这个接口没意义
    expect(req.request.body).toEqual({ nickname: '新昵称' });
    req.flush({
      code: 0,
      message: 'ok',
      data: { id: 'U1', username: 'bob', nickname: '新昵称', avatar: '' },
    });
    fixture.detectChanges();

    // 父组件据此回读资料（PUT 的回包只有 5 个字段，不是完整资料）
    expect(saved).toBe(1);
    expect(modal()).toBeNull();
  });

  it('服务端拒绝时原文透出、弹框不关、按钮不卡死（还能接着改）', () => {
    open();
    type('新昵称');
    btn('保存').click();
    fixture.detectChanges();
    http
      .expectOne((r) => r.url === '/api/v1/user/profile')
      .flush({ code: 422, message: '昵称已被使用' }, { status: 422, statusText: 'Unprocessable Entity' });
    fixture.detectChanges();

    expect(alert()).toBe('昵称已被使用');
    expect(modal()).not.toBeNull();
    expect(btn('保存').disabled).toBe(false);
    expect(btn('保存').textContent?.trim()).toBe('保存');
  });

  it('取消后不留下输了一半的值：再打开预填的仍是当前昵称', () => {
    open();
    type('输了一半');
    btn('关闭').click();
    fixture.detectChanges();
    expect(modal()).toBeNull();

    open();
    expect(input().value).toBe('旧昵称');
    expect(alert()).toBe('');
  });
});
