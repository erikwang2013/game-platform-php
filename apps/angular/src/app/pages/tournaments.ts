/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  Api,
  ApiError,
  Tournament,
  TournamentDetail,
  TournamentStatus,
  dt,
  money,
  moneyIsZero,
  moneyRaw,
} from '../core/api.service';

const TABS: { key: TournamentStatus; label: string }[] = [
  { key: 'upcoming', label: '即将开始' },
  { key: 'active', label: '进行中' },
  { key: 'ended', label: '已结束' },
];

/**
 * 赛事 —— 列表 + 详情弹框 + 报名，三个端点都在这里。
 *
 * 默认落在**即将开始**而不是服务端默认的 `active`：`join()` 要求 `start_at > now`
 * （见 `TournamentController::join`），而 `active` 的定义就是 `start_at <= now <= end_at`
 * ⇒ **进行中的赛事一条都报不进**。落地就摆在唯一报得进的那一栏。
 *
 * 报名按钮同理：只有「还没开赛」才摆，否则用户只会拿到一个必然 400 的按钮。
 * 但这只是**本地预判**（拿本机时钟跟服务端的 start_at 比），真闸在服务端 ——
 * 它拒绝时（已开赛/已报名/满员）把服务端的 message 原样透出，不吞成"操作失败"。
 *
 * FeatureFlag 关掉时 `list` 回 code=503「Tournaments not available」；`detail` **不查这个开关**，
 * 所以别拿详情页能打开当成"赛事功能开着"的证据。两处都在注释里标了。
 */
