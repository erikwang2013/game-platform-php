/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api, ApiError, WithdrawApplied, dt, money } from '../core/api.service';

const METHODS = [
  { v: 'paypal', t: 'PayPal' },
  { v: 'bank', t: '银行卡' },
  { v: 'crypto', t: '加密货币' },
];

const ST_LABEL: Record<string, string> = {
  pending: '待审核',
  reviewing: '审核中',
  manual_review: '人工审核',
  approved: '已通过',
  processing: '处理中',
  completed: '已完成',
  paid: '已打款',
  rejected: '已驳回',
  cancelled: '已取消',
  failed: '失败',
};

/** 提现：方式 + 金额 + 收款信息 → 申请 → 展示手续费/实际到账/新余额 */
@Component({
  selector: 'app-withdraw',
  imports: [RouterLink],
  template: `
    <a class="btn ghost back" routerLink="/wallet">← 返回钱包</a>

    @if (done(); as d) {
      <div class="card stack">
        <div class="between">
          <span class="label">提现申请已提交</span>
          <span class="badge {{ d.status === 'pending' ? 'warn' : 'on' }}">
            {{ st(d.status) }}
          </span>
        </div>
        <div class="kv">
          <span class="muted">订单号</span><span class="mono">{{ d.order_no }}</span>
        </div>
        <div class="kv">
          <span class="muted">提现金额</span><span class="mono">{{ d.platform_amount }}</span>
        </div>
        <div class="kv">
          <span class="muted">手续费</span><span class="mono">{{ d.fee }}</span>
        </div>
        <div class="kv">
          <span class="muted">实际到账</span
          ><span class="mono amount in">{{ d.actual_amount }}</span>
        </div>
        <div class="kv">
          <span class="muted">账户余额</span><span class="mono">{{ money(d.balance_after) }}</span>
        </div>
        @if (d.created_at) {
          <div class="kv">
            <span class="muted">提交时间</span><span class="mono">{{ dt(d.created_at) }}</span>
          </div>
        }
        <div class="wrap">
          <a class="btn" routerLink="/wallet">查看提现订单</a>
          <button class="btn ghost" type="button" (click)="again()">再提一笔</button>
        </div>
      </div>
    } @else {
      <div class="card form">
        <div>
          <span class="label">提现</span>
          <h1>申请提现</h1>
        </div>

        <label class="field">
          <span>提现方式</span>
          <select class="input" (change)="method.set($any($event.target).value)">
            @for (m of methods; track m.v) {
              <option [value]="m.v" [selected]="m.v === method()">{{ m.t }}</option>
            }
          </select>
        </label>

        <label class="field">
          <span>提现金额（平台币）</span>
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

        <label class="field">
          <span>收款账号信息</span>
          <textarea
            class="input"
            rows="3"
            placeholder="PayPal 邮箱 / 银行卡号与开户行 / 钱包地址"
            [value]="account()"
            (input)="onAccount($event)"
          ></textarea>
          @if (accountError()) {
            <span class="err">{{ accountError() }}</span>
          }
        </label>

        @if (error()) {
          <div class="alert">{{ error() }}</div>
        }

        <button class="btn primary wide" type="button" [disabled]="busy()" (click)="submit()">
          {{ busy() ? '提交中…' : '提交申请' }}
        </button>
        <p class="muted hint">手续费按等级与 VIP 折扣计算，提交后展示实际到账金额。</p>
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
  protected readonly dt = dt;

  protected readonly method = signal('paypal');
  protected readonly amount = signal('');
  protected readonly account = signal('');
  protected readonly amountError = signal('');
  protected readonly accountError = signal('');
  protected readonly error = signal('');
  protected readonly busy = signal(false);
  protected readonly done = signal<WithdrawApplied | null>(null);

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
      this.amountError.set('请输入提现金额');
      return;
    }
    // 仅字符串格式校验，不做金额运算；精度/限额/余额均由服务端判定
    if (!/^\d+(\.\d+)?$/.test(amount)) {
      this.amountError.set('金额格式不正确，请输入数字');
      return;
    }
    if (!accountInfo) {
      this.accountError.set('请填写收款账号信息');
      return;
    }

    this.busy.set(true);
    this.error.set('');
    this.api
      .applyWithdraw({ platform_amount: amount, method: this.method(), account_info: accountInfo })
      .subscribe({
        next: (d) => {
          this.busy.set(false);
          this.done.set(d);
        },
        error: (e: ApiError) => {
          this.busy.set(false);
          this.error.set(this.hint(e));
        },
      });
  }

  /** 按后端错误码补充可操作的提示；服务端原文照实展示，不做归因猜测 */
  private hint(e: ApiError): string {
    switch (e.code) {
      case 403:
        return `${e.message}（提现被全局开关或风控拦截，如有疑问请联系客服）`;
      case 429:
        return `${e.message}（已有一笔提现处理中，请稍后再试）`;
      case 503:
        return `${e.message}（提现服务暂时不可用，请稍后重试）`;
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
