/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Api, ApiError, Leaderboard, RankRow, money, moneyRaw } from '../core/api.service';

/** metric → 中文（真源 LeaderboardService::computeRanking 只认 earned/spent/play_count） */
const METRIC_LABEL: Record<string, string> = {
  earned: '累计买入',
  spent: '累计卖出',
  play_count: '开局次数',
};

/** 榜单周期 → 中文（服务端按 type 决定统计窗口） */
const TYPE_LABEL: Record<string, string> = {
  daily: '日榜',
  weekly: '周榜',
  monthly: '月榜',
  all: '总榜',
};

/**
 * 排行榜 —— 公开接口，未登录也能看。
 *
 * ⚠ 榜单条目**只有 3 个字段**：rank / user_id / score。
 * `user_id` 自 2026-10-02 起是 **hashid 字符串**（`LeaderboardController::ranking` 出网前逐行
 * `encodeId`；Service 里那把仍吐裸 BIGINT，因为它还喂 WS 与 Redis 缓存）—— 与 react 树同步。
 * **没有昵称、没有头像** ⇒ 榜单先天只能显示一个编号，这是**后端字段缺口，不是本树实现问题**。
 * 因此这里：① 不编造用户名/头像；② 不逐条拉 /user/profile 补字段（N+1，且多数拿不到）；
 * ③ 只展示末 4 个字符（版面选择，与 react 树 `maskedId` 同形）——hashid 即便完整显示也不是秘密
 * （它本来就在响应报文里），打码只是为了不把十几位塞进这一列，不承担"遮住主键"的职责。
 */
@Component({
  selector: 'app-leaderboard',
  template: `
    <div class="between sect">
      <h2>排行榜</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load()">刷新</button>
    </div>

    @if (loading()) {
      <div class="card grid2">
        @for (i of [1, 2, 3, 4]; track i) {
          <div class="skeleton sk"></div>
        }
      </div>
    } @else if (error()) {
      <div class="card state">
        <strong>加载失败</strong>
        <span>{{ error() }}</span>
        <button class="btn" type="button" (click)="load()">重试</button>
      </div>
    } @else if (!boards().length) {
      <div class="card state">
        <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
        <strong>暂无榜单</strong>
        <span>平台还没有开启排行榜</span>
      </div>
    } @else {
      <div class="chips">
        @for (b of boards(); track b.id) {
          <button class="chip" type="button" [class.on]="cur()?.id === b.id" (click)="pick(b)">
            {{ b.name }}
          </button>
        }
      </div>

      <div class="card">
        @if (cur(); as b) {
          <div class="between head">
            <b>{{ b.name }}</b>
            <span class="wrap">
              <span class="badge">{{ typeLabel(b.type) }}</span>
              <span class="badge">{{ metricLabel(b.metric) }}</span>
            </span>
          </div>
        }
        @if (rankLoading()) {
          <div class="rows">
            @for (i of [1, 2, 3]; track i) {
              <div class="row"><div class="skeleton sk-row"></div></div>
            }
          </div>
        } @else if (rankErr()) {
          <div class="state">
            <strong>加载失败</strong>
            <span>{{ rankErr() }}</span>
            <button class="btn" type="button" (click)="pick(cur()!)">重试</button>
          </div>
        } @else if (!rows().length) {
          <div class="state">
            <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
            <strong>榜单还是空的</strong>
            <span>该周期内还没有产生可统计的数据</span>
          </div>
        } @else {
          <div class="rows">
            @for (r of rows(); track r.rank) {
              <div class="row">
                <span class="rk" [class.top]="r.rank <= 3">{{ r.rank }}</span>
                <div class="grow">
                  <div class="t">玩家 {{ maskedId(r.user_id) }}</div>
                  <div class="s">{{ metricLabel(cur()?.metric) }}</div>
                </div>
                <span class="amount" [title]="moneyRaw(r.score)">{{ score(r) }}</span>
              </div>
            }
          </div>
        }
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
      .grid2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
      }
      .sk {
        height: 42px;
        border-radius: var(--r-sm);
      }
      .sk-row {
        height: 16px;
        width: 70%;
        border-radius: var(--r-sm);
      }
      .chips {
        margin-bottom: 14px;
      }
      .head {
        padding-bottom: 12px;
        margin-bottom: 4px;
        border-bottom: 1px solid var(--line);
      }
      .rk {
        flex: none;
        width: 30px;
        height: 30px;
        border-radius: var(--r-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        background: var(--surface-2);
        border: 1px solid var(--line);
      }
      /* 榜首=代币金。这棵树里金色只出现在「战利品」场合（奖励/名次/代币），
         所以它是名次色，不是又一个品牌色。 */
      .rk.top {
        background: var(--gold);
        color: var(--gold-ink);
        border-color: transparent;
      }
    `,
  ],
})
export class LeaderboardPage {
  private readonly api = inject(Api);

  /** 金额类榜单的 title 悬停要显后端原始串；play_count 榜的 score 是次数，原样挂也无妨 */
  protected readonly moneyRaw = moneyRaw;

  protected readonly boards = signal<Leaderboard[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal('');

  protected readonly cur = signal<Leaderboard | null>(null);
  protected readonly rows = signal<RankRow[]>([]);
  protected readonly rankLoading = signal(false);
  protected readonly rankErr = signal('');

  constructor() {
    this.load();
  }

  protected metricLabel(m?: string): string {
    return m ? (METRIC_LABEL[m] ?? m) : '';
  }

  /** 榜单名后缀（周期）—— 名字里通常已含周期，重复就不加 */
  protected typeLabel(t?: string): string {
    return t ? (TYPE_LABEL[t] ?? t) : '';
  }

  /** 身份列只展示末 4 个字符（与 react 树 `maskedId` 同形）；接口没有昵称，只能显示编号 */
  protected maskedId(id: string): string {
    return `#···${String(id).slice(-4)}`;
  }

  /** play_count 榜的 score 是次数，不做货币格式化；金额类指标才走 money() */
  protected score(r: RankRow): string {
    const v = r.score;
    if (this.cur()?.metric === 'play_count') return `${Number(v ?? 0).toLocaleString()} 次`;
    return money(v);
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set('');
    this.api.leaderboards().subscribe({
      next: (r) => {
        const list = r.list ?? [];
        this.boards.set(list);
        this.loading.set(false);
        if (list.length) this.pick(list[0]!);
      },
      error: (e: ApiError) => {
        this.error.set(e.message);
        this.loading.set(false);
      },
    });
  }

  protected pick(b: Leaderboard): void {
    this.cur.set(b);
    this.rankLoading.set(true);
    this.rankErr.set('');
    this.api.leaderboardRanking(b.id).subscribe({
      next: (r) => {
        this.rows.set(r.ranking ?? []);
        this.rankLoading.set(false);
      },
      error: (e: ApiError) => {
        this.rankErr.set(e.message);
        this.rankLoading.set(false);
      },
    });
  }
}
