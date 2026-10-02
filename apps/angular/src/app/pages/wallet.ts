/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import {
  Api,
  ApiError,
  DepositOrder,
  ExchangeRecordRow,
  Paged,
  Transaction,
  WalletInfo,
  WithdrawOrder,
  dt,
  money,
  moneyRaw,
} from '../core/api.service';
import { T, t } from '../core/i18n/i18n';

/** 分页列表：三个列表共用同一套 加载/错误/空/更多 逻辑 */
class Pager<T> {
  readonly items = signal<T[]>([]);
  readonly busy = signal(true);
  readonly more = signal(false);
  readonly error = signal('');
  readonly hasMore = signal(false);
  private page = 0;

  constructor(private readonly src: (page: number) => Observable<Paged<T>>) {}

  load(): void {
    const first = this.page === 0;
    (first ? this.busy : this.more).set(true);
    this.error.set('');
    this.src(this.page + 1).subscribe({
      next: (r) => {
        this.page = r.page || this.page + 1;
        this.items.set(first ? r.items : [...this.items(), ...r.items]);
        this.hasMore.set(this.page < (r.last_page || this.page));
        this.busy.set(false);
        this.more.set(false);
      },
      error: (e: ApiError) => {
        this.error.set(e.message);
        this.busy.set(false);
        this.more.set(false);
      },
    });
  }

  reload(): void {
    this.page = 0;
    this.load();
  }
}

/**
 * 交易类型 → 中文。**只列有写入侧的键**（逐键核过，写入点见下），未列出的键走 label() 原样透出
 * （宁可露出原键，也不要编一个不存在的类型名）。
 *
 * transactions 接口**不按 scope 过滤**，所以游戏币那本账的 game_spend / game_earn 也会出现在
 * 这个列表里。除迁移外，全仓只有 WalletService::record() → Transaction::create(:323) 一个口子
 * 能建流水行（service / admin 两棵的 WalletService 目前逐字节相同）。写入点：
 *   deposit          PaymentController.php:158
 *   withdraw         WithdrawController.php:232
 *   refund           **admin 树** app/admin/v1/WithdrawReviewTrait.php:186 / :284（提现驳回退回）
 *   exchange_out     ExchangeController.php:231（买币扣平台币）· exchange_in :260（卖币到账）
 *   game_spend       SelfProvider.php:54 · game_earn SelfProvider.php:166
 *   activity_reward  ActivityService.php:334 / :345
 *   referral_bonus   ReferralController.php:261
 *   lock / unlock    **admin 树** app/admin/v1/controller/RiskUserController.php:139 / :235（风控冻结/解冻）
 *   reconcile        install/migrations/2026_09_28_wallet_freeze_ledger.sql:112 / :142（仅回填迁移写）
 *
 * ⚠ 三个踩过的坑，别再踩：
 *  1. refund / lock / unlock 的写入侧在 **admin 树**（提现审核、风控冻结）—— 只 grep `service/`
 *     会把它们误判成死键。核键集时两棵树都要扫。
 *  2. `bet` / `win` 不是流水类型，是 `game_play_record.action` / `.result`
 *     （GamePlayRecorder.php:33、AntiCheatService.php:297），跟这张表是两张表，别抄进来。
 *  3. `transfer_in` / `transfer_out` / `commission` / `adjust` 全仓零写入（连常量都没有），已删。
 *     它们是从别处照抄进来的死键：用户永远看不到，只会在下次复核时把人带去查一轮。
 *
 * 键集与 apps/react 的 lib/labels.ts 对齐（两张表 12 键逐键相同）。
 *
 * ⚠ **表里存的是词条键，不是中文**：模板里过 `| t` 渲染（`label(MAP, x) | t`）。
 * 想改中文措辞就去改 `core/i18n/dict/wallet.ts`，**别在这里写回中文** ——
 * 表里直接写死中文的话，13 种语言下这一列永远不变，而界面上看不出是"没抽"。
 */
const TX_LABEL: Record<string, string> = {
  deposit: 'wallet.deposit',
  withdraw: 'wallet.withdraw',
  refund: 'tx.refund',
  exchange_in: 'tx.exchange_in',
  exchange_out: 'tx.exchange_out',
  game_spend: 'tx.game_spend',
  game_earn: 'tx.game_earn',
  activity_reward: 'tx.activity_reward',
  referral_bonus: 'tx.referral_bonus',
  lock: 'common.frozen',
  unlock: 'common.unfrozen',
  reconcile: 'tx.reconcile',
};