@Component({
  selector: 'app-tournaments',
  imports: [RouterLink],
  template: `
    <div class="between sect">
      <h2>赛事</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="reload()">刷新</button>
    </div>

    <div class="tabs">
      @for (t of tabs; track t.key) {
        <button class="tab-btn" type="button" [class.on]="status() === t.key" (click)="pick(t.key)">
          {{ t.label }}
        </button>
      }
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
          <button class="btn" type="button" (click)="reload()">重试</button>
        </div>
      } @else if (!items().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ emptyTitle() }}</strong>
          <span>换个标签看看，或稍后再来</span>
        </div>
      } @else {
        <div class="rows">
          @for (t of items(); track t.id) {
            <button class="row asbtn" type="button" (click)="openFresh(t.id)">
              <div class="grow">
                <div class="t">{{ t.name }}</div>
                <div class="s">
                  {{ t.game?.name || '全平台' }} · {{ playerText(t) }} · {{ dt(t.start_at) }}
                </div>
                <div class="s">
                  奖池 <span [title]="moneyRaw(t.prize_pool)">{{ money(t.prize_pool) }}</span> ·
                  {{ feeText(t) }}
                </div>
              </div>
              <span class="badge">查看</span>
            </button>
          }
        </div>
        @if (page() < lastPage()) {
          <div class="more">
            <button class="btn" type="button" [disabled]="more()" (click)="loadMore()">
              {{ more() ? '加载中…' : '加载更多' }}
            </button>
          </div>
        }
      }
    </div>

    @if (cur() || dErr() || dLoading()) {
      <div class="backdrop" (click)="close()"></div>
      <div class="modal" role="dialog" aria-modal="true" aria-label="赛事详情">
        <header class="between">
          <b>{{ cur()?.name || '赛事' }}</b>
          <button class="btn ghost" type="button" (click)="close()">关闭</button>
        </header>
        <div class="modal-body">
          @if (dLoading()) {
            <div class="state"><span class="spin"></span> 加载中…</div>
          } @else if (dErr()) {
            <div class="alert">{{ dErr() }}</div>
          } @else if (cur(); as d) {
            @if (note()) {
              <div class="alert ok">{{ note() }}</div>
            }
            <div class="kv">
              <span>奖池</span
              ><span class="amount" [title]="moneyRaw(d.prize_pool)">{{
                money(d.prize_pool)
              }}</span>
            </div>
            <div class="kv">
              <span>报名费</span
              ><span class="amount" [title]="moneyRaw(d.entry_fee)">{{ feeText(d) }}</span>
            </div>
            <div class="kv"><span>人数</span><span>{{ playerText(d) }}</span></div>
            <div class="kv"><span>时间</span><span>{{ dt(d.start_at) }} ~ {{ dt(d.end_at) }}</span></div>
            @if (d.type) {
              <div class="kv"><span>类型</span><span>{{ d.type }}</span></div>
            }
            @if (d.game) {
              <div class="kv">
                <span>游戏</span>
                <span><a class="lnk" [routerLink]="['/game', d.game.id]" (click)="close()">{{ d.game.name }}</a></span>
              </div>
            }
            @if (d.description) {
              <p class="desc">{{ d.description }}</p>
            }

            @if (d.my_entry; as me) {
              <div class="mine">
                <span class="badge on">已报名</span>
                <span class="s">积分 {{ me.score }}{{ me.rank ? ' · 第 ' + me.rank + ' 名' : '' }}</span>
              </div>
            } @else if (canJoin(d)) {
              <button class="btn primary wfull" type="button" [disabled]="joining()" (click)="join(d)">
                {{ joining() ? '报名中…' : '报名参赛' }}
              </button>
            } @else {
              <span class="s muted">报名已截止（开赛后不能再报名）</span>
            }
            @if (joinErr()) {
              <div class="alert">{{ joinErr() }}</div>
            }

            <div class="between sect2">
              <span class="label">排行榜</span>
              <span class="s muted">按积分倒序，最多 100 条</span>
            </div>
            @if (!d.leaderboard.length) {
              <p class="s muted">还没有成绩</p>
            } @else {
              <div class="rows">
                @for (r of d.leaderboard; track $index) {
                  <div class="row">
                    <span class="rank mono">{{ r.rank ?? $index + 1 }}</span>
                    <div class="grow"><div class="t">{{ r.user }}</div></div>
                    <span class="amount mono">{{ r.score }}</span>
                  </div>
                }
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
      /* 分段控件：一条轨道 + 选中片浮起。上一版是三个各自描边发光的药丸，
         看不出"三选一"这层意思。 */
      .tabs {
        display: flex;
        gap: 3px;
        margin-bottom: 14px;
        padding: 3px;
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        background: var(--surface-2);
      }
      .tab-btn {
        flex: 1;
        min-height: 40px;
        padding: 8px 12px;
        border-radius: var(--r-sm);
        border: 1px solid transparent;
        background: transparent;
        color: var(--text-2);
        font: inherit;
        font-size: var(--fs-md);
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition:
          background var(--t-fast) var(--ease),
          color var(--t-fast) var(--ease);
      }
      .tab-btn:hover {
        color: var(--text);
      }
      .tab-btn.on {
        color: var(--text);
        background: var(--raise);
        box-shadow: var(--sh-1);
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
      .desc {
        font-size: 13px;
        color: var(--muted);
        white-space: pre-wrap;
        overflow-wrap: anywhere;
      }
      .mine {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 12px 0;
      }
      .wfull {
        width: 100%;
        margin-top: 12px;
      }
      .sect2 {
        margin: 18px 0 6px;
      }
      .rank {
        min-width: 28px;
        color: var(--muted);
        font-size: 13px;
      }
      .lnk {
        text-decoration: underline;
      }
    `,
  ],
})
export class TournamentsPage {
  private readonly api = inject(Api);

  protected readonly tabs = TABS;
  protected readonly dt = dt;
  protected readonly money = money;
  protected readonly moneyRaw = moneyRaw;

