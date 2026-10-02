/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * ⚠ 本文件里 `cell('点差费用')` 这类**按中文标签定位**的选择器共 **21 条**，
 * 它们能过是因为**隐含依赖 `FALLBACK === 'zh'`**，而不是因为它们与语言无关：
 *   - `t()` 查不到键时回落到 `TABLE[FALLBACK]`（`core/i18n/i18n.ts`）；
 *   - `src/test-setup.ts` 在每条用例前把语言复位成 `FALLBACK`。
 * 这两处任一被改（`FALLBACK` 换语言、复位钩子被删/被排到用例钩子之后），
 * 本文件会**成片变红**，而红的原因跟页面代码无关 —— 那时先看
 * `src/app/core/i18n/i18n.spec.ts:113`（钉 `FALLBACK === 'zh'` 的那条），别来改这里的选择器。
 *
 * 本批**刻意不动**这些选择器：把断言改成走 `t()` 才能解耦，那是后续批次的事，
 * 现在改会在没有对照读数的情况下同时动「判据」和「被判断的东西」。
 */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ExchangeDirection, ExchangeDone, ExchangeQuote } from '../core/api.service';
import { ExchangePage } from './exchange';

type Sig<T> = { (): T; set(v: T): void };
type Probe = {
  direction: Sig<ExchangeDirection>;
  quote: Sig<ExchangeQuote | null>;
  done: Sig<ExchangeDone | null>;
};

/**
 * 兑换页两个面板（成交结果 + 询价结果）的**金额渲染**钉子。
 *
 * 钉它的理由：这两个面板原先全是裸插值 —— 8 处金额直接摆后端 `DECIMAL(20,8)` 原串，
 * 与同页「账户余额」格的 `money()` 形态不一致（同一个页面两套精度）；且结果面板的两个
 * 金额字段**随方向换位**，`in`/`out` 各有一条渲染路径，靠肉眼只看得到当前那一条。
 *
 * 反面也钉住了：`rate` / `spread_pct` 是**汇率与百分比，不是金额**，不许被顺手货币化
 * （`1 平台币 ≈ 7.12345678` 与 `1.5%` 必须原样，且不挂 title）—— 收编时最容易误伤的就是它们。
 */
