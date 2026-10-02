/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import {
  ACTIVITY_TYPE_LABEL,
  Activity,
  ActivityProgress,
  ActivityReward,
  Api,
  ApiError,
  dt,
  money,
} from '../core/api.service';
import { Mt, Msg } from '../core/i18n/i18n';

interface Row {
  a: Activity;
  p: ActivityProgress;
}

/** 从没参与过的活动不会出现在 /progress 里，补一条零值而不是显示 undefined */
const NONE: ActivityProgress = { activity_id: '', current: 0, target: 0, status: 'progressing' };

/**
 * 运营活动 —— 列表 + 今日进度 + 签到领奖。
 *
 * 只发**两个**请求（list + progress）后在客户端按 activity_id 合并，不逐条调
 * `/activities/{hashid}`：那条 detail 回的 participation 与 progress 同源，逐个拉就是 N+1。
 *
 * 奖励是**真钱**：服务端 checkin() 在同一个事务里写 reward_log + WalletService::mutate。
 * 所以签完必须重新拉 progress（下面的 refreshProgress），**绝不在前端自己累加余额**。
 * 幂等由服务端唯一键保证，重复点返回 `already` 而不是重复发奖。
 *
 * `config` 字段刻意不渲染：它是按 type 各自定义的 JSON schema（奖励挂在 rewards 或 tasks 上），
 * 猜字段名等于编造。目标值一律取 progress 里服务端快照的 `target`。
 *
 * ⚠ **签到按钮只给「`game_id=0` 的 `signin`」摆**，其余三类一律摆原因、不摆按钮。
 * 三条都是读服务端码得到的，不是口味：
 *  1. `daily_task` 点一下会**白拿奖并锁死当天进度**。`ActivityService::checkin` 收的
 *     `canJoin` 与 type 无关，且 `$ctx['event']` 是空串（ActivityService.php:154），
 *     而 `DailyTaskHandler::canJoin` **完全不看 event** ⇒ game_id=0 的 daily_task 必然放行；
 *     建行时 `target` 硬编码为 1（:175）、`current += 1`（:184）⇒ 一次点击即达标发奖。
 *     更重的是那条 participation 被置 REWARDED，而 `progress()` 对 REWARDED 早退
 *     （:124「本周期已发奖，不再累加」）⇒ **当天真实的 deposit.completed 再也累加不进去**。
 *     这是服务端的设计缺口（已上报，未改后端）。本页**不利用它**，也不摆这个按钮。
 *  2. 绑了具体游戏的 `signin`：`SignInHandler::canJoin:24` 要求
 *     `activity.game_id === 0 || ctx['game_id'] === activity.game_id`，而 checkin 的
 *     `ctx['game_id']` **恒为 0** ⇒ `game_id != 0` 的活动必抛 `Activity not available`（400）。
 *  3. `invite`：`InviteHandler::canJoin:28-29` 还要求 `ctx['activity_id'] > 0` 且等于活动 id，
 *     而 checkin 的 ctx 里**根本没有 `activity_id` 这个键** ⇒ 必然 400。
 *     （它的进度本来就该由注册事件写，见 `ShareLink::bindConversion`。）
 */
@Component({
  selector: 'app-activities',
  imports: [RouterLink, Mt],
  template: `
    <div class="between sect">
      <h2>运营活动</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load()">刷新</button>
    </div>

    @if (reward(); as list) {
      <div class="alert ok">已发放：{{ rewardText(list) }}（可在钱包流水中查看）</div>
    }
    @if (note()) {
      <div class="alert">{{ note() | mt }}</div>
    }

    @if (loading()) {
      <div class="card stack">
        @for (i of [1, 2, 3]; track i) {
          <div class="skeleton sk-line"></div>
        }
      </div>
    } @else if (err()) {
      <div class="card state">
        <strong>加载失败</strong>
        <span>{{ err() }}</span>
        <button class="btn" type="button" (click)="load()">重试</button>
      </div>
    } @else if (!rows().length) {
      <div class="card state">
        <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
        <strong>暂无可参与的活动</strong>
        <span>平台还没投放活动，或当前账号不在灰度范围内</span>
      </div>
    } @else {
      @for (r of rows(); track r.a.id) {
        <div class="card stack act">
          <div class="between">
            <div class="grow">
              <div class="t">{{ r.a.name }}</div>
              <div class="s muted">
                {{ typeLabel(r.a.type) }}
                @if (r.a.end_at) {
                  · 截止 {{ dt(r.a.end_at) }}
                }
                @if (gameLink(r.a); as gid) {
                  · <a [routerLink]="['/game', gid]">去玩对应游戏</a>
                }
              </div>
            </div>
            <span class="badge {{ tone(r.p) }}">{{ statusLabel(r.p) }}</span>
          </div>

          <div class="bar"><i [style.width.%]="pct(r.p)"></i></div>
          <div class="between">
            <span class="muted small">{{ progressText(r.p) }}</span>
            <!-- 不可签到的三类**摆原因不摆按钮**：摆一个必然 400 的按钮，或一个点一下白拿奖的按钮，
                 都比没有按钮更糟。判据见文件头注释，全部来自服务端读码。 -->
            @if (blockReason(r.a); as reason) {
              <span class="muted small">{{ reason }}</span>
            } @else {
              <button
                class="btn primary"
                type="button"
                [disabled]="busyId() === r.a.id || r.p.status === 'rewarded'"
                (click)="checkin(r.a)"
              >
                {{ busyId() === r.a.id ? '处理中…' : actionLabel(r) }}
              </button>
            }
          </div>

          @if (rowErr() && busyId() === null && lastId() === r.a.id) {
            <div class="alert">{{ rowErr() }}</div>
          }
        </div>
      }
    }
  `,
  styles: [
    `
      .act {
        margin-bottom: 12px;
      }
      .t {
        font-weight: 600;
      }
      .s {
        font-size: 12px;
        margin-top: 2px;
      }
      .small {
        font-size: 12px;
      }
      .grow {
        min-width: 0;
      }
      .sk-line {
        height: 16px;
      }
      /* 活动进度：金→紫，跟"攒代币"这件事同色系 */
      .bar {
        height: 8px;
        border-radius: var(--r-full);
        background: var(--surface-3);
        overflow: hidden;
      }
      .bar i {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--gold), var(--primary));
        transition: width var(--t) var(--ease);
      }
    `,
  ],
})
export class ActivitiesPage {
  private readonly api = inject(Api);

