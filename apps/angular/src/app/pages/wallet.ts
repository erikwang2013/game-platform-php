/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { Observable } from 'rxjs';
import {
  Api,
  ApiError,
  DepositOrder,
  Paged,
  Transaction,
  WalletInfo,
  WithdrawOrder,
  dt,
  money,
} from '../core/api.service';

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

const TX_LABEL: Record<string, string> = {
  deposit: '充值',
  withdraw: '提现',
  bet: '投注',
  win: '派彩',
  refund: '退款',
  transfer_in: '转入',
  transfer_out: '转出',
  commission: '佣金',
  adjust: '调账',
};

const DEP_LABEL: Record<string, string> = {
  pending: '待支付',
  paid: '已支付',
  confirmed: '已到账',
  success: '已完成',
  cancelled: '已取消',
  expired: '已过期',
  failed: '失败',
};

const WD_LABEL: Record<string, string> = {
  pending: '待审核',
  reviewing: '审核中',
  approved: '已通过',
  paid: '已打款',
  rejected: '已驳回',
  cancelled: '已取消',
  failed: '失败',
};

const BAD = ['cancelled', 'rejected', 'failed', 'expired'];

@Component({
  selector: 'app-wallet',
  imports: [NgTemplateOutlet],
  template: `
    <div class="card bal">
      <div class="main">
        <span class="label">可用余额</span>
        <strong class="big mono">{{ info() ? money(info()!.balance) : '—' }}</strong>
        @if (info(); as w) {
          <div class="wrap sub">
            <span class="chip">冻结 {{ money(w.frozen_balance) }}</span>
            <span class="chip">累计收入 {{ money(w.total_earned) }}</span>
            <span class="chip">累计支出 {{ money(w.total_spent) }}</span>
          </div>
        }
      </div>
      @if (balanceError()) {
        <div class="alert">{{ balanceError() }}</div>
      }
    </div>

    <div class="chips tabs">
      <button type="button" class="chip" [class.on]="tab() === 'tx'" (click)="pick('tx')">
        交易流水
      </button>
      <button type="button" class="chip" [class.on]="tab() === 'dep'" (click)="pick('dep')">
        充值订单
      </button>
      <button type="button" class="chip" [class.on]="tab() === 'wd'" (click)="pick('wd')">
        提现订单
      </button>
    </div>

    @if (tab() === 'tx') {
      <ng-container *ngTemplateOutlet="list; context: { $implicit: tx, kind: 'tx' }"></ng-container>
    } @else if (tab() === 'dep') {
      <ng-container
        *ngTemplateOutlet="list; context: { $implicit: dep, kind: 'dep' }"
      ></ng-container>
    } @else {
      <ng-container *ngTemplateOutlet="list; context: { $implicit: wd, kind: 'wd' }"></ng-container>
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
            <strong>加载失败</strong>
            <span>{{ p.error() }}</span>
            <button class="btn" type="button" (click)="p.reload()">重试</button>
          </div>
        } @else if (!p.items().length) {
          <div class="state">
            <strong>暂无记录</strong>
            <span>{{ emptyHint(kind) }}</span>
          </div>
        } @else {
          <div class="rows">
            @for (r of p.items(); track r.id) {
              <div class="row">
                @if (kind === 'tx') {
                  <div class="grow">
                    <div class="t">{{ label(TX_LABEL, r.type) }}</div>
                    <div class="s">{{ dt(r.created_at) }} · 余额 {{ money(r.balance_after) }}</div>
                    @if (r.remark) {
                      <div class="s">{{ r.remark }}</div>
                    }
                  </div>
                  <span
                    class="amount"
                    [class.in]="sign(r.amount) > 0"
                    [class.out]="sign(r.amount) < 0"
                  >
                    {{ sign(r.amount) > 0 ? '+' : '' }}{{ money(r.amount) }}
                  </span>
                } @else if (kind === 'dep') {
                  <div class="grow">
                    <div class="t">{{ r.order_no }}</div>
                    <div class="s">
                      {{ dt(r.created_at) }} · {{ r.currency }} {{ money(r.amount) }} → 平台
                      {{ money(r.platform_amount) }}
                    </div>
                  </div>
                  <span class="badge {{ tone(r.status) }}">{{ label(DEP_LABEL, r.status) }}</span>
                } @else {
                  <div class="grow">
                    <div class="t">{{ r.order_no }}</div>
                    <div class="s">
                      {{ dt(r.created_at) }} · {{ r.method || '—' }} · 平台
                      {{ money(r.platform_amount) }}
                    </div>
                    @if (r.review_note) {
                      <div class="s">{{ r.review_note }}</div>
                    }
                  </div>
                  <span class="badge {{ tone(r.status) }}">{{ label(WD_LABEL, r.status) }}</span>
                }
              </div>
            }
          </div>
          @if (p.hasMore()) {
            <div class="more">
              <button class="btn" type="button" [disabled]="p.more()" (click)="p.load()">
                {{ p.more() ? '加载中…' : '加载更多' }}
              </button>
            </div>
          }
        }
      </div>
    </ng-template>
  `,
  styles: [
    `
      .bal {
        padding: 22px 24px;
        background:
          radial-gradient(120% 170% at 0% 0%, rgba(124, 58, 237, 0.24), transparent 60%),
          var(--panel);
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      .bal .big {
        font-size: 32px;
        letter-spacing: -0.02em;
        margin-top: 6px;
      }
      .sub {
        margin-top: 10px;
      }
      .sub .chip {
        font-variant-numeric: tabular-nums;
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
        .bal .big {
          font-size: 38px;
        }
      }
    `,
  ],
})
export class WalletPage {
  private readonly api = inject(Api);

  protected readonly info = signal<WalletInfo | null>(null);
  protected readonly balanceError = signal('');
  protected readonly tab = signal<'tx' | 'dep' | 'wd'>('tx');

  protected readonly TX_LABEL = TX_LABEL;
  protected readonly DEP_LABEL = DEP_LABEL;
  protected readonly WD_LABEL = WD_LABEL;
  protected readonly money = money;
  protected readonly dt = dt;

  protected readonly tx = new Pager<Transaction>((p) => this.api.walletTransactions(p, 20));
  protected readonly dep = new Pager<DepositOrder>((p) => this.api.depositOrders(p, 20));
  protected readonly wd = new Pager<WithdrawOrder>((p) => this.api.withdrawOrders(p, 20));

  private readonly loaded = new Set<string>();

  constructor() {
    this.api.walletInfo().subscribe({
      next: (w) => this.info.set(w),
      error: (e: ApiError) => this.balanceError.set(e.message),
    });
    this.pick('tx');
  }

  /** 首次进入某标签才发请求，避免一次打 4 个接口 */
  protected pick(t: 'tx' | 'dep' | 'wd'): void {
    this.tab.set(t);
    if (this.loaded.has(t)) return;
    this.loaded.add(t);
    if (t === 'tx') this.tx.load();
    else if (t === 'dep') this.dep.load();
    else this.wd.load();
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

  protected emptyHint(kind: string): string {
    return kind === 'tx' ? '还没有资金变动' : '还没有相关订单';
  }
}
