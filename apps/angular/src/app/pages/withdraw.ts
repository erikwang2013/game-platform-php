/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  Api,
  ApiError,
  CaptchaProof,
  IdentityStatus,
  WithdrawApplied,
  dt,
  money,
  moneyRaw,
} from '../core/api.service';
import { CaptchaBox } from '../core/captcha';
import { Mt, Msg, T } from '../core/i18n/i18n';

/** 提现请求体（验证码答案在弹框确认时并入） */
interface WithdrawBody {
  platform_amount: string;
  method: string;
  account_info: string;
}

/**
 * 提现方式。`label` 是**词条键**，但要过 `t()` 才成文 —— `t()` 对认不出的键原样返回，
 * 所以品牌名 `PayPal` 直接写字面量，不必为它造一条永远只有一种译法的词条。
 */
const METHODS = [
  { v: 'paypal', label: 'PayPal' },
  { v: 'bank', label: 'withdraw.m_bank' },
  { v: 'crypto', label: 'withdraw.m_crypto' },
];

const ST_LABEL: Record<string, string> = {
  pending: 'status.wd.pending',
  reviewing: 'status.wd.reviewing',
  manual_review: 'status.wd.manual_review',
  approved: 'status.wd.approved',
  processing: 'status.wd.processing',
  completed: 'common.completed',
  paid: 'status.wd.paid',
  rejected: 'status.wd.rejected',
  cancelled: 'common.cancelled',
  failed: 'common.failed',
};

/** 提现：方式 + 金额 + 收款信息 → 申请 → 展示手续费/实际到账/新余额 */
@Component({
  selector: 'app-withdraw',
  imports: [RouterLink, CaptchaBox, T, Mt],
  template: `
    <a class="btn ghost back" routerLink="/wallet">{{ 'common.back_wallet' | t }}</a>

    <!--
      KYC 档位提示：服务端 WithdrawController::withdrawLevel 只在认证通过时给 verified 档
      （更高的单笔/日/月额度、更低的费率），其余一律 default 档。未认证的用户常常不知道自己
      为什么提得比预期少 —— 把差别和入口摆在表单上方。认证通过则整条不显示。
    -->
    @if (kyc(); as k) {
      @if (k.status !== 'approved') {
        <div class="card kycbar" [class.warnbar]="k.status !== 'pending'">
          <div class="grow">
            <div class="t">
              {{ (k.status === 'pending' ? 'withdraw.kyc_pending' : 'withdraw.kyc_none') | t }}
            </div>
            <div class="s">
              {{
                (k.status === 'pending' ? 'withdraw.kyc_hint_pending' : 'withdraw.kyc_hint_none')
                  | t
              }}
            </div>
          </div>
          @if (k.status !== 'pending') {
            <a class="btn ghost" routerLink="/kyc">{{ 'withdraw.go_kyc' | t }}</a>
          }
        </div>
      }
    }

    @if (done(); as d) {
      <div class="card stack">
        <div class="between">
          <span class="label">{{ 'withdraw.submitted' | t }}</span>
          <span class="badge {{ d.status === 'pending' ? 'warn' : 'on' }}">
            {{ st(d.status) | t }}
          </span>
        </div>
        <div class="kv">
          <span class="muted">{{ 'common.order_no' | t }}</span><span class="mono">{{ d.order_no }}</span>
        </div>
        <div class="kv">
          <span class="muted">{{ 'withdraw.amount_label' | t }}</span
          ><span class="mono" [title]="moneyRaw(d.platform_amount)">{{
            money(d.platform_amount)
          }}</span>
        </div>
        <div class="kv">
          <span class="muted">{{ 'withdraw.fee' | t }}</span
          ><span class="mono" [title]="moneyRaw(d.fee)">{{ money(d.fee) }}</span>
        </div>
        <div class="kv">
          <span class="muted">{{ 'withdraw.actual' | t }}</span
          ><span class="mono amount in" [title]="moneyRaw(d.actual_amount)">{{
            money(d.actual_amount)
          }}</span>
        </div>
        <div class="kv">
          <span class="muted">{{ 'common.account_balance' | t }}</span
          ><span class="mono" [title]="moneyRaw(d.balance_after)">{{
            money(d.balance_after)
          }}</span>
        </div>
        @if (d.created_at) {
          <div class="kv">
            <span class="muted">{{ 'withdraw.submitted_at' | t }}</span><span class="mono">{{ dt(d.created_at) }}</span>
          </div>
        }
        <div class="wrap">
          <a class="btn" routerLink="/wallet">{{ 'withdraw.view_orders' | t }}</a>
          <button class="btn ghost" type="button" (click)="again()">{{ 'withdraw.again' | t }}</button>
        </div>
      </div>
    } @else {
      <div class="card form">
        <div>
          <span class="label">{{ 'wallet.withdraw' | t }}</span>
          <h1>{{ 'withdraw.title' | t }}</h1>
        </div>

        <label class="field">
          <span>{{ 'withdraw.method_label' | t }}</span>
          <select class="input" (change)="method.set($any($event.target).value)">
            @for (m of methods; track m.v) {
              <option [value]="m.v" [selected]="m.v === method()">{{ m.label | t }}</option>
            }
          </select>
        </label>

        <label class="field">
          <span>{{ 'withdraw.amount_platform' | t }}</span>
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

        <label class="field">
          <span>{{ 'withdraw.account_info' | t }}</span>
          <textarea
            class="input"
            rows="3"
            [placeholder]="'withdraw.account_ph' | t"
            [value]="account()"
            (input)="onAccount($event)"
          ></textarea>
          @if (accountError()) {
            <span class="err">{{ accountError() | mt }}</span>
          }
        </label>

        @if (error()) {
          <div class="alert">{{ error() | mt }}</div>
        }

        <button class="btn primary wide" type="button" [disabled]="busy()" (click)="submit()">
          {{ (busy() ? 'common.submitting' : 'withdraw.submit') | t }}
        </button>
        <p class="muted hint">{{ 'withdraw.fee_hint' | t }}</p>
      </div>
    }

    <!-- 提现服务端强制验证码：本地校验通过后弹框，确认才发原请求 -->
    <app-captcha [(open)]="capOpen" [busy]="busy()" [action]="'withdraw.captcha_action'" (proof)="onProof($event)" />
  `,
  styles: [
    `
      /* 表单类页面收成一栏：1440 宽下把 4 个输入框和提交键拉满 1100px，
         字段行长会超出舒适阅读宽度，提交键也变得像一条横幅。
         窄屏不受影响（640 > 360），桌面则是右侧留白。 */
      :host {
        display: block;
        max-width: 640px;
      }
      .back {
        margin-bottom: 14px;
        font-size: 13px;
      }
      .kycbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 14px;
        padding: 14px 18px;
        border-left: 3px solid var(--warn);
        background: color-mix(in srgb, var(--warn) 10%, var(--surface));
      }
      .kycbar.warnbar {
        border-left-color: var(--neg);
        background: color-mix(in srgb, var(--neg) 10%, var(--surface));
      }
      .kycbar .grow {
        flex: 1;
        min-width: 0;
      }
      .kycbar .t {
        font-weight: 650;
        font-size: 14px;
      }
      .kycbar .s {
        margin-top: 3px;
        font-size: 12px;
        color: var(--muted);
        line-height: 1.6;
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
      .form textarea {
        resize: vertical;
        font: inherit;
      }
      .hint {
        font-size: 12px;
      }
    `,
  ],
})
export class WithdrawPage {
  private readonly api = inject(Api);

