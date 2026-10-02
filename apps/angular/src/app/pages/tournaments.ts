/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ModalFocus } from '../components/modal-focus';
import { Mt, Msg, T } from '../core/i18n/i18n';
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

/**
 * 标签页**存键、不存译文**：模板上过 `| t`（`{{ tab.label | t }}`）。
 * 存 `t()` 的结果会在切语言后留下一行旧语言的标签（见 core/i18n/i18n.ts 的 `Msg`）。
 * 本表的文案与 react 树 Tournaments.tsx 的 TABS 同键同值（同一列两个客户端，不许再漂移）。
 */
const TABS: { key: TournamentStatus; label: string }[] = [
  { key: 'upcoming', label: 'tourney.tab_upcoming' },
  { key: 'active', label: 'tourney.tab_active' },
  { key: 'ended', label: 'tourney.tab_ended' },
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
  imports: [RouterLink, ModalFocus, T, Mt],
  template: `
    <div class="between sect">
      <h2>{{ 'tourney.title' | t }}</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="reload()">
        {{ 'common.refresh' | t }}
      </button>
    </div>

    <div class="tabs">
      @for (tab of tabs; track tab.key) {
        <button
          class="tab-btn"
          type="button"
          [class.on]="status() === tab.key"
          (click)="pick(tab.key)"
        >
          {{ tab.label | t }}
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
          <strong>{{ 'common.load_failed' | t }}</strong>
          <span>{{ error() | mt }}</span>
          <button class="btn" type="button" (click)="reload()">{{ 'common.retry' | t }}</button>
        </div>
      } @else if (!items().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ emptyTitle() | t }}</strong>
          <span>{{ 'tourney.empty_hint' | t }}</span>
        </div>
      } @else {
        <div class="rows">
          @for (row of items(); track row.id) {
            <button class="row asbtn" type="button" (click)="openFresh(row.id)">
              <div class="grow">
                <div class="t">{{ row.name }}</div>
                <div class="s">
                  {{ row.game?.name || ('tourney.all_platform' | t) }} · {{ playerText(row) | mt }} ·
                  {{ dt(row.start_at) }}
                </div>
                <div class="s">
                  {{ 'tourney.prize_pool' | t }}
                  <span [title]="moneyRaw(row.prize_pool)">{{ money(row.prize_pool) }}</span> ·
                  {{ feeText(row) | mt }}
                </div>
              </div>
              <span class="badge">{{ 'common.view' | t }}</span>
            </button>
          }
        </div>
        @if (page() < lastPage()) {
          <div class="more">
            <button class="btn" type="button" [disabled]="more()" (click)="loadMore()">
              {{ (more() ? 'common.loading' : 'wallet.load_more') | t }}
            </button>
          </div>
        }
      }
    </div>

    @if (cur() || dErr() || dLoading()) {
      <div class="backdrop" (click)="close()"></div>
      <!-- uiModal：开框聚焦首个可聚焦元素 / Tab 圈在框内 / Esc 关框 / 关框把焦点还给打开者 -->
      <div
        class="modal"
        uiModal
        (dismiss)="close()"
        role="dialog"
        aria-modal="true"
        [attr.aria-label]="'tourney.detail_title' | t"
      >
        <header class="between">
          <b>{{ cur()?.name || ('tourney.title' | t) }}</b>
          <button class="btn ghost" type="button" (click)="close()">{{ 'common.close' | t }}</button>
        </header>
        <div class="modal-body">
          @if (dLoading()) {
            <div class="state"><span class="spin"></span> {{ 'common.loading' | t }}</div>
          } @else if (dErr()) {
            <div class="alert">{{ dErr() | mt }}</div>
          } @else if (cur(); as d) {
            @if (note()) {
              <div class="alert ok">{{ note() | mt }}</div>
            }
            <div class="kv">
              <span>{{ 'tourney.prize_pool' | t }}</span
              ><span class="amount" [title]="moneyRaw(d.prize_pool)">{{
                money(d.prize_pool)
              }}</span>
            </div>
            <div class="kv">
              <span>{{ 'tourney.entry_fee' | t }}</span
              ><span class="amount" [title]="moneyRaw(d.entry_fee)">{{ feeText(d) | mt }}</span>
            </div>
            <div class="kv">
              <span>{{ 'tourney.players_col' | t }}</span
              ><span>{{ playerText(d) | mt }}</span>
            </div>
            <div class="kv">
              <span>{{ 'tourney.time' | t }}</span
              ><span>{{ dt(d.start_at) }} ~ {{ dt(d.end_at) }}</span>
            </div>
            @if (d.type) {
              <div class="kv"><span>{{ 'tourney.type' | t }}</span><span>{{ d.type }}</span></div>
            }
            @if (d.game) {
              <div class="kv">
                <span>{{ 'tourney.game' | t }}</span>
                <span><a class="lnk" [routerLink]="['/game', d.game.id]" (click)="close()">{{ d.game.name }}</a></span>
              </div>
            }
            @if (d.description) {
              <p class="desc">{{ d.description }}</p>
            }

            @if (d.my_entry; as me) {
              <div class="mine">
                <span class="badge on">{{ 'tourney.joined_badge' | t }}</span>
                <span class="s"
                  >{{ 'tourney.score' | t }} {{ me.score
                  }}{{ me.rank ? ('tourney.rank_suffix' | t: { rank: me.rank }) : '' }}</span
                >
              </div>
            } @else if (canJoin(d)) {
              <button class="btn primary wfull" type="button" [disabled]="joining()" (click)="join(d)">
                {{ (joining() ? 'tourney.joining' : 'tourney.join') | t }}
              </button>
            } @else {
              <span class="s muted">{{ 'tourney.join_closed' | t }}</span>
            }
            @if (joinErr()) {
              <div class="alert">{{ joinErr() | mt }}</div>
            }

            <div class="between sect2">
              <span class="label">{{ 'tourney.leaderboard' | t }}</span>
              <span class="s muted">{{ 'tourney.lb_hint' | t }}</span>
            </div>
            @if (!d.leaderboard.length) {
              <p class="s muted">{{ 'tourney.no_scores' | t }}</p>
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
  /**
   * 三处报错信号（`error` / `dErr` / `joinErr`）存的是 **`Msg`（两态）而不是拼好的字符串** ——
   * 服务端原文走 raw 那一态，本地提示语（`common.load_failed` 那种）在模板上过 `| mt` 渲染期才求值。
   *
   * ⚠ 别改成存 `e.message`（构造那一刻的**冻结**文案）：那个串在切语言时不会重渲，
   * 屏幕上会留着旧语言的报错（本批的 `.message` → `.msg` 就是这个意思，见 `source-nails.spec.ts` ③）。
   */
  protected readonly error = signal<Msg>('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);

  private loadingId = '';

  protected readonly cur = signal<TournamentDetail | null>(null);
  protected readonly dLoading = signal(false);
  protected readonly dErr = signal<Msg>('');
  /**
   * 报名结果提示 —— 两态（服务端原文 / 词条键），见 `core/i18n/i18n.ts` 的 `Msg`。
   * ⚠ 成功那一句是**本地**文案且在异步回调里落值，存键、渲染期查表；存译好的串就冻在报名那一刻。
   */
  protected readonly note = signal<Msg>('');
  protected readonly joinErr = signal<Msg>('');
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

  /** 空态标题 —— 回的是**键**（模板上 `{{ emptyTitle() | t }}`），与 TABS 同一条规矩 */
  protected emptyTitle(): string {
    return {
      upcoming: 'tourney.empty_upcoming',
      active: 'tourney.empty_active',
      ended: 'tourney.empty_ended',
    }[this.status()];
  }

  /**
   * 报名费 0 = 免费。只做**展示**判定，不参与任何金额运算（金额一律字符串交给服务端）。
   *
   * 回 **`Msg`（两态）**：「免费」那一支是词条键，金额那一支是 `money()` 的**成品串** ——
   * 两态在模板上都过 `| mt`（键渲染期查表、串原样透出）。存 `t()` 的结果会让切语言后的
   * 「免费」冻在旧语言上（见 core/i18n/i18n.ts 的 `Msg`）。
   */
  protected feeText(t: Tournament): Msg {
    // 原先这里写 `Number(t.entry_fee) > 0` —— 金额列过数值转型，本批明令禁止；改走
    // moneyIsZero（同 money() 的字符串判据）。「免费」的取值分支原样保留。
    return moneyIsZero(t.entry_fee) ? { key: 'tourney.free' } : money(t.entry_fee);
  }

  protected playerText(t: Tournament): Msg {
    return t.max_players > 0
      ? { key: 'tourney.players_max', params: { current: t.player_count, max: t.max_players } }
      : { key: 'tourney.players', params: { count: t.player_count } };
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
        this.error.set(e.msg);
        this.loading.set(false);
        this.more.set(false);
      },
    });
  }

  /**
   * 打开详情。**不清 note()** —— `join()` 成功回调里是「先 `note.set('报名成功…')`（:364）
   * 紧接着 `this.open(t.id)`（:365）」，本方法若在此清 note，提示会在同一个 tick 里被当场擦掉。
   * （这条理由原先引的是已撤下的 coupons 页做旁证，那一页 2026-10-01 已删，改成本方法自己的调用点。）
   *
   * 在途详情请求的**代号** —— 与 `tickets.ts` / `announcements.ts` 的 `loadingId` 同一套判据。
   *
   * 不写这笔的现场（慢网必现）：点开一行 → 框里显示「加载中…」→ 用户点遮罩/关闭 →
   * 回包这才落地 → `cur.set(d)` 把**用户刚关掉的那条**又弹回来。四页同病，本页四页之一。
   *
   * 关框与换行都把它置空/改写 ⇒ 旧回包在 next/error 里比对不上就整条丢弃，不落任何信号。
   * 为什么不用「存 Subscription 再 unsubscribe」：本树另外两页（tickets/announcements）
   * 已经用代号这套，两套判据并存会让下一个改这几页的人先花时间分辨它们有什么区别。
   */
  protected open(id: string): void {
    this.loadingId = id;
    this.cur.set(null);
    this.dErr.set('');
    this.joinErr.set('');
    this.dLoading.set(true);
    this.api.tournamentDetail(id).subscribe({
      next: (d) => {
        if (this.loadingId !== id) return; // 关框/换行后落地的旧回包：丢弃
        this.cur.set(d);
        this.dLoading.set(false);
      },
      error: (e: ApiError) => {
        if (this.loadingId !== id) return;
        this.dErr.set(e.msg);
        this.dLoading.set(false);
      },
    });
  }

  /** 点列表行：清掉上一条赛事的提示与错误，再开 */
  protected openFresh(id: string): void {
    this.note.set('');
    this.open(id);
  }

  /**
   * 报名。**这条 POST 也会把框弹回来** —— 与 `open()` 那条详情请求同族，同一个 `loadingId` 判据：
   * 用户点「报名参赛」→ 请求还在路上 → 用户点「关闭」→ 报名回包这才落地 →
   * 原写法无条件 `this.open(t.id)`，把用户刚关掉的框又开出来，还带着「报名成功」。
   *
   * 但这里**两条状态必须无条件复位**，不能跟着判据一起被跳过：
   * `joining` 不在 `open()`/`close()` 里清，漏掉复位会让下次开框的按钮永久停在「报名中…」；
   * `load(1)` 刷的是列表（人数变了）不是弹框状态，与框开没开无关。
   */
  protected join(t: TournamentDetail): void {
    if (this.joining()) return;
    this.joining.set(true);
    this.note.set('');
    this.joinErr.set('');
    this.api.tournamentJoin(t.id).subscribe({
      next: () => {
        this.joining.set(false);
        this.load(1); // 人数变了，列表重拉（与框开没开无关）
        if (this.loadingId !== t.id) return; // 关框后落地：不重开、不摆提示
        this.note.set({ key: 'tournaments.joined' });
        this.open(t.id); // 重拉详情：my_entry 由服务端给，不在本地推算
      },
      error: (e: ApiError) => {
        this.joining.set(false);
        if (this.loadingId !== t.id) return;
        this.joinErr.set(e.msg);
      },
    });
  }

  protected close(): void {
    this.loadingId = ''; // 作废在途请求：否则慢回包一到，弹框会自己又弹回来
    this.cur.set(null);
    this.dErr.set('');
    this.joinErr.set('');
    this.dLoading.set(false);
  }
}