const DEP_LABEL: Record<string, string> = {
  pending: 'status.dep.pending',
  paid: 'status.dep.paid',
  confirmed: 'status.dep.confirmed',
  success: 'common.completed',
  cancelled: 'common.cancelled',
  expired: 'status.dep.expired',
  failed: 'common.failed',
};

const WD_LABEL: Record<string, string> = {
  pending: 'status.wd.pending',
  reviewing: 'status.wd.reviewing',
  approved: 'status.wd.approved',
  paid: 'status.wd.paid',
  rejected: 'status.wd.rejected',
  cancelled: 'common.cancelled',
  failed: 'common.failed',
};

const BAD = ['cancelled', 'rejected', 'failed', 'expired'];

@Component({
  selector: 'app-wallet',
  imports: [NgTemplateOutlet, RouterLink, T],
  template: `
    <div class="card bal">
      <div class="main">
        <span class="label">{{ 'wallet.available' | t }}</span>
        <strong class="big mono" [title]="moneyRaw(info()?.balance)">{{
          info() ? money(info()!.balance) : '—'
        }}</strong>
        @if (info(); as w) {
          <div class="wrap sub">
            <span class="chip" [title]="moneyRaw(w.frozen_balance)"
              >{{ 'common.frozen' | t }} {{ money(w.frozen_balance) }}</span
            >
            <span class="chip" [title]="moneyRaw(w.total_earned)"
              >{{ 'wallet.total_earned' | t }} {{ money(w.total_earned) }}</span
            >
            <span class="chip" [title]="moneyRaw(w.total_spent)"
              >{{ 'wallet.total_spent' | t }} {{ money(w.total_spent) }}</span
            >
          </div>
        }
      </div>
      @if (balanceError()) {
        <div class="alert">{{ balanceError() }}</div>
      }
    </div>

    <div class="acts">
      <a class="btn primary" routerLink="/wallet/deposit">{{ 'wallet.deposit' | t }}</a>
      <a class="btn" routerLink="/wallet/withdraw">{{ 'wallet.withdraw' | t }}</a>
      <a class="btn" routerLink="/wallet/exchange">{{ 'wallet.exchange' | t }}</a>
      <!-- 游戏流水是另一本账（游戏币），不在下面三个页签里 -->
      <a class="btn ghost" routerLink="/wallet/records">{{ 'wallet.records' | t }}</a>
    </div>

    <div class="chips tabs">
      <button type="button" class="chip" [class.on]="tab() === 'tx'" (click)="pick('tx')">
        {{ 'wallet.tab_tx' | t }}
      </button>
      <button type="button" class="chip" [class.on]="tab() === 'dep'" (click)="pick('dep')">
        {{ 'wallet.tab_dep' | t }}
      </button>
      <button type="button" class="chip" [class.on]="tab() === 'wd'" (click)="pick('wd')">
        {{ 'wallet.tab_wd' | t }}
      </button>
      <!-- 兑换记录：买入/卖出是平台币与游戏币之间的换手，两个方向都在这张表里
           （游戏币那一侧的**每次变动**在 /wallet/records，与本页签口径不同） -->
      <button type="button" class="chip" [class.on]="tab() === 'ex'" (click)="pick('ex')">
        {{ 'wallet.tab_ex' | t }}
      </button>
    </div>

    @if (tab() === 'tx') {
      <ng-container *ngTemplateOutlet="list; context: { $implicit: tx, kind: 'tx' }"></ng-container>
    } @else if (tab() === 'dep') {
      <ng-container
        *ngTemplateOutlet="list; context: { $implicit: dep, kind: 'dep' }"
      ></ng-container>
    } @else if (tab() === 'wd') {
      <ng-container *ngTemplateOutlet="list; context: { $implicit: wd, kind: 'wd' }"></ng-container>
    } @else {
      <ng-container *ngTemplateOutlet="list; context: { $implicit: ex, kind: 'ex' }"></ng-container>
    }

    <ng-template #list let-p let-kind="kind">
      <div class="card">
        @if (p.busy()) {
          <div class="rows">
            @for (i of [1, 2, 3, 4]; track i) {
              <div class="row"><div class="skeleton sk-row"></div></div>
            }
          </div>
        } @else if (p.error()) {
          <div class="state">
            <strong>{{ 'common.load_failed' | t }}</strong>
            <span>{{ p.error() }}</span>
            <button class="btn" type="button" (click)="p.reload()">{{ 'common.retry' | t }}</button>
          </div>
        } @else if (!p.items().length) {
          <div class="state">
            <!-- 吉祥物小骰（Dicey）：相对 public/，由 <base href> 解析到子路径 -->
            <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
            <strong>{{ 'common.no_records' | t }}</strong>
            <span>{{ emptyHint(kind) }}</span>
          </div>
        } @else {
          <div class="rows">
            @for (r of p.items(); track r.id) {
              <div class="row">
                @if (kind === 'tx') {
                  <div class="grow">
                    <div class="t">{{ label(TX_LABEL, r.type) | t }}</div>
                    <div class="s">
                      {{ dt(r.created_at) }} · {{ 'common.balance' | t }}
                      <span [title]="moneyRaw(r.balance_after)">{{ money(r.balance_after) }}</span>
                    </div>
                    @if (r.remark) {
                      <div class="s">{{ r.remark }}</div>
                    }
                  </div>
                  <span
                    class="amount"
                    [class.in]="sign(r.amount) > 0"
                    [class.out]="sign(r.amount) < 0"
                    [title]="moneyRaw(r.amount)"
                  >
                    {{ sign(r.amount) > 0 ? '+' : '' }}{{ money(r.amount) }}
                  </span>
                } @else if (kind === 'dep') {
                  <div class="grow">
                    <div class="t">{{ r.order_no }}</div>
                    <div class="s">
                      {{ dt(r.created_at) }} · {{ r.currency }}
                      <span [title]="moneyRaw(r.amount)">{{ money(r.amount) }}</span> → {{ 'common.platform' | t }}
                      <span [title]="moneyRaw(r.platform_amount)">{{
                        money(r.platform_amount)
                      }}</span>
                    </div>
                  </div>
                  <span class="badge {{ tone(r.status) }}">{{ label(DEP_LABEL, r.status) | t }}</span>
                } @else if (kind === 'wd') {
                  <div class="grow">
                    <div class="t">{{ r.order_no }}</div>
                    <div class="s">
                      {{ dt(r.created_at) }} · {{ r.method || '—' }} · {{ 'common.platform' | t }}
                      <span [title]="moneyRaw(r.platform_amount)">{{
                        money(r.platform_amount)
                      }}</span>
                    </div>
                    @if (r.review_note) {
                      <div class="s">{{ r.review_note }}</div>
                    }
                  </div>
                  <span class="badge {{ tone(r.status) }}">{{ label(WD_LABEL, r.status) | t }}</span>
                } @else {
                  <!-- 收支一律记**平台币那一侧**，符号由 direction 决定（platform_amount 本身
                       是无符号 decimal，正负不在串里）：in=买币支出、out=卖币到账净额 -->
                  <div class="grow">
                    <div class="t">{{ exLabel(r.direction) }}</div>
                    <div class="s">
                      {{ 'common.game_coin' | t }}
                      <span [title]="moneyRaw(r.game_amount)">{{ money(r.game_amount) }}</span>
                      · {{ dt(r.created_at) }}
                    </div>
                    <div class="s">{{ 'common.rate' | t }} {{ r.rate }} · {{ 'common.spread' | t }} {{ money(r.spread_fee) }}</div>
                  </div>
                  <span
                    class="amount"
                    [class.in]="exInflow(r.direction)"
                    [class.out]="!exInflow(r.direction)"
                    [title]="moneyRaw(r.platform_amount)"
                  >
                    {{ exInflow(r.direction) ? '+' : '-' }}{{ money(r.platform_amount) }}
                  </span>
                }
              </div>
            }
          </div>
          @if (p.hasMore()) {
            <div class="more">
              <button class="btn" type="button" [disabled]="p.more()" (click)="p.load()">
                {{ (p.more() ? 'common.loading' : 'wallet.load_more') | t }}
              </button>
            </div>
          }
        }
      </div>
    </ng-template>
  `,
  styles: [
    `
      /* 余额卡是全站头号数字：钱只在这里被放大一次。品牌紫从左侧漫开，
         顶沿压一条代币金 —— 金色在这棵树里只代表"战利品/钱"。 */
      .bal {
        padding: 24px 24px 22px;
        background:
          radial-gradient(
            120% 180% at 0% 0%,
            color-mix(in srgb, var(--primary) 24%, transparent),
            transparent 62%
          ),
          var(--surface);
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      /* 顶沿的机台灯条：金渐到紫、右端收干净。用 ::after 贴顶画而不是
         inset 阴影 —— 阴影会跟着圆角包下来，看起来像整圈金边。 */
      .bal::after {
        content: '';
        position: absolute;
        inset: 0 0 auto;
        height: 3px;
        background: linear-gradient(90deg, var(--gold), var(--primary) 62%, transparent);
        pointer-events: none;
      }
      .bal .big {
        /* <strong> 默认是 inline：不改成 block 的话「可用余额」会和金额挤在同一行，
           下面的 margin-top 也完全不生效（inline 不吃纵向 margin）。 */
        display: block;
        font-size: 40px;
        font-weight: 800;
        letter-spacing: -0.035em;
        line-height: 1.05;
        margin-top: 6px;
        overflow-wrap: anywhere;
      }
      .sub {
        margin-top: 10px;
      }
      .sub .chip {
        font-variant-numeric: tabular-nums;
      }
      /* 四个动作按 2×2 排。原来是 flex-wrap，360 宽下第四个（游戏流水）
         被挤到单独一行、前三个还大小不一 —— 花钱的入口不该排成这样。 */
      .acts {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
      }
      .tabs {
        margin: 18px 0 14px;
      }
      .sk-row {
        height: 16px;
        width: 70%;
      }
      .more {
        display: flex;
        justify-content: center;
        padding-top: 14px;
      }
      @media (min-width: 768px) {
        .bal {
          padding: 28px 30px 26px;
        }
        .bal .big {
          font-size: 52px;
        }
        .acts {
          grid-template-columns: repeat(4, minmax(0, 1fr));
        }
      }
    `,
  ],
})
export class WalletPage {
  private readonly api = inject(Api);

