/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { money } from '../core/api.service';
import { PlaylogsPage } from './playlogs';

type Probe = {
  sign(v: number | string): string;
  abs(v: number | string): string;
};

/**
 * 负号**字符**的钉子。
 *
 * 钉它的理由：`sign()` 此前回的是排版减号 `−`(U+2212)，而钱包流水那一屏走的是
 * `money()` —— 它只归一、不产符号，透传的是后端 bcmath 串里本来就有的 ASCII
 * `-`(U+002D)。于是同一个应用里「−12.34」（游戏流水页）与「-12.34」（钱包页）
 * 是**两个不同的字符**：肉眼在等宽字体下几乎分不出来，真机脚本也不会因此报错，
 * 而字体缺 U+2212 字形时还会掉成豆腐块。现在统一成 ASCII `-`。
 *
 * 断言读的是**码点**而不是把字符写进期望值：`toBe('-')` 在复制粘贴与查找替换里
 * 会被无声带偏，`codePointAt` 不会 —— 这也正是当初它能藏到现在的原因。
 *
 * 特意**不渲染整页**（那要造一堆服务桩，钉子会退化成测桩）：只钉这两个纯函数，
 * 它们正是模板表达式 `{{ sign(v) }}{{ money(abs(v)) }}` 的全部零件。
 * 渲染那一层由 /tmp 的真机脚本管，两层分工见交付说明。
 */
describe('PlaylogsPage 负号字符', () => {
  let http: HttpTestingController;
  let page: PlaylogsPage;

  const probe = (): Probe => page as unknown as Probe;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    // inject(Api) 写在字段初始化器里 ⇒ 必须在注入上下文里构造
    page = TestBed.runInInjectionContext(() => new PlaylogsPage());
    // 构造函数里 load(1) 发一发，先冲掉，后续用例各管各的
    http
      .expectOne((r) => r.url === '/api/v1/game/play-logs')
      .flush({ code: 0, message: 'ok', data: { items: [], page: 1, last_page: 1, total: 0 } });
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('负号是 ASCII U+002D，且与 money() 产出的符号同码点', () => {
    // 与钱包页同字符：那边渲染的是 money(v) 的结果，这边是 sign(v) + money(abs(v))
    expect(probe().sign('-12.34').codePointAt(0)).toBe(0x2d);
    expect(money('-12.34').codePointAt(0)).toBe(0x2d);
    expect(probe().sign('-12.34').codePointAt(0)).toBe(money('-12.34').codePointAt(0));

    // 模板里真正拼出来的那个串：一个 ASCII 负号 + 千分位，整个串都是 ASCII
    const rendered = probe().sign('-1234.5678') + money(probe().abs('-1234.5678'));
    expect(rendered).toBe('-1,234.5678');
    expect([...rendered].every((c) => c.codePointAt(0)! < 0x80)).toBe(true);
  });

  it('正数与空值仍是加号（这一支的语义与改前一致，未被顺手改掉）', () => {
    expect(probe().sign('12.34')).toBe('+');
    expect(probe().sign('0.00')).toBe('+');
    // 现状：空值走 `v ?? ''` ⇒ 不以 '-' 开头 ⇒ 回 '+'（本次刻意不改，只锁行为）
    expect(probe().sign('')).toBe('+');
    expect(probe().sign(null as unknown as string)).toBe('+');
  });
});
