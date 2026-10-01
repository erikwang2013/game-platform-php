/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { PaymentMethodInfo } from '../core/api.service';
import { DepositPage } from './deposit';

type Probe = { limit(m: PaymentMethodInfo): string };

/**
 * 支付方式限额提示（`limit`）的钉子。
 *
 * 钉它的理由：这行文案是**充值下单前用户唯一能看到的限额**，而它原先写的是
 * `Number(m.max_amount) > 0` —— 金额列过数值转型（本批明令禁止的写法），
 * 已改为字符串判据 `moneyIsZero`。判据一换就必须钉住两侧：写法的各种零（0/0.00/0.00000000）
 * 仍要显示「不限」，而 scale-8 的最小非零量**不许**被当成零（否则限额显示成「不限」，
 * 用户会以为没有上限）。
 *
 * 它渲染在 `<option>` 里（`{{ m.name }}（{{ limit(m) }}）`）—— `<option>` 只收文本节点，
 * 所以这一处**没有** title 悬停位，精度只能靠 money() 本身；这条也一并钉住，免得
 * 以后有人为了挂 title 往 option 里塞 span（浏览器会把标签当字面文本渲染出来）。
 */
describe('DepositPage 支付方式限额提示', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<DepositPage>;
  let probe: Probe;

  const m = (o: Partial<PaymentMethodInfo> = {}): PaymentMethodInfo => ({
    id: 'P1',
    name: 'Stripe',
    type: 'card',
    provider: 'stripe',
    min_amount: '10.0000',
    max_amount: '1000.0000',
    ...o,
  });

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(DepositPage);
    probe = fixture.componentInstance as unknown as Probe;
    // 构造函数里拉一次支付方式；本组用例只调 limit()，但请求不冲掉 verify() 会红
    http.expectOne('/api/v1/payment/methods').flush({ code: 0, message: 'ok', data: { list: [] } });
    fixture.detectChanges();
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('max 的各种零写法都显示「不限」（0/0.00/0.00000000，与 money() 同一套判据）', () => {
    for (const zero of ['0', '0.00', '0.00000000', '-0.00000000']) {
      expect(probe.limit(m({ max_amount: zero }))).toBe('10.00 ~ 不限');
    }
  });

  it('scale-8 最小非零量不是零：限额照实显示，不许写成「不限」', () => {
    expect(probe.limit(m({ min_amount: '0.00000001', max_amount: '0.00000002' }))).toBe(
      '0.00000001 ~ 0.00000002',
    );
  });

  it('min/max 都过 money()：千分位与大数一位不差', () => {
    expect(
      probe.limit(m({ min_amount: '1000', max_amount: '12345678901234567890.12' })),
    ).toBe('1,000.00 ~ 12,345,678,901,234,567,890.12');
  });
});
