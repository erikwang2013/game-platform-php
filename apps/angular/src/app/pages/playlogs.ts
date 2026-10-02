/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { ModalFocus } from '../components/modal-focus';
import { Mt, Msg, T } from '../core/i18n/i18n';
import {
  Api,
  ApiError,
  GameWallet,
  PlayLog,
  PlayLogDetail,
  dt,
  money,
  moneyIsZero,
  moneyRaw,
} from '../core/api.service';

/**
 * 流水动作短码 → 中文。**取值真源是写入侧，不是列注释**（两者对不上，列注释是漂移）：
 *  ① 代码实际写的 5 个：`start`（`GameController.php:291`，直接建行）、`launch`（同文件 :296，走
 *     `GamePlayLogService::write` —— 每次启动游戏都写一行、金额全 0）、`bet`/`settle`/`refund`
 *     （`GameSdkController.php:96,148,224` 与 `ProviderController.php:105,151,212`，经 `GamePlayRecorder`）。
 *  ② `install/install.sql:583` 的列注释写的是 `start/end/earn/spend` —— 后三个**全仓零写入点**，
 *     留着只兜底旧数据（`earn`/`spend` 的真身是钱包那本账的 `game_spend`/`game_earn`，见 wallet.ts
 *     的 TX_LABEL，与本列无关）。照注释抄就会漏掉 `launch`，把英文原键摆给用户。
 * 服务端按 `action` 筛选是等值匹配，未知值原样透出（见 actionLabel），不猜。
 * 本表的**键集**与 react 树 MyGames.tsx 的 ACTION_LABEL 一致（同一列两个客户端，不许再漂移）；
 * 唯一一处**文案**分歧是 `start`：这边写「开局」，react 写「开始」（本树 wallet.ts 早已把同一事件
 * game_spend 标成「开局扣费」，两页并排看时「开局」才对得上）。要两树逐字一致，改 `dict/gameplay.ts`
 * 那一行的中文即可。`mygames.action_start` 的**键名**与 react 同键，11 种外文译文逐字取自那边。
 *
 * 表里存的是**键**、不是译文：渲染处过 `| t`（`{{ actionLabel(…) | t }}`，两处：列表行与详情框）——
 * 存 `t()` 的结果会在切语言后留下一行旧语言的残影（见 core/i18n/i18n.ts 的 `Msg`）。
 * `| t` 对认不出的键**原样返回**，于是 actionLabel 的「未知动作原样透出」与「—」两条分支
 * 不需要额外处理，与迁移前逐字节同形。
 */
const ACTION_LABEL: Record<string, string> = {
  start: 'mygames.action_start',
  launch: 'mygames.action_launch',
  bet: 'mygames.action_bet',
  settle: 'mygames.action_settle',
  refund: 'mygames.action_refund',
  end: 'mygames.action_end',
  earn: 'mygames.action_earn',
  spend: 'mygames.action_spend',
};

/**
 * 我的游戏 —— 上半是**游戏币余额**（`/game/balance`），下半是**游戏流水**（`/game/play-logs`）。
 *
 * 与钱包是两本账：`/wallet/info` 记平台币，这里记各游戏内的币。
 * 服务端唯一的下标真相是 GamePlayLog；列表项不含 metadata，展开时才取详情。
 */
