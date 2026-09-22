/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  Api,
  ApiError,
  DepositCreated,
  PaymentMethodInfo,
  depositAmountOk,
  dt,
  money,
} from '../core/api.service';

/** 后端支持的 8 种法币（DepositController 的 in: 白名单） */
const CURRENCIES = ['USD', 'CNY', 'EUR', 'JPY', 'KRW', 'GBP', 'BRL', 'INR'];

/** 充值：选支付方式 + 币种 + 金额 → 创建订单 → 打开收银台 */
@Component({
  selector: 'app-deposit',
  imports: [RouterLink],
  template: `
    <a class="btn ghost back" routerLink="/wallet">← 返回钱包</a>

    @if (done(); as d) {
      <div class="card stack">
        <div class="between">
          <span class="label">订单已创建</span>
          <span class="badge warn">待支付</span>
        </div>
        <div class="kv">
          <span class="muted">订单号</span><span class="mono">{{ d.order_no }}</span>
        </div>
        <div class="kv">
          <span class="muted">充值金额</span
          ><span class="mono">{{ money(d.amount) }} {{ currency() }}</span>
        </div>
        <div class="kv">
          <span class="muted">到账平台币</span
          ><span class="mono">{{ money(d.platform_amount) }}</span>
        </div>
        @if (d.expires_at) {
          <div class="kv">
            <span class="muted">支付截止</span><span class="mono">{{ dt(d.expires_at) }}</span>
          </div>
        }

        @if (safeUrl(); as u) {
          <a class="btn primary wide" [href]="u" target="_blank" rel="noopener noreferrer"
            >前往支付</a
          >
          <p class="muted hint">付款需在收银台完成；若未自动打开，请点上方按钮。</p>
        } @else if (unsafeUrl()) {
          <div class="alert">
            支付链接协议异常，未自动跳转。请复制下方链接、核对无误后自行打开。
          </div>
          <label class="field">
            <span>支付链接</span>
            <input class="input mono" readonly [value]="unsafeUrl()" (focus)="select($event)" />
          </label>
        }

        <div class="wrap">
          <a class="btn" routerLink="/wallet">查看充值订单</a>
          <button class="btn ghost" type="button" (click)="again()">再充一笔</button>
        </div>
      </div>
    } @else {
      <div class="card form">
        <div>
          <span class="label">充值</span>
          <h1>购买平台币</h1>
        </div>

        <label class="field">
          <span>支付方式</span>
          <select
            class="input"
            [disabled]="methodsBusy() || !methods().length"
            (change)="methodId.set($any($event.target).value)"
          >
            @if (!methods().length) {
              <option value="">{{ methodsBusy() ? '加载中…' : '暂无可用支付方式' }}</option>
            }
            @for (m of methods(); track m.id) {
              <option [value]="m.id" [selected]="m.id === methodId()">
                {{ m.name }}（{{ limit(m) }}）
              </option>
            }
          </select>
        </label>

        <label class="field">
          <span>币种</span>
          <select class="input" (change)="pickCurrency($any($event.target).value)">
            @for (c of currencies; track c) {
              <option [value]="c" [selected]="c === currency()">{{ c }}</option>
            }
          </select>
        </label>

        <label class="field">
          <span>金额（{{ currency() }}）</span>
          <input
            class="input mono"
            inputmode="decimal"
            autocomplete="off"
            placeholder="0.00"
            [value]="amount()"
            (input)="onAmount($event)"
          />
          @if (amountError()) {
            <span class="err">{{ amountError() }}</span>
          }
        </label>

        @if (methodsError()) {
          <div class="alert">
            {{ methodsError() }}
            <button class="btn ghost" type="button" (click)="loadMethods()">重试</button>
          </div>
        }
        @if (error()) {
          <div class="alert">{{ error() }}</div>
        }

        <button
          class="btn primary wide"
          type="button"
          [disabled]="busy() || !methodId()"
          (click)="submit()"
        >
          {{ busy() ? '提交中…' : '去支付' }}
        </button>
        <p class="muted hint">订单有效期 1 小时，请在此期间完成付款。</p>
      </div>
    }
  `,
  styles: [
    `
      .back {
        margin-bottom: 14px;
        font-size: 13px;
      }
      .form {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 22px 24px 24px;
      }
      .form h1 {
        margin-top: 6px;
      }
      .hint {
        font-size: 12px;
      }
    `,
  ],
})
export class DepositPage {
  private readonly api = inject(Api);