  protected readonly info = signal<WalletInfo | null>(null);
  protected readonly balanceError = signal('');
  protected readonly tab = signal<'tx' | 'dep' | 'wd' | 'ex'>('tx');

  protected readonly TX_LABEL = TX_LABEL;
  protected readonly DEP_LABEL = DEP_LABEL;
  protected readonly WD_LABEL = WD_LABEL;
  protected readonly money = money;
  protected readonly moneyRaw = moneyRaw;
  protected readonly dt = dt;

  protected readonly tx = new Pager<Transaction>((p) => this.api.walletTransactions(p, 20));
  protected readonly dep = new Pager<DepositOrder>((p) => this.api.depositOrders(p, 20));
  protected readonly wd = new Pager<WithdrawOrder>((p) => this.api.withdrawOrders(p, 20));
  protected readonly ex = new Pager<ExchangeRecordRow>((p) => this.api.exchangeRecords(p, 20));

  private readonly loaded = new Set<string>();

  constructor() {
    this.api.walletInfo().subscribe({
      next: (w) => this.info.set(w),
      error: (e: ApiError) => this.balanceError.set(e.message),
    });
    this.pick('tx');
  }

  /** 首次进入某标签才发请求，避免一次打 4 个接口 */
  protected pick(t: 'tx' | 'dep' | 'wd' | 'ex'): void {
    this.tab.set(t);
    if (this.loaded.has(t)) return;
    this.loaded.add(t);
    if (t === 'tx') this.tx.load();
    else if (t === 'dep') this.dep.load();
    else if (t === 'wd') this.wd.load();
    else this.ex.load();
  }