  protected readonly loading = signal(true);
  protected readonly err = signal('');
  private readonly list = signal<Activity[]>([]);
  private readonly prog = signal<Map<string, ActivityProgress>>(new Map());

  protected readonly busyId = signal<string | null>(null);
  protected readonly lastId = signal('');
  protected readonly rowErr = signal('');
  /** 签到结果提示 —— 两态（键 / 服务端原文），见 `core/i18n/i18n.ts` 的 `Msg`。两句都是本地文案且在异步回调里落值 */
  protected readonly note = signal<Msg>('');
  protected readonly reward = signal<ActivityReward[] | null>(null);

  protected readonly money = money;
  protected readonly dt = dt;

  /** 活动原序（服务端按 id desc），进度按 activity_id 挂上去 */
  protected readonly rows = computed<Row[]>(() => {
    const m = this.prog();
    return this.list().map((a) => ({ a, p: m.get(a.id) ?? NONE }));
  });

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.err.set('');
    this.note.set('');
    this.reward.set(null);
    this.rowErr.set('');
    forkJoin({
      list: this.api.activities(),
      prog: this.api.activityProgress(),
    }).subscribe({
      next: ({ list, prog }) => {
        this.list.set(list.list ?? []);
        this.setProg(prog.list ?? []);
        this.loading.set(false);
      },
      error: (e: ApiError) => {
        this.err.set(e.message);
        this.loading.set(false);
      },
    });
  }

  /** 签到后只重拉进度：活动定义没变，不必重拉 list */
  private refreshProgress(): void {
    this.api.activityProgress().subscribe({
      next: (r) => this.setProg(r.list ?? []),
      // 进度刷新失败不清空已有展示，下一次手动刷新会补上
      error: () => undefined,
    });
  }

  private setProg(items: ActivityProgress[]): void {
    this.prog.set(new Map(items.map((p) => [p.activity_id, p])));
  }

  protected checkin(a: Activity): void {
    if (this.busyId()) return;
    this.busyId.set(a.id);
    this.lastId.set(a.id);
    this.rowErr.set('');
    this.note.set('');
    this.reward.set(null);
    this.api.activityCheckin(a.id).subscribe({
      next: (r) => {
        this.busyId.set(null);
        if (r.status === 'rewarded') this.reward.set(r.reward ?? []);
        else if (r.status === 'already') this.note.set({ key: 'activities.already' });
        else this.note.set({ key: 'activities.not_yet' });
        this.refreshProgress();
      },
      error: (e: ApiError) => {
        this.busyId.set(null);
        this.rowErr.set(e.message);
      },
    });
  }

  protected typeLabel(t: string): string {
    return ACTIVITY_TYPE_LABEL[t] ?? t;
  }

  /** 奖励条目里的 type 是**币种**，不是流水类型 */
  protected rewardText(list: ActivityReward[]): string {
    if (!list.length) return '已达标（本次没有可发放的奖励）';
    return list.map((r) => `${this.coinLabel(r.type)} ${money(r.amount)}`).join('、');
  }

  private coinLabel(t: string): string {
    return t === 'platform_coin' ? '平台币' : t === 'game_coin' ? '游戏币' : t;
  }

  /** game_id 为 0/空 = 全平台，没有可跳转的游戏页 */
  protected gameLink(a: Activity): string {
    const g = a.game_id;
    return g && String(g) !== '0' ? String(g) : '';
  }

  /**
   * 能不能签到。空串 = 摆按钮；否则是**不可签到的原因**（不是错误提示）。
   * 三类判据见文件头注释，全是「点了服务端必拒」或「点了能白拿」。
   * 「绑了游戏」这一条直接复用 `gameLink()` 的判据（同一个谓词，不再写第二遍）。
   */
  protected blockReason(a: Activity): string {
    if (a.type !== 'signin') {
      return a.type === 'invite' ? '按好友注册自动累计' : '按任务条件自动累计';
    }
    return this.gameLink(a) ? '请在对应游戏内完成' : '';
  }

  protected pct(p: ActivityProgress): number {
    if (!p.target || p.target <= 0) return 0;
    return Math.min(100, Math.round((p.current / p.target) * 100));
  }

  protected progressText(p: ActivityProgress): string {
    if (p.status === 'rewarded') return '今日已完成';
    if (!p.target || p.target <= 0) return '今日还没开始';
    return `今日进度 ${p.current} / ${p.target}`;
  }

  protected statusLabel(p: ActivityProgress): string {
    if (p.status === 'rewarded') return '已领取';
    if (p.status === 'completed') return '已达标';
    return !p.target || p.target <= 0 ? '未开始' : '进行中';
  }

  protected tone(p: ActivityProgress): string {
    if (p.status === 'rewarded') return 'on';
    if (p.status === 'completed') return 'warn';
    return '';
  }

  /** 只有 `game_id=0` 的 signin 才走得到这里（其余由 blockReason 拦下）⇒ 不再分「签到/领取」 */
  protected actionLabel(r: Row): string {
    return r.p.status === 'rewarded' ? '今日已领' : '签到';
  }
}