  protected readonly currencies = CURRENCIES;
  protected readonly money = money;
  protected readonly dt = dt;

  protected readonly methods = signal<PaymentMethodInfo[]>([]);
  protected readonly methodsBusy = signal(true);
  protected readonly methodsError = signal('');
  protected readonly methodId = signal('');

  protected readonly currency = signal('USD');
  protected readonly amount = signal('');
  protected readonly amountError = signal('');
  protected readonly error = signal('');
  protected readonly busy = signal(false);
  protected readonly done = signal<DepositCreated | null>(null);

  /** 仅 http(s) 的收银台地址可跳转 */
  protected readonly safeUrl = computed(() => {
    const u = this.done()?.checkout_url ?? '';
    return /^https?:\/\//i.test(u) ? u : '';
  });
  /** 非 http(s)（含空串以外的异常协议）改为展示可复制文本，不跳转 */
  protected readonly unsafeUrl = computed(() => {
    const u = this.done()?.checkout_url ?? '';
    return u && !/^https?:\/\//i.test(u) ? u : '';
  });

  constructor() {
    this.loadMethods();
  }

  protected loadMethods(): void {
    this.methodsBusy.set(true);
    this.methodsError.set('');
    this.api.paymentMethods().subscribe({
      next: (r) => {
        const list = r.list ?? [];
        this.methods.set(list);
        this.methodId.set(list[0]?.id ?? '');
        this.methodsBusy.set(false);
      },
      error: (e: ApiError) => {
        this.methodsError.set(e.message);
        this.methodsBusy.set(false);
      },
    });
  }

  protected pickCurrency(c: string): void {
    this.currency.set(c);
    this.amountError.set('');
  }

  protected onAmount(ev: Event): void {
    this.amount.set((ev.target as HTMLInputElement).value);
    this.amountError.set('');
    this.error.set('');
  }

  /** 支付方式限额提示；max_amount 数值为 0 表示不限。仅展示，不做金额运算。 */
  protected limit(m: PaymentMethodInfo): string {
    const max = Number(m.max_amount) > 0 ? money(m.max_amount) : '不限';
    return `${money(m.min_amount)} ~ ${max}`;
  }

  protected select(ev: Event): void {
    (ev.target as HTMLInputElement).select();
  }

  protected submit(): void {
    const amount = this.amount().trim();
    if (!amount) {
      this.amountError.set('请输入充值金额');
      return;
    }
    if (!depositAmountOk(amount, this.currency())) {
      this.amountError.set('金额格式不支持：JPY/KRW 不支持小数，其余币种最多 2 位小数');
      return;
    }
    if (!this.methodId()) {
      this.error.set('请先选择支付方式');
      return;
    }

    this.busy.set(true);
    this.error.set('');
    // 提交用户输入的字符串原文（仅 trim），不经 Number()/舍入
    this.api
      .createDeposit({
        amount,
        currency: this.currency(),
        payment_method_id: this.methodId(),
      })
      .subscribe({
        next: (d) => {
          this.busy.set(false);
          this.done.set(d);
          if (/^https?:\/\//i.test(d.checkout_url ?? '')) {
            window.open(d.checkout_url, '_blank', 'noopener,noreferrer');
          }
        },
        error: (e: ApiError) => {
          this.busy.set(false);
          this.error.set(
            e.code === 502 ? `${e.message}（支付网关暂时不可用，请稍后重试）` : e.message,
          );
        },
      });
  }

  protected again(): void {
    this.done.set(null);
    this.amount.set('');
    this.amountError.set('');
    this.error.set('');
  }
}