@Component({
  selector: 'app-playlogs',
  imports: [ModalFocus, T, Mt],
  template: `
    <div class="between sect">
      <h2>{{ 'mygames.title' | t }}</h2>
      <button class="btn ghost" type="button" [disabled]="assetsLoading()" (click)="loadAssets()">
        {{ 'common.refresh' | t }}
      </button>
    </div>

    <div class="card">
      @if (assetsLoading()) {
        <div class="rows">
          @for (i of [1, 2]; track i) {
            <div class="row"><div class="skeleton sk-row"></div></div>
          }
        </div>
      } @else if (assetsError()) {
        <div class="state">
          <strong>{{ 'common.load_failed' | t }}</strong>
          <span>{{ assetsError() | mt }}</span>
          <button class="btn" type="button" (click)="loadAssets()">{{ 'common.retry' | t }}</button>
        </div>
      } @else if (!games().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ 'mygames.empty_title' | t }}</strong>
          <span>{{ 'mygames.empty_hint' | t }}</span>
        </div>
      } @else {
        <div class="assets">
          @for (g of games(); track g.game_id) {
            <div class="asset">
              <div class="between">
                <div class="t">{{ g.name }}</div>
                @if (g.type) {
                  <span class="badge">{{ g.type }}</span>
                }
              </div>
              <div class="rows">
                @for (c of g.currencies; track c.currency_id) {
                  <div class="row">
                    <div class="grow">
                      <div class="t">{{ c.name }}</div>
                      <div class="s">
                        {{ c.symbol }}
                        @if (!moneyIsZero(c.frozen_balance)) {
                          · {{ 'common.frozen' | t }}
                          <span [title]="moneyRaw(c.frozen_balance)">{{
                            money(c.frozen_balance)
                          }}</span>
                        }
                      </div>
                    </div>
                    <span class="amount" [title]="moneyRaw(c.balance)">{{ money(c.balance) }}</span>
                  </div>
                }
              </div>
            </div>
          }
        </div>
      }
    </div>

    <div class="between sect">
      <h2>{{ 'wallet.records' | t }}</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load(1)">
        {{ 'common.refresh' | t }}
      </button>
    </div>

    <div class="card">
      @if (loading()) {
        <div class="rows">
          @for (i of [1, 2, 3]; track i) {
            <div class="row"><div class="skeleton sk-row"></div></div>
          }
        </div>
      } @else if (error()) {
        <div class="state">
          <strong>{{ 'common.load_failed' | t }}</strong>
          <span>{{ error() | mt }}</span>
          <button class="btn" type="button" (click)="load(1)">{{ 'common.retry' | t }}</button>
        </div>
      } @else if (!items().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ 'mygames.no_logs_title' | t }}</strong>
          <span>{{ 'mygames.no_logs_hint' | t }}</span>
        </div>
      } @else {
        <div class="rows">
          @for (l of items(); track l.id) {
            <button class="row asbtn" type="button" (click)="open(l)">
              <div class="grow">
                <div class="t">{{ actionLabel(l.action) | t }}</div>
                <div class="s">{{ dt(l.created_at) }}{{ l.session_id ? ' · ' + l.session_id : '' }}</div>
              </div>
              <span
                class="amount"
                [class.in]="isIn(l)"
                [class.out]="!isIn(l)"
                [title]="moneyRaw(l.game_amount_change)"
              >
                {{ sign(l.game_amount_change) }}{{ money(abs(l.game_amount_change)) }}
              </span>
            </button>
          }
        </div>
        @if (page() < lastPage()) {
          <div class="more">
            <button class="btn" type="button" [disabled]="more()" (click)="load(page() + 1)">
              {{ (more() ? 'common.loading' : 'wallet.load_more') | t }}
            </button>
          </div>
        }
      }
    </div>

    @if (cur() || detailErr() || detailLoading()) {
      <div class="backdrop" (click)="close()"></div>
      <!-- uiModal：开框聚焦首个可聚焦元素 / Tab 圈在框内 / Esc 关框 / 关框把焦点还给打开者 -->
      <div
        class="modal"
        uiModal
        (dismiss)="close()"
        role="dialog"
        aria-modal="true"
        [attr.aria-label]="'mygames.detail_title' | t"
      >
        <header class="between">
          <b>{{ 'mygames.detail_title' | t }}</b>
          <button class="btn ghost" type="button" (click)="close()">{{ 'common.close' | t }}</button>
        </header>
        <div class="modal-body">
          @if (detailLoading()) {
            <div class="state"><span class="spin"></span> {{ 'common.loading' | t }}</div>
          } @else if (detailErr()) {
            <div class="alert">{{ detailErr() | mt }}</div>
          } @else if (cur(); as d) {
            <div class="kv">
              <span>{{ 'mygames.col_action' | t }}</span
              ><span>{{ actionLabel(d.action) | t }}</span>
            </div>
            <div class="kv"><span>{{ 'mygames.col_time' | t }}</span><span>{{ dt(d.created_at) }}</span></div>
            <div class="kv">
              <span>{{ 'mygames.col_session' | t }}</span
              ><span class="mono">{{ d.session_id || '—' }}</span>
            </div>
            <div class="kv">
              <span>{{ 'mygames.col_before' | t }}</span
              ><span class="amount" [title]="moneyRaw(d.game_amount_before)">{{
                money(d.game_amount_before)
              }}</span>
            </div>
            <div class="kv">
              <span>{{ 'mygames.col_change' | t }}</span
              ><span class="amount" [title]="moneyRaw(d.game_amount_change)"
                >{{ sign(d.game_amount_change) }}{{ money(abs(d.game_amount_change)) }}</span
              >
            </div>
            <div class="kv">
              <span>{{ 'mygames.col_after' | t }}</span
              ><span class="amount" [title]="moneyRaw(d.game_amount_after)">{{
                money(d.game_amount_after)
              }}</span>
            </div>
            <div class="kv">
              <span>{{ 'mygames.col_platform_change' | t }}</span
              ><span class="amount" [title]="moneyRaw(d.platform_amount_change)">{{
                money(d.platform_amount_change)
              }}</span>
            </div>
            @if (d.started_at || d.ended_at) {
              <div class="kv">
                <span>{{ 'mygames.col_window' | t }}</span
                ><span>{{ dt(d.started_at) }} / {{ dt(d.ended_at) }}</span>
              </div>
            }
          }
        </div>
      </div>
    }
  `,
  styles: [
    `
      .sect {
        margin: 0 0 14px;
      }
      .sect h2 {
        margin: 0;
        font-size: 17px;
      }
      .sk-row {
        height: 16px;
        width: 70%;
        border-radius: 8px;
      }
      /* 游戏资产：一款游戏一块，块内是该游戏的各币种余额 */
      .assets {
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      .asset {
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        padding: 10px 14px;
        background: var(--surface-2);
      }
      .asbtn {
        width: 100%;
        background: transparent;
        border: 0;
        border-bottom: 1px solid var(--line);
        color: inherit;
        text-align: left;
        cursor: pointer;
        font: inherit;
        transition: background var(--t-fast) var(--ease);
      }
      .asbtn:hover {
        background: var(--surface-2);
      }
      .more {
        display: flex;
        justify-content: center;
        padding-top: 14px;
      }
    `,
  ],
})
export class PlaylogsPage {
  private readonly api = inject(Api);

