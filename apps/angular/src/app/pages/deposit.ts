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
  moneyIsZero,
  moneyRaw,
} from '../core/api.service';
import { Mt, Msg, T, t } from '../core/i18n/i18n';

/** 后端支持的 8 种法币（DepositController 的 in: 白名单） */
const CURRENCIES = ['USD', 'CNY', 'EUR', 'JPY', 'KRW', 'GBP', 'BRL', 'INR'];

/** 充值：选支付方式 + 币种 + 金额 → 创建订单 → 打开收银台 */
@Component({
  selector: 'app-deposit',
  imports: [RouterLink, T, Mt],
  template: `
    <a class="btn ghost back" routerLink="/wallet">{{ 'common.back_wallet' | t }}</a>

    @if (done(); as d) {
      <div class="card stack">
        <div class="between">
          <span class="label">{{ 'deposit.created' | t }}</span>
          <span class="badge warn">{{ 'status.dep.pending' | t }}</span>
        </div>
        <div class="kv">
          <span class="muted">{{ 'common.order_no' | t }}</span><span class="mono">{{ d.order_no }}</span>
        </div>
        <div class="kv">
          <span class="muted">{{ 'deposit.amount_label' | t }}</span
          ><span class="mono" [title]="moneyRaw(d.amount)"
            >{{ money(d.amount) }} {{ currency() }}</span
          >
        </div>
        <div class="kv">
          <span class="muted">{{ 'deposit.credit' | t }}</span
          ><span class="mono" [title]="moneyRaw(d.platform_amount)">{{
            money(d.platform_amount)
          }}</span>
        </div>
        @if (d.expires_at) {
          <div class="kv">
            <span class="muted">{{ 'deposit.expires' | t }}</span><span class="mono">{{ dt(d.expires_at) }}</span>
          </div>
        }

        @if (safeUrl(); as u) {
          <a class="btn primary wide" [href]="u" target="_blank" rel="noopener noreferrer"
            >{{ 'deposit.go_pay' | t }}</a
          >
          <p class="muted hint">{{ 'deposit.pay_hint' | t }}</p>
        } @else if (unsafeUrl()) {
          <div class="alert">
            {{ 'deposit.bad_link' | t }}
          </div>
          <label class="field">
            <span>{{ 'deposit.pay_link' | t }}</span>
            <input class="input mono" readonly [value]="unsafeUrl()" (focus)="select($event)" />
          </label>
        }

        <div class="wrap">
          <a class="btn" routerLink="/wallet">{{ 'deposit.view_orders' | t }}</a>
          <button class="btn ghost" type="button" (click)="again()">{{ 'deposit.again' | t }}</button>
        </div>
      </div>
    } @else {
      <div class="card form">
        <div>
          <span class="label">{{ 'wallet.deposit' | t }}</span>
          <h1>{{ 'deposit.title' | t }}</h1>
        </div>

        <label class="field">
          <span>{{ 'deposit.method' | t }}</span>
          <select
            class="input"
            [disabled]="methodsBusy() || !methods().length"
            (change)="methodId.set($any($event.target).value)"
          >
            @if (!methods().length) {
              <option value="">{{ (methodsBusy() ? 'common.loading' : 'deposit.no_methods') | t }}</option>
            }
            @for (m of methods(); track m.id) {
              <option [value]="m.id" [selected]="m.id === methodId()">
                {{ 'deposit.method_option' | t: { name: m.name, limit: limit(m) } }}
              </option>
            }
          </select>
        </label>

        <label class="field">
          <span>{{ 'deposit.currency' | t }}</span>
          <select class="input" (change)="pickCurrency($any($event.target).value)">
            @for (c of currencies; track c) {
              <option [value]="c" [selected]="c === currency()">{{ c }}</option>
            }
          </select>
        </label>

        <label class="field">
          <span>{{ 'deposit.amount_with_cur' | t: { cur: currency() } }}</span>
          <input
            class="input mono"
            inputmode="decimal"
            autocomplete="off"
            placeholder="0.00"
            [value]="amount()"
            (input)="onAmount($event)"
          />
          @if (amountError()) {
            <span class="err">{{ amountError() | mt }}</span>
          }
        </label>

        @if (methodsError()) {
          <div class="alert">
            {{ methodsError() }}
            <button class="btn ghost" type="button" (click)="loadMethods()">{{ 'common.retry' | t }}</button>
          </div>
        }
        @if (error()) {
          <div class="alert">{{ error() | mt }}</div>
        }

        <button
          class="btn primary wide"
          type="button"
          [disabled]="busy() || !methodId()"
          (click)="submit()"
        >
          {{ (busy() ? 'common.submitting' : 'deposit.submit') | t }}
        </button>
        <p class="muted hint">{{ 'deposit.validity' | t }}</p>
      </div>
    }
  `,
  styles: [
    `
      /* 同 withdraw.ts：表单页收成一栏，桌面右留白，窄屏不受影响 */
      :host {
        display: block;
        max-width: 640px;
      }
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
  protected readonly moneyRaw = moneyRaw;
  protected readonly dt = dt;

  protected readonly methods = signal<PaymentMethodInfo[]>([]);
  protected readonly methodsBusy = signal(true);
  protected readonly methodsError = signal('');
  protected readonly methodId = signal('');

  protected readonly currency = signal('USD');
  protected readonly amount = signal('');
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg`；同信号的每个写入点都走这两态 */
  protected readonly amountError = signal<Msg>('');
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg`；同信号的每个写入点都走这两态 */
  protected readonly error = signal<Msg>('');
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

  /** 支付方式限额提示；max_amount 为 0 表示不限。仅展示，不做金额运算。 */
  protected limit(m: PaymentMethodInfo): string {
    // 原先这里写 `Number(m.max_amount) > 0` —— 金额列过数值转型，本批明令禁止；改走
    // moneyIsZero（同 money() 的字符串判据）。原样保留「不限」的取值分支。
    const max = moneyIsZero(m.max_amount) ? t('common.unlimited') : money(m.max_amount);
    return `${money(m.min_amount)} ~ ${max}`;
  }

  protected select(ev: Event): void {
    (ev.target as HTMLInputElement).select();
  }

  protected submit(): void {
    const amount = this.amount().trim();
    if (!amount) {
      this.amountError.set({ key: 'deposit.err_amount_required' });
      return;
    }
    if (!depositAmountOk(amount, this.currency())) {
      this.amountError.set({ key: 'deposit.err_amount_format' });
      return;
    }
    if (!this.methodId()) {
      this.error.set({ key: 'deposit.err_method_required' });
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
            e.code === 502 ? { key: 'deposit.err_gateway', params: { msg: e.message } } : e.message,
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
