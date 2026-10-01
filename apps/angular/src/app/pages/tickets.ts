/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import {
  Api,
  ApiError,
  TICKET_TYPES,
  TicketBrief,
  TicketDetail,
  TicketType,
  dt,
} from '../core/api.service';

/** 工单类型短码 → 中文（真值见服务端 TicketController 的 validator in: 白名单） */
const TYPE_LABEL: Record<string, string> = {
  deposit: '充值',
  withdraw: '提现',
  game: '游戏',
  account: '账号',
  other: '其它',
};

/** 工单状态短码 → 中文（服务端写 open / waiting / closed） */
const STATUS_LABEL: Record<string, string> = {
  open: '待处理',
  waiting: '已回复',
  closed: '已关闭',
};

/**
 * 客服工单 —— 列表 / 详情 / 新建 / 追加回复。
 *
 * 服务端契约要点（TicketController）：
 * - 列表项**不含正文**，正文与回复只在详情里；
 * - 详情与回复对非归属工单一律回 **404**（不是 403），所以「找不到」也包含「不是你的」；
 * - 只有 `closed` 的工单禁止回复，其余回复后状态被打成 `waiting`；
 * - type 只认 deposit/withdraw/game/account/other，其余 422。
 */
@Component({
  selector: 'app-tickets',
  template: `
    <div class="between sect">
      <h2>客服工单</h2>
      <button class="btn ghost" type="button" (click)="toggleNew()">
        {{ composing() ? '取消' : '新建工单' }}
      </button>
    </div>

    @if (composing()) {
      <div class="card stack">
        @if (formErr()) {
          <div class="alert">{{ formErr() }}</div>
        }
        <label class="field">
          <span>问题类型</span>
          <select class="input" [value]="fType()" (change)="pickType($event)">
            @for (t of types; track t) {
              <option [value]="t">{{ typeLabel(t) }}</option>
            }
          </select>
        </label>
        <label class="field">
          <span>标题</span>
          <input
            class="input"
            maxlength="200"
            placeholder="一句话描述问题"
            [value]="fSubject()"
            (input)="fSubject.set(val($event))"
          />
        </label>
        <label class="field">
          <span>详细说明</span>
          <textarea
            class="input"
            rows="6"
            maxlength="5000"
            placeholder="请附上订单号、时间等信息，便于定位"
            [value]="fContent()"
            (input)="fContent.set(val($event))"
          ></textarea>
        </label>
        <button class="btn primary wide" type="button" [disabled]="busy()" (click)="create()">
          {{ busy() ? '提交中…' : '提交工单' }}
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
          <strong>加载失败</strong>
          <span>{{ error() }}</span>
          <button class="btn" type="button" (click)="load(1)">重试</button>
        </div>
      } @else if (!items().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>暂无工单</strong>
          <span>充值、提现、账号等遇到问题都可以在这里提交</span>
          <button class="btn" type="button" (click)="toggleNew()">新建工单</button>
        </div>
      } @else {
        <div class="rows">
          @for (t of items(); track t.id) {
            <button class="row asbtn" type="button" (click)="open(t)">
              <div class="grow">
                <div class="t">{{ t.subject }}</div>
                <div class="s">
                  {{ typeLabel(t.type) }} · {{ dt(t.created_at) }}
                  @if (t.reply_count) {
                    · {{ t.reply_count }} 条回复
                  }
                </div>
              </div>
              <span class="badge" [class]="badgeCls(t.status)">{{ label(t.status) }}</span>
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
      <div class="modal" role="dialog" aria-modal="true" aria-label="工单详情">
        <header class="between">
          <b>{{ cur()?.subject || '工单' }}</b>
          <button class="btn ghost" type="button" (click)="close()">关闭</button>
        </header>
        <div class="modal-body">
          @if (detailLoading()) {
            <div class="state"><span class="spin"></span> 加载中…</div>
          } @else if (detailErr()) {
            <div class="alert">{{ detailErr() }}</div>
          } @else if (cur(); as d) {
            <div class="s muted">
              {{ typeLabel(d.type) }} · {{ dt(d.created_at) }} · {{ label(d.status) }}
            </div>
            <div class="body">{{ d.content }}</div>

            @for (r of d.replies; track r.id) {
              <div class="msg" [class.me]="!r.is_admin">
                <div class="who2">{{ r.is_admin ? '客服' : '我' }} · {{ dt(r.created_at) }}</div>
                <div class="body">{{ r.content }}</div>
              </div>
            }

            @if (replyErr()) {
              <div class="alert">{{ replyErr() }}</div>
            }

            @if (d.status === 'closed') {
              <div class="alert">该工单已关闭，如需继续沟通请新建一个工单。</div>
            } @else {
              <label class="field">
                <span>追加回复</span>
                <textarea
                  class="input"
                  rows="3"
                  maxlength="5000"
                  placeholder="补充说明…"
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
                {{ replyBusy() ? '发送中…' : '发送' }}
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
      .modal-body .body {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        font-size: 14px;
        line-height: 1.7;
      }
      .msg {
        border: 1px solid var(--stroke);
        border-radius: 12px;
        padding: 10px 12px;
        background: var(--panel);
      }
      .msg.me {
        border-color: rgba(124, 58, 237, 0.35);
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

  protected readonly types = TICKET_TYPES;
  protected readonly dt = dt;
  protected readonly items = signal<TicketBrief[]>([]);
  protected readonly loading = signal(true);
  protected readonly more = signal(false);
  protected readonly error = signal('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);

  protected readonly composing = signal(false);
  protected readonly fType = signal<TicketType>('other');
  protected readonly fSubject = signal('');
  protected readonly fContent = signal('');
  protected readonly formErr = signal('');
  protected readonly busy = signal(false);

  protected readonly cur = signal<TicketDetail | null>(null);
  protected readonly detailLoading = signal(false);
  protected readonly detailErr = signal('');
  protected readonly reply = signal('');
  protected readonly replyErr = signal('');
  protected readonly replyBusy = signal(false);

  constructor() {
    this.load(1);
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
        this.error.set(e.message);
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
    if (!subject) return this.formErr.set('请填写标题');
    if (subject.length > 200) return this.formErr.set('标题不能超过 200 字');
    if (!content) return this.formErr.set('请填写详细说明');
    if (content.length > 5000) return this.formErr.set('详细说明不能超过 5000 字');

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
        this.formErr.set(e.message);
      },
    });
  }

  protected open(t: TicketBrief): void {
    this.cur.set(null);
    this.detailErr.set('');
    this.reply.set('');
    this.replyErr.set('');
    this.detailLoading.set(true);
    this.api.ticketDetail(t.id).subscribe({
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
          next: (fresh) => this.cur.set(fresh),
          error: () => this.close(),
        });
        this.load(1);
      },
      error: (e: ApiError) => {
        this.replyBusy.set(false);
        this.replyErr.set(e.message);
      },
    });
  }
}