  protected readonly dt = dt;
  protected readonly moneyIsZero = moneyIsZero;

  /**
   * 三处报错信号存的是 **`Msg`（两态）而不是拼好的字符串** —— 服务端原文走 raw 那一态，
   * 提示语本体（`common.load_failed` 那种）在模板上过 `| mt` 渲染期才求值。
   *
   * ⚠ 别改成存 `e.message`（构造那一刻的**冻结**文案）：那个串在切语言时不会重渲，
   * 屏幕上会留着旧语言的报错（本批的 `.message` → `.msg` 就是这个意思，见 `source-nails.spec.ts` ③）。
   */
  /** 游戏币余额。与下面的流水**各自独立失败**：一个端点挂了，另一个照常显示 */
  protected readonly games = signal<GameWallet[]>([]);
  protected readonly assetsLoading = signal(true);
  protected readonly assetsError = signal<Msg>('');

  protected readonly items = signal<PlayLog[]>([]);
  protected readonly loading = signal(true);
  protected readonly more = signal(false);
  protected readonly error = signal<Msg>('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);

  private loadingId = '';

  protected readonly cur = signal<PlayLogDetail | null>(null);
  protected readonly detailLoading = signal(false);
  protected readonly detailErr = signal<Msg>('');

  constructor() {
    this.loadAssets();
    this.load(1);
  }

  protected loadAssets(): void {
    this.assetsLoading.set(true);
    this.assetsError.set('');
    this.api.gameBalances().subscribe({
      next: (r) => {
        this.games.set(r.games ?? []);
        this.assetsLoading.set(false);
      },
      error: (e: ApiError) => {
        this.assetsError.set(e.msg);
        this.assetsLoading.set(false);
      },
    });
  }

  protected money = money;
  protected moneyRaw = moneyRaw;

  protected actionLabel(a?: string): string {
    return a ? (ACTION_LABEL[a] ?? a) : '—';
  }

  /** money() 只做展示格式化；正负号据此判定，不参与运算 */
  protected abs(v: number | string): string {
    const s = String(v ?? '0');
    return s.startsWith('-') ? s.slice(1) : s;
  }

  /**
   * 负号用 **ASCII `-`(U+002D) 而不是排版减号 `−`(U+2212)**。三处理由：
   * ① 后端 bcmath 串本身就是 ASCII `-`（`'-1234.5678'`），`money()` 只是透传；若反过来让
   *    `money()` 去产 U+2212，要动 30+ 个调用点，而这里只动一行。
   * ② 同一屏里 `{{ sign() }}{{ money(...) }}` 与钱包页的 `{{ money(r.amount) }}` 必须同字符，
   *    否则「−12.34」和「-12.34」并排显示两个不同的负号。
   * ③ 本元素 `title` 是 `moneyRaw()` 给的**后端原始串**，里面也是 ASCII `-`；正文与 title
   *    用同一字符，悬停对拍不会看着像两个数。
   * 字体栈也偏 ASCII：U+2212 不是所有字体都有字形（13 种语言下有过缺字风险）。
   */
  protected sign(v: number | string): string {
    return String(v ?? '').startsWith('-') ? '-' : '+';
  }

  protected isIn(l: PlayLog): boolean {
    return !String(l.game_amount_change ?? '').startsWith('-');
  }

  protected load(p: number): void {
    const first = p === 1;
    (first ? this.loading : this.more).set(true);
    this.error.set('');
    this.api.playLogs(p, 20).subscribe({
      next: (r) => {
        this.items.set(first ? r.items : [...this.items(), ...r.items]);
        this.page.set(r.page);
        this.lastPage.set(r.last_page);
        this.loading.set(false);
        this.more.set(false);
      },
      error: (e: ApiError) => {
        this.error.set(e.msg);
        this.loading.set(false);
        this.more.set(false);
      },
    });
  }

  /**
   * 在途详情请求的**代号** —— 与 `tickets.ts` / `announcements.ts` 的 `loadingId` 同一套判据。
   *
   * 不写这笔的现场（慢网必现）：点开一行 → 框里显示「加载中…」→ 用户点遮罩/关闭 →
   * 回包这才落地 → `cur.set(d)` 把**用户刚关掉的那条**又弹回来。四页同病，本页四页之一。
   *
   * 关框与换行都把它置空/改写 ⇒ 旧回包在 next/error 里比对不上就整条丢弃，不落任何信号。
   * 为什么不用「存 Subscription 再 unsubscribe」：本树另外两页（tickets/announcements）
   * 已经用代号这套，两套判据并存会让下一个改这几页的人先花时间分辨它们有什么区别。
   */
  protected open(l: PlayLog): void {
    this.loadingId = l.id;
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(true);
    this.api.playLogDetail(l.id).subscribe({
      next: (d) => {
        if (this.loadingId !== l.id) return; // 关框/换行后落地的旧回包：丢弃
        this.cur.set(d);
        this.detailLoading.set(false);
      },
      error: (e: ApiError) => {
        if (this.loadingId !== l.id) return;
        this.detailErr.set(e.msg);
        this.detailLoading.set(false);
      },
    });
  }

  protected close(): void {
    this.loadingId = ''; // 作废在途请求：否则慢回包一到，弹框会自己又弹回来
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(false);
  }
}