describe('ExchangePage 金额渲染', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<ExchangePage>;
  let page: ExchangePage;
  let probe: Probe;

  /** 都挑「旧实现会出错」的量级：>2^53、scale-8 最小非零量、只带尾零、负零 */
  const RAW = {
    platform_amount: '12345678901234567890.12',
    game_amount: '0.00000001',
    spread_fee: '628.5000',
    balance_after: '-0.00000000',
    rate: '7.12345678',
  };

  const cell = (label: string): HTMLElement => {
    const rows = Array.from(fixture.nativeElement.querySelectorAll('.kv')) as HTMLElement[];
    const row = rows.find(
      (r) => (r.querySelector('.muted')?.textContent ?? '').trim() === label,
    );
    if (!row) throw new Error(`面板里没有「${label}」这一格`);
    const v = row.querySelector('.mono');
    if (!v) throw new Error(`「${label}」这一格没有值元素`);
    return v as HTMLElement;
  };

  const render = (o: { direction?: ExchangeDirection; quote?: ExchangeQuote | null; done?: ExchangeDone | null }): void => {
    probe.direction.set(o.direction ?? 'in');
    probe.quote.set(o.quote ?? null);
    probe.done.set(o.done ?? null);
    fixture.detectChanges();
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(ExchangePage);
    page = fixture.componentInstance;
    probe = page as unknown as Probe;
    // 构造函数里拉一次游戏列表（币种挂在游戏上，符号要靠它渲染）
    http.expectOne((r) => r.url === '/api/v1/game/list').flush({
      code: 0,
      message: 'ok',
      data: {
        items: [
          {
            id: 'G1',
            name: '德州扑克',
            slug: 'texas',
            type: 'poker',
            description: '',
            cover_image: '',
            sdk_version: '1',
            platform: 'web',
            region: 'global',
            categories: [],
            currencies: [{ id: 'C1', name: '金币', symbol: '🪙', exchange_rate: RAW.rate }],
          },
        ],
        page: 1,
        last_page: 1,
        total: 1,
      },
    });
    fixture.detectChanges();
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('成交结果 in：支出侧是平台币、到账侧是游戏币，两处都过 money()', () => {
    render({ direction: 'in', done: { exchange_id: 'E1', direction: 'in', ...RAW } });
    expect(cell('支付平台币').textContent!.trim()).toBe('12,345,678,901,234,567,890.12');
    // scale-8 最小非零量：截到 2 位就成了 0.00
    expect(cell('到账游戏币（已扣点差）').textContent!.trim()).toBe('0.00000001');
  });

  it('成交结果 out：同样的两个字段**换位**（两条渲染路径都要过 money()）', () => {
    render({ direction: 'out', done: { exchange_id: 'E2', direction: 'out', ...RAW } });
    expect(cell('卖出游戏币').textContent!.trim()).toBe('0.00000001');
    expect(cell('到账平台币（已扣点差）').textContent!.trim()).toBe(
      '12,345,678,901,234,567,890.12',
    );
  });

  it('成交结果：点差费用过 money()，账户余额的负零按 0.00 显示', () => {
    render({ direction: 'in', done: { exchange_id: 'E1', direction: 'in', ...RAW } });
    expect(cell('点差费用').textContent!.trim()).toBe('628.50');
    expect(cell('账户余额').textContent!.trim()).toBe('0.00');
  });

  it('成交结果：三处金额的 title 是后端原始串，汇率不挂 title', () => {
    render({ direction: 'in', done: { exchange_id: 'E1', direction: 'in', ...RAW } });
    expect(cell('支付平台币').getAttribute('title')).toBe(RAW.platform_amount);
    expect(cell('到账游戏币（已扣点差）').getAttribute('title')).toBe(RAW.game_amount);
    expect(cell('点差费用').getAttribute('title')).toBe(RAW.spread_fee);
    // 成交汇率是**汇率**不是金额：正文原样、不挂 title（挂了就等于宣称它是金额）
    expect(cell('成交汇率').textContent!.trim()).toBe(RAW.rate);
    expect(cell('成交汇率').getAttribute('title')).toBeNull();
  });

  it('询价 in：折合游戏币与预计获得过 money()，汇率/点差百分比**保持原样**', () => {
    render({
      direction: 'in',
      quote: {
        platform_amount: RAW.platform_amount,
        rate: RAW.rate,
        spread_fee: RAW.spread_fee,
        spread_pct: '1.5',
        game_amount: '0.00000001',
        actual_game_amount: '12345678901234567890.12',
      },
    });
    expect(cell('折合游戏币（扣点差前）').textContent!.trim()).toBe('0.00000001');
    expect(cell('预计获得').textContent!.trim()).toBe('12,345,678,901,234,567,890.12 🪙');
    expect(cell('点差费用').textContent!.trim()).toBe('628.50');
    expect(cell('汇率').textContent!.trim()).toBe(`1 平台币 ≈ ${RAW.rate} 金币`);
    expect(cell('点差').textContent!.trim()).toBe('1.5%');
  });

  it('询价 out：换到平台币那一路，同样过 money() 且 title 为原始串', () => {
    render({
      direction: 'out',
      quote: {
        platform_amount: RAW.platform_amount,
        rate: RAW.rate,
        spread_fee: RAW.spread_fee,
        spread_pct: '1.5',
        platform_equivalent: '0.00000001',
        actual_platform_amount: '12345678901234567890.12',
      },
    });
    expect(cell('折合平台币（扣点差前）').textContent!.trim()).toBe('0.00000001');
    expect(cell('预计到账').textContent!.trim()).toBe('12,345,678,901,234,567,890.12 平台币');
    expect(cell('折合平台币（扣点差前）').getAttribute('title')).toBe('0.00000001');
    expect(cell('预计到账').getAttribute('title')).toBe('12345678901234567890.12');
    expect(cell('点差费用').getAttribute('title')).toBe(RAW.spread_fee);
    // 这一路没有 game_amount / actual_game_amount，渲染出来的行里也不该多出它们
    expect(fixture.nativeElement.textContent).not.toContain('折合游戏币');
  });
});