  protected readonly status = signal<TournamentStatus>('upcoming');
  protected readonly items = signal<Tournament[]>([]);
  protected readonly loading = signal(true);
  protected readonly more = signal(false);
  protected readonly error = signal('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);

  protected readonly cur = signal<TournamentDetail | null>(null);
  protected readonly dLoading = signal(false);
  protected readonly dErr = signal('');
  protected readonly note = signal('');
  protected readonly joinErr = signal('');
  protected readonly joining = signal(false);

  constructor() {
    this.load(1);
  }

  protected pick(s: TournamentStatus): void {
    if (this.status() === s) return;
    this.status.set(s);
    this.load(1);
  }

  protected reload(): void {
    this.load(1);
  }

  protected loadMore(): void {
    this.load(this.page() + 1);
  }

  protected emptyTitle(): string {
    return { upcoming: '暂无即将开始的赛事', active: '当前没有进行中的赛事', ended: '还没有已结束的赛事' }[
      this.status()
    ];
  }

  /** 报名费 0 = 免费。只做**展示**判定，不参与任何金额运算（金额一律字符串交给服务端） */
  protected feeText(t: Tournament): string {
    // 原先这里写 `Number(t.entry_fee) > 0` —— 金额列过数值转型，本批明令禁止；改走
    // moneyIsZero（同 money() 的字符串判据）。「免费」的取值分支原样保留。
    return moneyIsZero(t.entry_fee) ? '免费' : money(t.entry_fee);
  }

  protected playerText(t: Tournament): string {
    return t.max_players > 0 ? `${t.player_count} / ${t.max_players} 人` : `${t.player_count} 人`;
  }

  /**
   * 本地预判：只在**开赛前**才摆报名按钮（服务端 join() 的第一道闸就是 `start_at <= now` ⇒ 400）。
   * 与服务端比的是同一把时钟吗？不是 —— 本机时钟可能偏，所以服务端真拒绝时把 message 原样透出。
   */
  protected canJoin(t: Tournament): boolean {
    const ms = new Date(String(t.start_at ?? '').replace(' ', 'T')).getTime();
    return Number.isFinite(ms) && ms > Date.now();
  }

  protected load(p: number): void {
    const first = p === 1;
    (first ? this.loading : this.more).set(true);
    this.error.set('');
    this.api.tournaments(this.status(), p, 20).subscribe({
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

  /**
   * 打开详情。**不清 note()** —— `join()` 成功回调里是「先 `note.set('报名成功…')`（:364）
   * 紧接着 `this.open(t.id)`（:365）」，本方法若在此清 note，提示会在同一个 tick 里被当场擦掉。
   * （这条理由原先引的是已撤下的 coupons 页做旁证，那一页 2026-10-01 已删，改成本方法自己的调用点。）
   */
  protected open(id: string): void {
    this.cur.set(null);
    this.dErr.set('');
    this.joinErr.set('');
    this.dLoading.set(true);
    this.api.tournamentDetail(id).subscribe({
      next: (d) => {
        this.cur.set(d);
        this.dLoading.set(false);
      },
      error: (e: ApiError) => {
        this.dErr.set(e.message);
        this.dLoading.set(false);
      },
    });
  }

  /** 点列表行：清掉上一条赛事的提示与错误，再开 */
  protected openFresh(id: string): void {
    this.note.set('');
    this.open(id);
  }

  protected join(t: TournamentDetail): void {
    if (this.joining()) return;
    this.joining.set(true);
    this.note.set('');
    this.joinErr.set('');
    this.api.tournamentJoin(t.id).subscribe({
      next: () => {
        this.joining.set(false);
        this.note.set('报名成功，开赛后会出现在排行榜里。');
        this.open(t.id); // 重拉详情：my_entry 由服务端给，不在本地推算
        this.load(1); // 人数变了，列表也重拉
      },
      error: (e: ApiError) => {
        this.joining.set(false);
        this.joinErr.set(e.message);
      },
    });
  }

  protected close(): void {
    this.cur.set(null);
    this.dErr.set('');
    this.joinErr.set('');
    this.dLoading.set(false);
  }
}
