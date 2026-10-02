/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ModalFocus } from '../components/modal-focus';
import { Mt, Msg, T } from '../core/i18n/i18n';
import {
  Api,
  ApiError,
  TICKET_TYPES,
  TicketBrief,
  TicketDetail,
  TicketType,
  dt,
} from '../core/api.service';

/**
 * 工单类型短码 → **键**（真值见服务端 TicketController 的 validator in: 白名单）。
 * 存键不存译文：模板上过 `| t`（`{{ typeLabel(…) | t }}`）—— 存 `t()` 的结果会冻在切语言那一刻。
 * 查不到的短码**原样透出**（服务端将来加类型不吞字）；`t()` 对认不出的键也原样返回 ⇒ 不用特判。
 */
const TYPE_LABEL: Record<string, string> = {
  deposit: 'tickets.type_deposit',
  withdraw: 'tickets.type_withdraw',
  game: 'tickets.type_game',
  account: 'tickets.type_account',
  other: 'tickets.type_other',
};

/**
 * 工单状态短码 → **键**（服务端写 open / waiting / closed）。
 *
 * ⚠ `waiting` 的中文本批**改正**了：它是**用户**回复之后置上的（`TicketController::reply:166`，
 * 那条回复写的是 `is_admin = 0`）⇒ 真相是「等客服回」，不是「客服已回」。原先本树写的是
 * 「已回复」，把方向说反了 —— 用户看到「已回复」会以为该自己等着，实际球在他这边。
 * 现在照 react 树同格的 `ticket.status_waiting` 取「待回复」。`tickets.spec.ts` 有一条钉子钉死方向。
 */
const STATUS_LABEL: Record<string, string> = {
  open: 'tickets.status_open',
  waiting: 'tickets.status_waiting',
  closed: 'tickets.status_closed',
};

/**
 * 客服工单 —— 列表 / 详情 / 新建 / 追加回复。
 *
 * 服务端契约要点（TicketController）：
 * - 列表项**不含正文**，正文与回复只在详情里；
 * - 详情与回复对非归属工单一律回 **404**（不是 403），所以「找不到」也包含「不是你的」；
 * - 只有 `closed` 的工单禁止回复，其余回复后状态被打成 `waiting`；
 * - type 只认 deposit/withdraw/game/account/other，其余 422。
 *
 * **URL 是弹框的唯一真值源**（`/tickets/:hashid` ↔ 打开对应工单），与公告页同一套。
 * ⚠ 工单详情对**非归属人**回 404（不是 403）⇒ 把链接发给别人，对方打开只会看到「找不到」，
 * 这是服务端的归属守卫在起作用，不是本页的 bug。
 */
