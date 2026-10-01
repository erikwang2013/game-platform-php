/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { WalletPage } from './wallet';

type Probe = {
  TX_LABEL: Record<string, string>;
  label(map: Record<string, string>, key: string): string;
};

/**
 * 交易类型标签表（TX_LABEL）的钉子。
 *
 * 钉它的理由：钱包流水是**唯一一处会把后端原始 type 直接摆到用户眼前**的列表
 * （`transactions` 不按 scope 过滤，游戏币那本账的 game_spend / game_earn 也混在里面），
 * 而这张表此前**仓内零覆盖** —— 观察者只有 /tmp 里那支手工真机脚本，那支脚本不进 CI，
 * 哪天功能撤了它也不会提醒谁（本轮已经有一次：/referral 撤下后脚本里 4 条期望静默过期）。
 *
 * 特意**不渲染整页**（那要造一堆服务桩，钉子会退化成测桩）：只钉表本身 + 回落函数。
 * 渲染那一层由真机脚本管，两层分工见交付说明。
 */
describe('WalletPage 流水类型标签', () => {
  let http: HttpTestingController;
  let page: WalletPage;

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
    page = TestBed.runInInjectionContext(() => new WalletPage());
    // 构造函数里余额 + 首个页签各发一发，先冲掉，后续用例各管各的
    http
      .expectOne('/api/v1/wallet/info')
      .flush({ code: 0, message: 'ok', data: { balance: '100.00', frozen_balance: '0.00', total_earned: '5.00', total_spent: '1.00' } });
    http
      .expectOne((r) => r.url === '/api/v1/wallet/transactions')
      .flush({ code: 0, message: 'ok', data: { items: [], page: 1, last_page: 1, total: 0 } });
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('有写入侧的三条已落中文（兑换两条 + ReferralController:261 的邀请奖励）', () => {
    expect(probe().TX_LABEL['exchange_in']).toBe('兑换转入');
    expect(probe().TX_LABEL['exchange_out']).toBe('兑换转出');
    expect(probe().TX_LABEL['referral_bonus']).toBe('邀请奖励');
  });

  it('六个无写入侧的死键不许回来（加回来 = 又一批只有噪音没有功能的条目）', () => {
    const t = probe().TX_LABEL;
    for (const dead of ['bet', 'win', 'transfer_in', 'transfer_out', 'commission', 'adjust']) {
      expect(t[dead], dead).toBeUndefined();
    }
  });

  it('未列出的 type 回落成原键（宁可露原键，也不编一个不存在的类型名）', () => {
    expect(probe().label(probe().TX_LABEL, 'mystery_type')).toBe('mystery_type');
  });

  it('表里每条都真的是中文 —— 没有哪条偷懒把英文键抄成值', () => {
    for (const [key, text] of Object.entries(probe().TX_LABEL)) {
      expect(text, key).not.toMatch(/[A-Za-z]/);
      expect(text.length, key).toBeGreaterThan(0);
    }
  });
});
