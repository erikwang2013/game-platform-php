/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Msg, t } from '../core/i18n/i18n';
import { MeExport } from './me-export';

/**
 * `Msg` 是**两态**（词条键 / 服务端原文）：只有键那一态的读点需要解析，原文那一态原样透出。
 * **故意不统一**：把「服务端拒绝」那一处也裹进来，就再也证明不了「原文透出、不吞成导出失败」这一支。
 */
const txt = (v: Msg): string => (typeof v === 'string' ? v : t(v.key, v.params));

/**
 * 「导出我的数据」卡片的钉子。四件容易退化成摆设的事：
 * ① **导出只由点击触发**（页面自己不抓数据，更不在首屏就白拉一份全量导出）；
 * ② 落盘文件名与屏幕上那句时刻全部来自**服务端 `exported_at`**，本机时钟不掺进来；
 * ③ 服务端拒绝时原因原样透出，**且不落盘**（失败还给一份文件是最坏的结果）；
 * ④ 「每类上限 100 条 / 游戏币余额不在其中」两句必须在按钮**之前** —— 别让用户点完才看见。
 *
 * ⚠ 固定装置的时刻是**故意的过去日期**：若实现退回本机时钟，用例必须变红。
 */
const EXPORTED_AT = '2025-03-04 10:00:00';
const NAME = 'game-platform-export-20250304100000.json';

const payload = (): Record<string, unknown> => ({
  profile: { username: 'bob', nickname: null, email: null, phone: null },
  wallet: { balance: '10.00', total_earned: '20.00', total_spent: '10.00' },
  transactions: [{ id: 'T1' }, { id: 'T2' }],
  exchange_records: [{ id: 'E1' }],
  deposit_orders: [{ id: 'D1' }, { id: 'D2' }, { id: 'D3' }],
  withdraw_orders: [{ id: 'W1' }, { id: 'W2' }, { id: 'W3' }, { id: 'W4' }],
  oauth_accounts: [],
  exported_at: EXPORTED_AT,
});

const todayDigits = (): string => {
  const d = new Date();
  const p = (n: number): string => String(n).padStart(2, '0');
  return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}`;
};

const realCreate = URL.createObjectURL;
const realRevoke = URL.revokeObjectURL;

/** jsdom 不实现 Blob URL ⇒ 直接接管两个全局，用例自己记账 */
const interceptBlobUrls = (): { created: string[]; revoked: string[] } => {
  const created: string[] = [];
  const revoked: string[] = [];
  URL.createObjectURL = ((_b: Blob) => {
    const u = `blob:test-${created.length}`;
    created.push(u);
    return u;
  }) as typeof URL.createObjectURL;
  URL.revokeObjectURL = ((u: string) => {
    revoked.push(String(u));
  }) as typeof URL.revokeObjectURL;
  return { created, revoked };
};

describe('MeExport 导出我的数据', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<MeExport>;

  type Probe = { run(): void; busy(): boolean; ok(): boolean; msg(): Msg };
  const probe = (): Probe => fixture.componentInstance as unknown as Probe;
  const btn = (): HTMLButtonElement => fixture.nativeElement.querySelector('button');
  const alert = (): HTMLElement | null => fixture.nativeElement.querySelector('.alert');

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
    fixture = TestBed.createComponent(MeExport);
    fixture.detectChanges();
  });

  afterEach(() => {
    URL.createObjectURL = realCreate;
    URL.revokeObjectURL = realRevoke;
    vi.restoreAllMocks();
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('页面自己不抓数据 —— 导出只由点击触发', () => {
    http.expectNone(() => true);
    expect(btn().textContent?.trim()).toBe('下载 JSON');
    expect(btn().disabled).toBe(false);
    expect(alert()).toBeNull();
  });

  it('点按钮才发 GET /api/v1/user/export-data，回包后落盘并报出文件名与服务端时刻', () => {
    const { created } = interceptBlobUrls();
    const downloads: string[] = [];
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
      this: HTMLAnchorElement,
    ) {
      downloads.push(this.getAttribute('download') ?? '');
    });

    // 请求必须锚在这一点击上：不点就没有请求
    btn().click();
    fixture.detectChanges();
    expect(btn().textContent).toContain('导出中…');
    expect(btn().disabled).toBe(true);

    const req = http.expectOne((r) => r.url === '/api/v1/user/export-data');
    expect(req.request.method).toBe('GET');
    // 导出是 GET，不该带任何查询参数（拉的就是全量快照）
    expect(req.request.params.keys()).toEqual([]);
    req.flush({ code: 0, message: 'Data export ready', data: payload() });
    fixture.detectChanges();

    // 落盘：名字只由服务端 exported_at 生成，且恰落一份
    expect(created).toHaveLength(1);
    expect(downloads).toEqual([NAME]);
    // 屏幕上那句话：服务端时刻 + 四类各自计数，没有本机时钟
    expect(txt(probe().msg())).toContain(`已导出 ${NAME}`);
    expect(txt(probe().msg())).toContain('服务端生成于');
    expect(txt(probe().msg())).toContain('流水 2 · 兑换 1 · 充值 3 · 提现 4');
    expect(txt(probe().msg())).not.toContain(todayDigits());
    expect(probe().busy()).toBe(false);
    expect(btn().textContent).toContain('下载 JSON');
    expect(alert()?.getAttribute('role')).toBe('status');
  });

  it('服务端拒绝时原样透出原因、不落盘，改完还能再点一次', () => {
    const { created } = interceptBlobUrls();
    // 真实现会让 jsdom 去导航 blob:（控制台刷「Not implemented」）⇒ 记账即可
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    btn().click();
    http
      .expectOne((r) => r.url === '/api/v1/user/export-data')
      .flush({ code: 500, message: '数据导出失败，请稍后重试', data: null });
    fixture.detectChanges();

    // 原文透出，不吞成「导出失败」
    expect(probe().msg()).toBe('数据导出失败，请稍后重试');
    expect(probe().ok()).toBe(false);
    expect(probe().busy()).toBe(false);
    expect(alert()?.getAttribute('role')).toBe('alert');
    // 失败绝不能给出一份文件
    expect(created).toEqual([]);
    expect(click).not.toHaveBeenCalled();

    // busy 已复位 ⇒ 再点必须真的再发一次（不是卡在「导出中…」）
    btn().click();
    http
      .expectOne((r) => r.url === '/api/v1/user/export-data')
      .flush({ code: 0, message: 'ok', data: payload() });
    expect(created).toHaveLength(1);
  });

  it('「每类上限 100 条」「游戏币余额不在这份文件里」两句摆在按钮之前', () => {
    const hint = fixture.nativeElement.querySelector('.hint') as HTMLElement;
    const bold = Array.from(hint.querySelectorAll('b')).map((b) => b.textContent?.trim());
    expect(bold).toEqual(['每类明细上限 100 条', '游戏币余额不在这份文件里']);
    // 必须在按钮之前：点完才看见「这不是全部历史」＝ 已经晚了
    expect(hint.compareDocumentPosition(btn()) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });
});