  protected readonly methods = METHODS;
  protected readonly money = money;
  protected readonly moneyRaw = moneyRaw;
  protected readonly dt = dt;

  protected readonly method = signal('paypal');
  protected readonly amount = signal('');
  protected readonly account = signal('');
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg`；同信号的每个写入点都走这两态 */
  protected readonly amountError = signal<Msg>('');
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg`；同信号的每个写入点都走这两态 */
  protected readonly accountError = signal<Msg>('');
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg` */
  protected readonly error = signal<Msg>('');
  protected readonly busy = signal(false);
  protected readonly done = signal<WithdrawApplied | null>(null);
  /** 弹框状态 + 开框时冻结的请求体（框开期间页面控件仍可能被键盘改动） */
  protected readonly capOpen = signal(false);
  protected readonly pending = signal<WithdrawBody | null>(null);
  /** KYC 状态：只用于提示提现档位；取不到就整条不显示，不挡提现 */
  protected readonly kyc = signal<IdentityStatus | null>(null);

  constructor() {
    this.api.identityStatus().subscribe({
      next: (s) => this.kyc.set(s),
      error: () => this.kyc.set(null),
    });
  }

  protected st(s: string): string {
    return ST_LABEL[s] ?? s ?? '—';
  }

  protected onAmount(ev: Event): void {
    this.amount.set((ev.target as HTMLInputElement).value);
    this.amountError.set('');
    this.error.set('');
  }

  protected onAccount(ev: Event): void {
    this.account.set((ev.target as HTMLTextAreaElement).value);
    this.accountError.set('');
    this.error.set('');
  }

  protected submit(): void {
    const amount = this.amount().trim();
    const accountInfo = this.account().trim();
    if (!amount) {
      this.amountError.set({ key: 'withdraw.err_amount_required' });
      return;
    }
    // 仅字符串格式校验，不做金额运算；精度/限额/余额均由服务端判定
    if (!/^\d+(\.\d+)?$/.test(amount)) {
      this.amountError.set({ key: 'withdraw.err_amount_format' });
      return;
    }
    if (!accountInfo) {
      this.accountError.set({ key: 'withdraw.err_account_required' });
      return;
    }

    this.error.set('');
    this.pending.set({ platform_amount: amount, method: this.method(), account_info: accountInfo });
    this.capOpen.set(true);
  }

  /** 弹框确认 → 带 captcha_key/clicks 调原接口；失败关框，服务端 message 落在原错误位 */
  protected onProof(p: CaptchaProof): void {
    const body = this.pending();
    if (!body) return;
    this.busy.set(true);
    this.error.set('');
    this.api.applyWithdraw({ ...body, ...p }).subscribe({
      next: (d) => {
        this.capOpen.set(false);
        this.busy.set(false);
        this.done.set(d);
      },
      error: (e: ApiError) => {
        this.capOpen.set(false);
        this.busy.set(false);
        this.error.set(this.hint(e));
      },
    });
  }

  /**
   * 按后端错误码补充可操作的提示；服务端原文照实展示，不做归因猜测。
   *
   * ⚠ 返回**两态 `Msg` 而不是拼好的句子**：本方法是异步回调（`error:`）里调用的，
   * 返回字符串就等于在那一刻把译文定稿 —— 切语言后这一行不跟着变。存键、渲染期才算。
   */
  private hint(e: ApiError): Msg {
    switch (e.code) {
      case 403:
        return { key: 'withdraw.err_blocked', params: { msg: e.message } };
      case 429:
        return { key: 'withdraw.err_pending', params: { msg: e.message } };
      case 503:
        return { key: 'withdraw.err_unavailable', params: { msg: e.message } };
      default:
        return e.message;
    }
  }

  protected again(): void {
    this.done.set(null);
    this.amount.set('');
    this.account.set('');
    this.error.set('');
  }
}