@Component({
  selector: 'app-tickets',
  imports: [ModalFocus, T, Mt],
  template: `
    <div class="between sect">
      <h2>{{ 'tickets.title' | t }}</h2>
      <button class="btn ghost" type="button" (click)="toggleNew()">
        {{ (composing() ? 'common.cancel' : 'tickets.new') | t }}
      </button>
    </div>

    @if (composing()) {
      <div class="card stack">
        @if (formErr()) {
          <div class="alert">{{ formErr() | mt }}</div>
        }
        <label class="field">
          <span>{{ 'tickets.field_type' | t }}</span>
          <select class="input" [value]="fType()" (change)="pickType($event)">
            @for (opt of types; track opt) {
              <option [value]="opt">{{ typeLabel(opt) | t }}</option>
            }
          </select>
        </label>
        <label class="field">
          <span>{{ 'tickets.field_subject' | t }}</span>
          <input
            class="input"
            maxlength="200"
            [placeholder]="'tickets.subject_ph' | t"
            [value]="fSubject()"
            (input)="fSubject.set(val($event))"
          />
        </label>
        <label class="field">
          <span>{{ 'tickets.field_content' | t }}</span>
          <textarea
            class="input"
            rows="6"
            maxlength="5000"
            [placeholder]="'tickets.content_ph' | t"
            [value]="fContent()"
            (input)="fContent.set(val($event))"
          ></textarea>
        </label>
        <button class="btn primary wide" type="button" [disabled]="busy()" (click)="create()">
          {{ (busy() ? 'common.submitting' : 'tickets.submit') | t }}
        </button>
      </div>
    }

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
          <strong>{{ 'tickets.empty_title' | t }}</strong>
          <span>{{ 'tickets.empty_hint' | t }}</span>
          <button class="btn" type="button" (click)="toggleNew()">{{ 'tickets.new' | t }}</button>
        </div>
      } @else {
        <div class="rows">
          @for (row of items(); track row.id) {
            <button class="row asbtn" type="button" (click)="view(row.id)">
              <div class="grow">
                <div class="t">{{ row.subject }}</div>
                <div class="s">
                  {{ typeLabel(row.type) | t }} · {{ dt(row.created_at) }}
                  @if (row.reply_count) {
                    · {{ 'tickets.reply_count' | t: { count: row.reply_count } }}
                  }
                </div>
              </div>
              <span class="badge" [class]="badgeCls(row.status)">{{ label(row.status) | t }}</span>
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
      <div class="backdrop" (click)="back()"></div>
      <!-- uiModal：开框聚焦首个可聚焦元素 / Tab 圈在框内 / Esc 关框 / 关框把焦点还给打开者 -->
      <div
        class="modal"
        uiModal
        (dismiss)="back()"
        role="dialog"
        aria-modal="true"
        [attr.aria-label]="'tickets.detail_title' | t"
      >
        <header class="between">
          <b>{{ cur()?.subject || ('tickets.fallback_title' | t) }}</b>
          <button class="btn ghost" type="button" (click)="back()">{{ 'common.close' | t }}</button>
        </header>
        <div class="modal-body">
          @if (detailLoading()) {
            <div class="state"><span class="spin"></span> {{ 'common.loading' | t }}</div>
          } @else if (detailErr()) {
            <div class="alert">{{ detailErr() | mt }}</div>
          } @else if (cur(); as d) {
            <div class="s muted">
              {{ typeLabel(d.type) | t }} · {{ dt(d.created_at) }} · {{ label(d.status) | t }}
            </div>
            <div class="body">{{ d.content }}</div>

            @for (r of d.replies; track r.id) {
              <div class="msg" [class.me]="!r.is_admin">
                <div class="who2">
                  {{ (r.is_admin ? 'tickets.author_admin' : 'tickets.author_me') | t }} ·
                  {{ dt(r.created_at) }}
                </div>
                <div class="body">{{ r.content }}</div>
              </div>
            }

            @if (replyErr()) {
              <div class="alert">{{ replyErr() | mt }}</div>
            }

            @if (d.status === 'closed') {
              <div class="alert">{{ 'tickets.closed_note' | t }}</div>
            } @else {
              <label class="field">
                <span>{{ 'tickets.field_reply' | t }}</span>
                <textarea
                  class="input"
                  rows="3"
                  maxlength="5000"
                  [placeholder]="'tickets.reply_ph' | t"
                  [value]="reply()"
                  (input)="reply.set(val($event))"
                ></textarea>
              </label>
              <button
                class="btn primary wide"
                type="button"
                [disabled]="replyBusy() || !reply().trim()"
                (click)="send()"
              >
                {{ (replyBusy() ? 'common.sending' : 'common.send') | t }}
              </button>
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
      .card.stack {
        margin-bottom: 14px;
      }
      .input[type='textarea'],
      textarea.input {
        resize: vertical;
        font: inherit;
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
      .modal-body .body {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        font-size: 14px;
        line-height: 1.7;
      }
      .msg {
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        padding: 10px 12px;
        background: var(--surface-2);
      }
      /* 自己的发言靠左侧品牌色条区分（不是换底色 —— 换底色在亮色皮肤下会糊成一片） */
      .msg.me {
        border-left: 3px solid var(--primary);
      }
      .who2 {
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 4px;
      }
    `,
  ],
})
export class TicketsPage {
  private readonly api = inject(Api);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly types = TICKET_TYPES;
  protected readonly dt = dt;
  protected readonly items = signal<TicketBrief[]>([]);
  protected readonly loading = signal(true);
  protected readonly more = signal(false);
  /** 两态（服务端原文 / 词条键）—— 与 `formErr` 同规：读的地方一律过 `| mt`，不存 `t()` 的结果 */
  protected readonly error = signal<Msg>('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);

  protected readonly composing = signal(false);
  protected readonly fType = signal<TicketType>('other');
  protected readonly fSubject = signal('');
  protected readonly fContent = signal('');
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg`；同信号的每个写入点都走这两态 */
  protected readonly formErr = signal<Msg>('');
  protected readonly busy = signal(false);

  protected readonly cur = signal<TicketDetail | null>(null);
  protected readonly detailLoading = signal(false);
  protected readonly detailErr = signal<Msg>('');
  protected readonly reply = signal('');
  protected readonly replyErr = signal<Msg>('');
  protected readonly replyBusy = signal(false);

  constructor() {
    this.load(1);
    // 深链：/tickets/<hashid> 打开对应弹框，返回列表 URL 时收起（与公告页同一套）
    this.route.paramMap.pipe(takeUntilDestroyed()).subscribe((p) => {
      const id = p.get('hashid');
      if (id) this.open(id);
      else this.clear();
    });
  }

  protected typeLabel(t?: string): string {
    return t ? (TYPE_LABEL[t] ?? t) : '—';
  }

  protected label(s: string): string {
    return STATUS_LABEL[s] ?? s;
  }

  protected badgeCls(s: string): string {
    return s === 'closed' ? '' : s === 'waiting' ? 'on' : 'warn';
  }

  protected val(ev: Event): string {
    return (ev.target as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement).value;
  }

  /** select 的 value 是 string，收窄回服务端白名单类型（选项就来自同一份常量） */
  protected pickType(ev: Event): void {
    this.fType.set(this.val(ev) as TicketType);
  }

  protected toggleNew(): void {
    this.formErr.set('');
    this.composing.update((v) => !v);
  }

  protected load(p: number): void {
    const first = p === 1;
    (first ? this.loading : this.more).set(true);
    this.error.set('');
    this.api.tickets(p, 20).subscribe({
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

  protected create(): void {
    if (this.busy()) return;
    const subject = this.fSubject().trim();
    const content = this.fContent().trim();
    // 本地先挡，避免明知 422 还发请求；服务端 max:200 / max:5000 仍会二次校验
    if (!subject) return this.formErr.set({ key: 'tickets.err_subject_required' });
    if (subject.length > 200) return this.formErr.set({ key: 'tickets.err_subject_too_long' });
    if (!content) return this.formErr.set({ key: 'tickets.err_content_required' });
    if (content.length > 5000) return this.formErr.set({ key: 'tickets.err_content_too_long' });

    this.busy.set(true);
    this.formErr.set('');
    this.api.createTicket({ type: this.fType(), subject, content }).subscribe({
      next: () => {
        this.busy.set(false);
        this.composing.set(false);
        this.fSubject.set('');
        this.fContent.set('');
        this.load(1);
      },
      error: (e: ApiError) => {
        this.busy.set(false);
        this.formErr.set(e.msg);
      },
    });
  }

  /** 点列表行：只改 URL，弹框由构造器里的 paramMap 订阅打开（URL 是唯一真值源） */
  protected view(id: string): void {
    void this.router.navigate(['/tickets', id]);
  }

  /** 关闭 / 点遮罩：同样改 URL，让地址栏与界面一起回到列表 */
  protected back(): void {
    void this.router.navigate(['/tickets']);
  }

  /** 正在拉的那条：弹框后面还能点别的行，回包要按它对号入座 */
  private loadingId = '';

  private open(id: string): void {
    if (this.cur()?.id === id) return;
    this.loadingId = id;
    this.cur.set(null);
    this.detailErr.set('');
    this.reply.set('');
    this.replyErr.set('');
    this.detailLoading.set(true);
    this.api.ticketDetail(id).subscribe({
      next: (d) => {
        if (this.loadingId !== id) return;
        this.cur.set(d);
        this.detailLoading.set(false);
      },
      error: (e: ApiError) => {
        if (this.loadingId !== id) return;
        this.detailErr.set(e.msg);
        this.detailLoading.set(false);
      },
    });
  }

  private clear(): void {
    // 同时作废在途请求：否则关掉之后回包一到，弹框会自己又弹回来
    this.loadingId = '';
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(false);
  }

  protected send(): void {
    const d = this.cur();
    const text = this.reply().trim();
    if (!d || this.replyBusy() || !text) return;
    this.replyBusy.set(true);
    this.replyErr.set('');
    this.api.replyTicket(d.id, text).subscribe({
      next: () => {
        this.replyBusy.set(false);
        this.reply.set('');
        // 回读：服务端会把状态打成 waiting，本地猜状态会漂
        this.api.ticketDetail(d.id).subscribe({
          // 与 open() 同一套代号判据：用户在这条回读落地前关框/换了别的单，
          // 就不能再由它 set/back —— 否则 (a) 框自己弹回来、(b) 而且此后点「关闭」
          // 与当前 URL 同址，router 的 onSameUrlNavigation='ignore' 不再发 paramMap，
          // clear() 永不执行 ⇒ 框卡住关不掉。
          next: (fresh) => {
            if (this.loadingId !== d.id) return;
            this.cur.set(fresh);
          },
          error: () => {
            if (this.loadingId !== d.id) return;
            this.back();
          },
        });
        this.load(1);
      },
      error: (e: ApiError) => {
        this.replyBusy.set(false);
        this.replyErr.set(e.msg);
      },
    });
  }
}
