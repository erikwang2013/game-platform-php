/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { FALLBACK, normalize } from '../core/i18n/langs';
import { t, use } from '../core/i18n/i18n';
import { WalletPage } from './wallet';

/**
 * 资金域（B1）界面多语言的钉子。
 *
 * 钉它的理由：**「某个键写错」和「某个字没抽」在界面上都长得像正常的** ——
 * 前者 `t()` 原样吐出键名（`wallet.available` 直接印在页面上），后者的字根本不变。
 * 两者都只在「切到另一种语言看一眼」时才暴露，而不会有人天天切语言去发现。
 * 仓内没有第二种观察者：`ng build` 不看文案，真机脚本不进 CI。
 *
 * ⚠ **本条只钉渲染层**。「四个页面文件里还有没有中文字面量」是**源码层**判据，
 * 本树跑不了：`tsconfig.spec.json` 的 `types` 只有 `vitest/globals`，没有 `@types/node`
 * ⇒ `import { readFileSync } from 'node:fs'` 报 TS2307。要落树级源码钉，
 * 得先给它加 `types`（`vite/client` 走 `import.meta.glob(..., { query: '?raw' })` 最省）
 * —— 那是**一次覆盖全树**的改动，等抽取铺满全树（B4）时一并做，不在 B1 单开。
 * 当前源码层的读数由 `/tmp/b1_inv.mjs` 逐行扫描提供（B1 报的是 0 残留），不进 CI。
 */
describe('B1 资金域：切语言后真的变', () => {
  beforeEach(() => {
    localStorage.clear();
    void use(FALLBACK);
  });

  afterEach(() => TestBed.resetTestingModule());

  /** 钱包页四个动作按钮的可见文字 */
  const acts = (el: HTMLElement): string =>
    [...el.querySelectorAll('.acts a')].map((a) => a.textContent?.trim()).join('|');

  /** 起一次真钱包页，冲掉构造函数那两发请求 */
  const renderWallet = (): { fx: ReturnType<typeof TestBed.createComponent<WalletPage>>; http: HttpTestingController } => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    const http = TestBed.inject(HttpTestingController);
    const fx = TestBed.createComponent(WalletPage);
    fx.detectChanges();
    http
      .expectOne((r) => r.url === '/api/v1/wallet/info')
      .flush({ code: 0, message: 'ok', data: { balance: '1.00', frozen_balance: '0.00', total_earned: '0.00', total_spent: '0.00' } });
    http
      .expectOne((r) => r.url === '/api/v1/wallet/transactions')
      .flush({ code: 0, message: 'ok', data: { items: [], page: 1, last_page: 1, total: 0 } });
    fx.detectChanges();
    return { fx, http };
  };

  it('钱包页：默认中文，切到 en / ja 后按钮与余额卡标题都跟着变', async () => {
    const { fx, http } = renderWallet();

    expect(acts(fx.nativeElement as HTMLElement)).toBe('充值|提现|兑换|游戏流水');
    expect(fx.nativeElement.querySelector('.bal .label')?.textContent).toContain('可用余额');

    await use('en');
    fx.detectChanges();
    expect(acts(fx.nativeElement as HTMLElement)).toBe('Deposit|Withdraw|Exchange|Game history');
    expect(fx.nativeElement.querySelector('.bal .label')?.textContent).toContain('Available balance');

    await use('ja');
    fx.detectChanges();
    expect(acts(fx.nativeElement as HTMLElement)).toBe('入金|出金|両替|ゲーム履歴');

    http.verify();
  });

  it('组件方法里的文案也过 t()（不是只有模板那一层）', async () => {
    // emptyHint / exLabel 是**方法返回的字符串**，不经模板管道 —— 它们留在中文最容易漏
    expect(t('wallet.empty_tx')).toBe('还没有资金变动');
    expect(t('wallet.ex_buy')).toBe('买入游戏币');

    await use('en');
    expect(t('wallet.empty_tx')).toBe('No wallet activity yet');
    expect(t('wallet.ex_buy')).toBe('Buy game coins');
  });

  it('B1 新增的键都真的在表里（键写错时 t() 会原样吐键名）', async () => {
    await use('en');
    for (const k of ['deposit.title', 'withdraw.submit', 'exchange.quote', 'common.order_no']) {
      // 吐出来等于键名本身就是「键拼错了」的指纹；空串是「译成了空」
      expect(t(k), k).not.toBe(k);
      expect(t(k).length, k).toBeGreaterThan(0);
    }
    // 带占位符的几条：漏传参数会原样留着 `{msg}`，这条钉住「参数名对得上」
    expect(t('deposit.err_gateway', { msg: 'X' })).toContain('X');
    expect(t('withdraw.err_blocked', { msg: 'Y' })).toContain('Y');
  });
});

describe('B1 与 B0 的兜底口径一致', () => {
  it('认不出的码仍然落 zh（B1 没有改动这条）', () => {
    expect(normalize('xx')).toBe(FALLBACK);
  });
});
