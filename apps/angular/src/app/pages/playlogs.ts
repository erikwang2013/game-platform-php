/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Api, ApiError, PlayLog, PlayLogDetail, dt, money } from '../core/api.service';

/**
 * 流水动作短码 → 中文。**取值真源是写入侧，不是列注释**（两者对不上，列注释是漂移）：
 *  ① 代码实际写的 5 个：`start`（`GameController.php:291`，直接建行）、`launch`（同文件 :296，走
 *     `GamePlayLogService::write` —— 每次启动游戏都写一行、金额全 0）、`bet`/`settle`/`refund`
 *     （`GameSdkController.php:96,148,224` 与 `ProviderController.php:105,151,212`，经 `GamePlayRecorder`）。
 *  ② `install/install.sql:576` 的列注释写的是 `start/end/earn/spend` —— 后三个**全仓零写入点**，
 *     留着只兜底旧数据（`earn`/`spend` 的真身是钱包那本账的 `game_spend`/`game_earn`，见 wallet.ts
 *     的 TX_LABEL，与本列无关）。照注释抄就会漏掉 `launch`，把英文原键摆给用户。
 * 服务端按 `action` 筛选是等值匹配，未知值原样透出（见 actionLabel），不猜。
 * 本表的**键集**与 react 树 MyGames.tsx 的 ACTION_LABEL 一致（同一列两个客户端，不许再漂移）；
 * 唯一一处**文案**分歧是 `start`：这边写「开局」，react 写「开始」（本树 wallet.ts 早已把同一事件
 * game_spend 标成「开局扣费」，两页并排看时「开局」才对得上）。要两树逐字一致，改这一行即可。
 */
const ACTION_LABEL: Record<string, string> = {
  start: '开局',
  launch: '启动',
  bet: '下注',
  settle: '结算',
  refund: '退还',
  end: '结束',
  earn: '赢取',
  spend: '消耗',
};

/**
 * 游戏流水 —— 游戏币（非平台币）的每一次变动。
 *
 * 与钱包流水是两本账：`/wallet/transactions` 记平台币，这里记游戏币（game_amount_*）。
 * 服务端唯一的下标真相是 GamePlayLog；列表项不含 metadata，展开时才取详情。
 */
@Component({
  selector: 'app-playlogs',
  template: `
    <div class="between sect">
      <h2>游戏流水</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load(1)">刷新</button>
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
          <strong>加载失败</strong>
          <span>{{ error() }}</span>
          <button class="btn" type="button" (click)="load(1)">重试</button>
        </div>
      } @else if (!items().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>暂无游戏流水</strong>
          <span>进入任意游戏并产生游戏币变动后，记录会出现在这里</span>
        </div>
      } @else {
        <div class="rows">
          @for (l of items(); track l.id) {
            <button class="row asbtn" type="button" (click)="open(l)">
              <div class="grow">
                <div class="t">{{ actionLabel(l.action) }}</div>
                <div class="s">{{ dt(l.created_at) }}{{ l.session_id ? ' · ' + l.session_id : '' }}</div>
              </div>
              <span class="amount" [class.in]="isIn(l)" [class.out]="!isIn(l)">
                {{ sign(l.game_amount_change) }}{{ money(abs(l.game_amount_change)) }}
              </span>
            </button>
          }
        </div>
        @if (page() < lastPage()) {
          <div class="more">
            <button class="btn" type="button" [disabled]="more()" (click)="load(page() + 1)">
              {{ more() ? '加载中…' : '加载更多' }}
            </button>
          </div>
        }
      }
    </div>

    @if (cur() || detailErr() || detailLoading()) {
      <div class="backdrop" (click)="close()"></div>
      <div class="modal" role="dialog" aria-modal="true" aria-label="流水详情">
        <header class="between">
          <b>流水详情</b>
          <button class="btn ghost" type="button" (click)="close()">关闭</button>
        </header>
        <div class="modal-body">
          @if (detailLoading()) {
            <div class="state"><span class="spin"></span> 加载中…</div>
          } @else if (detailErr()) {
            <div class="alert">{{ detailErr() }}</div>
          } @else if (cur(); as d) {
            <div class="kv"><span>动作</span><span>{{ actionLabel(d.action) }}</span></div>
            <div class="kv"><span>时间</span><span>{{ dt(d.created_at) }}</span></div>
            <div class="kv"><span>会话</span><span class="mono">{{ d.session_id || '—' }}</span></div>
            <div class="kv"><span>变动前</span><span class="amount">{{ money(d.game_amount_before) }}</span></div>
            <div class="kv"><span>变动</span><span class="amount">{{ sign(d.game_amount_change) }}{{ money(abs(d.game_amount_change)) }}</span></div>
            <div class="kv"><span>变动后</span><span class="amount">{{ money(d.game_amount_after) }}</span></div>
            <div class="kv"><span>平台币变动</span><span class="amount">{{ money(d.platform_amount_change) }}</span></div>
            @if (d.started_at || d.ended_at) {
              <div class="kv"><span>开始 / 结束</span><span>{{ dt(d.started_at) }} / {{ dt(d.ended_at) }}</span></div>
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
      .asbtn {
        width: 100%;
        background: transparent;
        border: 0;
        border-bottom: 1px solid var(--stroke);
        color: inherit;
        text-align: left;
        cursor: pointer;
        font: inherit;
      }
      .asbtn:hover {
        background: var(--panel);
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
  protected readonly items = signal<PlayLog[]>([]);
  protected readonly loading = signal(true);
  protected readonly more = signal(false);
  protected readonly error = signal('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);

  protected readonly cur = signal<PlayLogDetail | null>(null);
  protected readonly detailLoading = signal(false);
  protected readonly detailErr = signal('');

  constructor() {
    this.load(1);
  }

  protected money = money;

  protected actionLabel(a?: string): string {
    return a ? (ACTION_LABEL[a] ?? a) : '—';
  }

  /** money() 只做展示格式化；正负号据此判定，不参与运算 */
  protected abs(v: number | string): string {
    const s = String(v ?? '0');
    return s.startsWith('-') ? s.slice(1) : s;
  }

  protected sign(v: number | string): string {
    return String(v ?? '').startsWith('-') ? '−' : '+';
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
        this.error.set(e.message);
        this.loading.set(false);
        this.more.set(false);
      },
    });
  }

  protected open(l: PlayLog): void {
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(true);
    this.api.playLogDetail(l.id).subscribe({
      next: (d) => {
        this.cur.set(d);
        this.detailLoading.set(false);
      },
      error: (e: ApiError) => {
        this.detailErr.set(e.message);
        this.detailLoading.set(false);
      },
    });
  }

  protected close(): void {
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(false);
  }
}
