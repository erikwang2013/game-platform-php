/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import {
  Api,
  ApiError,
  CaptchaProof,
  ExchangeDirection,
  ExchangeDone,
  ExchangePayload,
  ExchangeQuote,
  Game,
  money,
  moneyRaw,
  Num,
} from '../core/api.service';
import { CaptchaBox } from '../core/captcha';

/** 游戏列表单页条数；超过再加“加载更多”（复用 gameList 的 page 参数） */
const GAMES_PER_PAGE = 100;

/** 兑换：选游戏 → 选该游戏币种 → 买/卖 → 询价 → 确认。
 *  game_id / currency_id 均为服务端下发的 hashid，不能自行拼造。 */
@Component({
  selector: 'app-exchange',
  imports: [RouterLink, CaptchaBox],
  template: `
    <div class="stack">
      <a class="btn ghost back" routerLink="/wallet">← 返回钱包</a>

      @if (done(); as d) {
        <div class="card stack">
          <div class="between">
            <span class="label">兑换完成</span>
            <span class="badge on">{{ d.direction === 'in' ? '买入' : '卖出' }}</span>
          </div>
          <div class="kv">
            <span class="muted">{{ d.direction === 'in' ? '支付平台币' : '卖出游戏币' }}</span>
            <span class="mono" [title]="moneyRaw(spendAmt(d))">{{ money(spendAmt(d)) }}</span>
          </div>
          <div class="kv">
            <span class="muted">
              {{ d.direction === 'in' ? '到账游戏币（已扣点差）' : '到账平台币（已扣点差）' }}
            </span>
            <span class="mono amount in" [title]="moneyRaw(gainAmt(d))">{{
              money(gainAmt(d))
            }}</span>
          </div>
          <div class="kv">
            <span class="muted">点差费用</span
            ><span class="mono" [title]="moneyRaw(d.spread_fee)">{{ money(d.spread_fee) }}</span>
          </div>
          <div class="kv">
            <span class="muted">成交汇率</span><span class="mono">{{ d.rate }}</span>
          </div>
          <div class="kv">
            <span class="muted">账户余额</span
            ><span class="mono" [title]="moneyRaw(d.balance_after)">{{
              money(d.balance_after)
            }}</span>
          </div>
          <div class="wrap">
            <a class="btn" routerLink="/wallet">返回钱包</a>
            <button class="btn ghost" type="button" (click)="again()">再兑一笔</button>
          </div>
        </div>
      } @else {
        <div class="card form">
          <div>
            <span class="label">兑换</span>
            <h1>平台币 / 游戏币互兑</h1>
          </div>

          <label class="field">
            <span>游戏</span>
            <select
              class="input"
              [disabled]="gamesBusy() || !games().length"
              (change)="pickGame($any($event.target).value)"
            >
              @if (!games().length) {
                <option value="">{{ gamesBusy() ? '加载中…' : '暂无可兑换的游戏' }}</option>
              }
              @for (g of games(); track g.id) {
                <option [value]="g.id" [selected]="g.id === gameId()">{{ g.name }}</option>
              }
            </select>
          </label>

          <label class="field">
            <span>游戏币种</span>
            <select
              class="input"
              [disabled]="!currencies().length"
              (change)="pickCurrency($any($event.target).value)"
            >
              @if (!currencies().length) {
                <option value="">该游戏暂无可用币种</option>
              }
              @for (c of currencies(); track c.id) {
                <option [value]="c.id" [selected]="c.id === currencyId()">
                  {{ c.name }}（{{ c.symbol }}）· 汇率 {{ c.exchange_rate }}
                </option>
              }
            </select>
          </label>

          <div class="field">
            <span>方向</span>
            <div class="chips">
              <button
                type="button"
                class="chip"
                [class.on]="direction() === 'in'"
                (click)="pickDirection('in')"
              >
                买入（平台币 → 游戏币）
              </button>
              <button
                type="button"
                class="chip"
                [class.on]="direction() === 'out'"
                (click)="pickDirection('out')"
              >
                卖出（游戏币 → 平台币）
              </button>
            </div>
          </div>

          <label class="field">
            <span>
              @if (direction() === 'in') {
                支付平台币数量
              } @else {
                卖出游戏币数量（{{ cur()?.name || '—' }}）
              }
            </span>
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
            @if (direction() === 'out') {
              <span class="muted hint">此处填写的是游戏币数量，不是平台币数量。</span>
            }
          </label>

          @if (gamesError()) {
            <div class="alert">
              {{ gamesError() }}
              <button class="btn ghost" type="button" (click)="loadGames()">重试</button>
            </div>
          }
          @if (!quote() && error()) {
            <div class="alert">{{ error() }}</div>
          }

          <button
            class="btn primary wide"
            type="button"
            [disabled]="quoteBusy() || !currencyId()"
            (click)="quoteNow()"
          >
            {{ quoteBusy() ? '询价中…' : '询价' }}
          </button>
        </div>

        @if (quote(); as q) {
          <div class="card stack">
            <span class="label">询价结果</span>
            <div class="kv">
              <span class="muted">汇率</span>
              <span class="mono">1 平台币 ≈ {{ q.rate }} {{ cur()?.name || '' }}</span>
            </div>
            <div class="kv">
              <span class="muted">点差</span><span class="mono">{{ q.spread_pct }}%</span>
            </div>
            <div class="kv">
              <span class="muted">点差费用</span
              ><span class="mono" [title]="moneyRaw(q.spread_fee)">{{ money(q.spread_fee) }}</span>
            </div>
            @if (direction() === 'in') {
              <div class="kv">
                <span class="muted">折合游戏币（扣点差前）</span
                ><span class="mono" [title]="moneyRaw(q.game_amount)">{{
                  money(q.game_amount)
                }}</span>
              </div>
              <div class="kv">
                <span class="muted">预计获得</span>
                <span class="mono amount in" [title]="moneyRaw(q.actual_game_amount)"
                  >{{ money(q.actual_game_amount) }} {{ cur()?.symbol || '' }}</span
                >
              </div>
            } @else {
              <div class="kv">
                <span class="muted">折合平台币（扣点差前）</span
                ><span class="mono" [title]="moneyRaw(q.platform_equivalent)">{{
                  money(q.platform_equivalent)
                }}</span>
              </div>
              <div class="kv">
                <span class="muted">预计到账</span>
                <span class="mono amount in" [title]="moneyRaw(q.actual_platform_amount)"
                  >{{ money(q.actual_platform_amount) }} 平台币</span
                >
              </div>
            }
            @if (error()) {
              <div class="alert">{{ error() }}</div>
            }
            <button class="btn primary wide" type="button" [disabled]="busy()" (click)="confirm()">
              {{ busy() ? '兑换中…' : '确认兑换' }}
            </button>
            <p class="muted hint">报价随行情变动，修改数量或币种后需重新询价。</p>
          </div>
        }
      }
    </div>

    <!-- 卖出（游戏币 → 平台币）服务端强制验证码；买入不加 -->
    <app-captcha [(open)]="capOpen" [busy]="busy()" action="确认卖出" (proof)="onProof($event)" />
  `,
  styles: [
    `
      /* 同 withdraw.ts：表单页收成一栏，桌面右留白，窄屏不受影响 */
      :host {
        display: block;
        max-width: 640px;
      }
      .back {
        margin-bottom: 0;
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
export class ExchangePage {
  private readonly api = inject(Api);

  protected readonly money = money;
  protected readonly moneyRaw = moneyRaw;

  /**
   * 成交结果的两个金额字段**随方向换位**（`in`=买入 / `out`=卖出），字符串里没法写 `d.a || d.b`
   * 那种兜底 —— 两个字段都恒存在，只是语义不同。故把三元的选取收进这两个取值器，
   * 模板只负责 `money(取值器(d))`：一处定义、不会出现标题与正文选中不同字段的情况。
   */
  protected spendAmt(d: ExchangeDone): Num {
    return d.direction === 'in' ? d.platform_amount : d.game_amount;
  }

  /** 到账侧净额（in=游戏币 / out=平台币；两侧都已扣点差，见后端 exchangeLegs） */
  protected gainAmt(d: ExchangeDone): Num {
    return d.direction === 'in' ? d.game_amount : d.platform_amount;
  }

  protected readonly games = signal<Game[]>([]);
  protected readonly gamesBusy = signal(true);
  protected readonly gamesError = signal('');
  protected readonly gameId = signal('');
  protected readonly currencyId = signal('');
  protected readonly direction = signal<ExchangeDirection>('in');
  protected readonly amount = signal('');
  protected readonly amountError = signal('');
  protected readonly error = signal('');
  protected readonly quoteBusy = signal(false);
  protected readonly quote = signal<ExchangeQuote | null>(null);
  protected readonly busy = signal(false);
  protected readonly done = signal<ExchangeDone | null>(null);
  /** 弹框状态 + 开框时冻结的卖出请求体（必须与已展示的询价一致，框开期间不许被改） */
  protected readonly capOpen = signal(false);
  protected readonly pending = signal<ExchangePayload | null>(null);

  protected readonly currencies = computed(
    () => this.games().find((g) => g.id === this.gameId())?.currencies ?? [],
  );

  protected readonly cur = computed(
    () => this.currencies().find((c) => c.id === this.currencyId()) ?? null,
  );

  constructor() {
    this.loadGames();
  }

  protected loadGames(): void {
    this.gamesBusy.set(true);
    this.gamesError.set('');
    this.api.gameList({ page: 1, per_page: GAMES_PER_PAGE }).subscribe({
      next: (r) => {
        const list = r.items ?? [];
        this.games.set(list);
        this.gamesBusy.set(false);
        if (!this.gameId()) this.pickGame(list[0]?.id ?? '');
      },
      error: (e: ApiError) => {
        this.gamesError.set(e.message);
        this.gamesBusy.set(false);
      },
    });
  }

  protected pickGame(id: string): void {
    this.gameId.set(id);
    this.currencyId.set(this.games().find((g) => g.id === id)?.currencies[0]?.id ?? '');
    this.stale();
  }

  protected pickCurrency(id: string): void {
    this.currencyId.set(id);
    this.stale();
  }

  protected pickDirection(d: ExchangeDirection): void {
    if (this.direction() === d) return;
    this.direction.set(d);
    this.stale();
  }

  protected onAmount(ev: Event): void {
    this.amount.set((ev.target as HTMLInputElement).value);
    this.amountError.set('');
    this.stale();
  }

  /** 询价 */
  protected quoteNow(): void {
    const amount = this.amount().trim();
    if (!amount) {
      this.amountError.set('请输入兑换数量');
      return;
    }
    if (!/^\d+(\.\d+)?$/.test(amount)) {
      this.amountError.set('数量格式不正确，请输入数字');
      return;
    }
    if (!this.gameId() || !this.currencyId()) {
      this.error.set('请先选择游戏与币种');
      return;
    }

    this.quoteBusy.set(true);
    this.error.set('');
    this.api.quoteExchange(this.payload(amount)).subscribe({
      next: (q) => {
        this.quoteBusy.set(false);
        this.quote.set(q);
      },
      error: (e: ApiError) => {
        this.quoteBusy.set(false);
        this.error.set(e.message);
      },
    });
  }

  protected confirm(): void {
    const amount = this.amount().trim();
    if (!this.quote() || !amount) return;

    const payload = this.payload(amount);
    if (this.direction() === 'in') {
      // 买入不需要验证码（后端刻意没加）
      this.trade(this.api.exchangeBuy(payload));
      return;
    }
    this.error.set('');
    this.pending.set(payload);
    this.capOpen.set(true);
  }

  /** 弹框确认 → 带 captcha_key/clicks 调卖出接口；失败关框，服务端 message 落在原错误位 */
  protected onProof(p: CaptchaProof): void {
    const payload = this.pending();
    if (!payload) return;
    this.trade(this.api.exchangeSell({ ...payload, ...p }));
  }

  private trade(call: Observable<ExchangeDone>): void {
    this.busy.set(true);
    this.error.set('');
    call.subscribe({
      next: (d) => {
        this.capOpen.set(false);
        this.busy.set(false);
        this.done.set(d);
        this.quote.set(null);
        this.amount.set('');
      },
      error: (e: ApiError) => {
        this.capOpen.set(false);
        this.busy.set(false);
        this.error.set(e.message);
      },
    });
  }

  /** 数量变化后旧报价作废：不允许拿过期价格确认 */
  private stale(): void {
    this.quote.set(null);
    this.error.set('');
  }

  /**
   * 请求体：direction='out'（卖出）时 platform_amount 承载的是【游戏币】数量 ——
   * 服务端复用了同一字段名，不要改成 game_amount。
   */
  private payload(amount: string): ExchangePayload {
    return {
      game_id: this.gameId(),
      currency_id: this.currencyId(),
      direction: this.direction(),
      platform_amount: amount,
    };
  }

  protected again(): void {
    this.done.set(null);
    this.amount.set('');
    this.amountError.set('');
    this.error.set('');
    this.quote.set(null);
  }
}
