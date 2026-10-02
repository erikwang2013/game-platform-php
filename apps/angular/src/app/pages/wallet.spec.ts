/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { t } from '../core/i18n/i18n';
import { WalletPage } from './wallet';

type Probe = {
  TX_LABEL: Record<string, string>;
  label(map: Record<string, string>, key: string): string;
  pick(t: 'tx' | 'dep' | 'wd' | 'ex'): void;
  ex: { items(): Array<{ id: string; direction: string }> };
  exLabel(d: string): string;
  exInflow(d: string): boolean;
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
    expect(t(probe().TX_LABEL['exchange_in']!)).toBe('兑换转入');
    expect(t(probe().TX_LABEL['exchange_out']!)).toBe('兑换转出');
    expect(t(probe().TX_LABEL['referral_bonus']!)).toBe('邀请奖励');
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

  it('表里每条都能翻出中文 —— 键写错会原样吐键名（含 ASCII，故这条咬得住）', () => {
    for (const [type, key] of Object.entries(probe().TX_LABEL)) {
      // 比原来「表里的值不许是英文」**更强**：原来只管这张表本身，
      // 管不到「这个键在不在译文表里」；现在断言整条链（表 → 词条键 → 译文）。
      // 键拼错时 t() 原样吐键名 ⇒ 命中 ASCII ⇒ 红。
      const text = t(key);
      expect(text, `${type} → ${key}`).not.toMatch(/[A-Za-z]/);
      expect(text.length, `${type} → ${key}`).toBeGreaterThan(0);
    }
  });

  /**
   * 第四个页签（兑换记录）。此前 `/exchange/records` 在本树**零消费方** ⇒
   * 买入/卖出成交后无处可查（流水页只有 exchange_in/out 两条平台币账）。
   */
  it('兑换页签读 /api/v1/exchange/records，行落进 ex 列表', () => {
    probe().pick('ex');
    const req = http.expectOne((r) => r.url === '/api/v1/exchange/records');
    expect(req.request.method).toBe('GET');
    expect(req.request.params.get('page')).toBe('1');
    req.flush({
      code: 0,
      message: 'ok',
      data: {
        items: [
          {
            id: 'X1',
            game_id: 'G1',
            currency_id: 'C1',
            direction: 'in',
            platform_amount: '10.00000000',
            game_amount: '100.00000000',
            rate: '10.00000000',
            spread_fee: '0.10000000',
            created_at: '2026-10-01 12:00:00',
          },
        ],
        page: 1,
        last_page: 1,
        total: 1,
      },
    });
    expect(probe().ex.items().length).toBe(1);
    expect(probe().ex.items()[0]!.id).toBe('X1');
  });

  /**
   * 兑换行的口径：`platform_amount` 是**无符号** decimal，且含义随 direction 换位
   * （in=买币支出、out=卖币到账净额）⇒ 收支方向只能看 direction，看金额正负必然判错。
   */
  it('兑换行：in 是支出、out 是到账，文案与 apps/react 同口径', () => {
    expect(probe().exLabel('in')).toBe('买入游戏币');
    expect(probe().exLabel('out')).toBe('卖出游戏币');
    expect(probe().exInflow('in')).toBe(false); // 买币：平台币减少
    expect(probe().exInflow('out')).toBe(true); // 卖币：平台币到账
  });
});