  protected label(map: Record<string, string>, key: string): string {
    return map[key] ?? key ?? '—';
  }

  protected tone(s: string): string {
    if (BAD.includes(s)) return 'bad';
    return s === 'pending' || s === 'reviewing' ? 'warn' : 'on';
  }

  /** 交易金额正负：后端 decimal 可能是字符串，仅用于展示方向 */
  protected sign(v: number | string): number {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  }

  /**
   * 兑换行的文案与收支方向。**只看 direction，不看金额正负**：
   * `platform_amount` 是无符号 decimal，且它的口径随方向换位（in=买币支出、out=卖币到账净额）
   * —— 本仓在别的域踩过这个字段复用（见 `exchange-direction-semantics`）。
   * 口径与 apps/react 的 Wallet.tsx 第 4 个页签一致（那边是 `inflow = direction === 'out'`）。
   */
  protected exLabel(d: string): string {
    return t(d === 'in' ? 'wallet.ex_buy' : 'wallet.ex_sell');
  }

  protected exInflow(d: string): boolean {
    return d === 'out';
  }

  protected emptyHint(kind: string): string {
    if (kind === 'tx') return t('wallet.empty_tx');
    if (kind === 'ex') return t('wallet.empty_ex');
    return t('wallet.empty_orders');
  }
}
