/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Api, ApiError, ChatMessage, FriendUser, dt } from '../core/api.service';
import { Avatars } from '../core/avatar';
import { T, t } from '../core/i18n/i18n';

/**
 * 消息 —— 与某人的对话（`/chat/:hashid`，hashid 是**对方用户**的）。
 *
 * 三个坑（都在 `ChatController` 里，写错了不会报错、只会静默显示错东西）：
 *
 * **① `GET /chat/messages/{peer}` 调用即已读** —— 服务端在这一次请求里就把「对方发给我的未读」
 *    全置 1（`ChatController.php:98-100`）。所以**打开本页就完成了已读回执**：不要再调
 *    `/chat/read`（那个端点本树刻意没接），也不要摆「标记已读」按钮。`?page=2` 拉更早的消息
 *    同样会打这个 GET，同样会标已读 —— 那批本来就已读，无副作用。
 *
 * **② 同一次响应里，刚被标已读的那批 `is_read` 仍然是 0** —— 因为那句 UPDATE 排在拼 items
 *    **之后**。也就是说首屏拿到的已读状态**天生是旧快照**，要再进一次才是新值。
 *    所以本页**一个已读标记都不渲染**：唯一能拿到的快照会自己骗自己（把已读显示成未读）。
 *    会话列表每行的未读数来自 `conversations()`，那个是真值 —— 看完对话回列表要**刷新**。
 *
 * **③ 非好友发消息是 403**（'Only friends can send messages'）—— 原样透出，不吞成「发送失败」：
 *    用户得知道该先去加好友。发送失败时**草稿不清**，加完好继续发。
 *
 * 另外两条与「无 WS」配套：
 *  - 服务端**不回正文**（`send` 只回 id/created_at）⇒ 发完必须重拉 messages，
 *    客户端自己 push 一条就是第二真值源（id 与时间都是服务端生成的）；
 *  - 本树没有 WS 客户端 ⇒ 顶部如实写「新消息到达后刷新」。
 */
@Component({
  selector: 'app-chat-room',
  imports: [RouterLink, T],
  template: `
    <a class="back muted" routerLink="/chat">← {{ 'chat.back_to_list' | t }}</a>

    <div class="between sect">
      <h2>{{ peerName() }}</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load()">
        {{ 'common.refresh' | t }}
      </button>
    </div>
    @if (peer(); as p) {
      <p class="muted s user">@{{ p.username }}</p>
    }

    <p class="muted note">
      {{ 'chat.room_note_a' | t }}<strong>{{ 'chat.room_note_b' | t }}</strong
      >{{ 'chat.room_note_c' | t }}
    </p>

    <!-- 无 [formGroup] 的表单一律 (submit) + preventDefault：ngSubmit 只由 FormsModule 提供，本树不引 -->
    <form class="sbar" (submit)="send($event)" novalidate>
      <input
        class="input"
        type="text"
        name="content"
        placeholder="{{ 'chat.placeholder' | t }}"
        autocomplete="off"
        [value]="draft()"
        (input)="draft.set(val($event))"
      />
      <button class="btn primary" type="submit" [disabled]="sending() || !draft().trim()">
        {{ sending() ? ('common.sending' | t) : ('common.send' | t) }}
      </button>
    </form>

    @if (sendErr(); as m) {
      <div class="alert">{{ m }}</div>
    }
    @if (moreErr(); as m) {
      <div class="alert">{{ m }}</div>
    }

    <div class="card">
      @if (loading()) {
        <div class="rows">
          @for (i of [1, 2, 3]; track i) {
            <div class="row"><div class="skeleton sk-row"></div></div>
          }
        </div>
      } @else if (err()) {
        <div class="state">
          <strong>{{ 'common.load_failed' | t }}</strong>
          <span>{{ err() }}</span>
          <button class="btn" type="button" (click)="load()">{{ 'common.retry' | t }}</button>
        </div>
      } @else if (!msgs().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ 'chat.room_empty_title' | t }}</strong>
          <span>{{ 'chat.room_empty_hint' | t }}</span>
        </div>
      } @else {
        @if (page() < lastPage()) {
          <button class="btn ghost wide more" type="button" [disabled]="moreBusy()" (click)="more()">
            {{ moreBusy() ? ('common.loading' | t) : ('chat.load_earlier' | t) }}
          </button>
        }
        <div class="rows">
          @for (m of msgs(); track m.id) {
            <!-- 1:1 会话里"不是对方发的"就等于"我发的"（服务端这句查询只有这两个方向，
                 且 send 拒收自己发给自己）⇒ 不需要再存一份"我的 id"来比对 -->
            <div class="row msg" [class.mine]="m.from_user_id !== peerId()">
              <div class="bubble">{{ m.content }}</div>
              <span class="s when">{{ dt(m.created_at) }}</span>
            </div>
          }
        </div>
      }
    </div>
  `,
  styles: [
    `
      .sect {
        margin: 0 0 2px;
      }
      .sect h2 {
        margin: 0;
        font-size: 17px;
      }
      .user {
        margin: 0 0 10px;
      }
      .note {
        font-size: 12px;
        line-height: 1.6;
        margin: 0 0 14px;
      }
      .sbar {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
      }
      .sbar .input {
        flex: 1;
        min-width: 0;
      }
      .more {
        margin-bottom: 6px;
      }
      .msg {
        gap: 8px;
        align-items: flex-end;
      }
      .bubble {
        flex: 1;
        min-width: 0;
        padding: 9px 12px;
        border-radius: var(--r-md);
        border: 1px solid var(--line);
        background: var(--surface-2);
        font-size: 14px;
        line-height: 1.55;
        /* 正文里可能有超长串（URL 等），别把行撑破 */
        overflow-wrap: anywhere;
        white-space: pre-wrap;
      }
      .msg.mine .bubble {
        background: var(--grad);
        color: var(--primary-ink);
        border-color: transparent;
      }
      .when {
        flex: 0 0 auto;
      }
      .sk-row {
        height: 16px;
        width: 70%;
      }
    `,
  ],
})
export class ChatRoomPage {
  private readonly api = inject(Api);
  private readonly route = inject(ActivatedRoute);
  protected readonly av = inject(Avatars);

  protected readonly dt = dt;
  protected readonly msgs = signal<ChatMessage[]>([]);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly moreBusy = signal(false);
  protected readonly err = signal('');
  protected readonly moreErr = signal('');

  protected readonly draft = signal('');
  protected readonly sending = signal(false);
  protected readonly sendErr = signal('');

  /** 对方的名字/头像。`/chat/messages` 不回 peer 信息，只能从两个列表里认领（见 loadPeer） */
  protected readonly peer = signal<FriendUser | null>(null);

  /**
   * 对方 hashid。**从订阅里落值**，不在别处读 `route.snapshot`：同一条路由配置换参数时
   * 组件会被复用，订阅拿到的才是新的 —— 读 snapshot 会拿着上一个 id 继续发请求。
   */
  private readonly peerSig = signal('');

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed()).subscribe((pm) => {
      this.peerSig.set(pm.get('hashid') ?? '');
      this.load();
    });
  }

  protected peerId(): string {
    return this.peerSig();
  }

  protected peerName(): string {
    const p = this.peer();
    return p ? p.nickname || p.username : t('chat.fallback_peer');
  }

  protected val(ev: Event): string {
    return (ev.target as HTMLInputElement).value;
  }

  /** 拉第 1 页（最新 50 条，服务端已 reverse 成旧→新） */
  protected load(): void {
    this.loading.set(true);
    this.err.set('');
    this.moreErr.set('');
    this.api.chatMessages(this.peerId(), 1).subscribe({
      next: (r) => {
        this.msgs.set(r.items ?? []);
        this.page.set(r.page ?? 1);
        this.lastPage.set(r.last_page ?? 1);
        this.loading.set(false);
        this.toBottom();
      },
      error: (e: ApiError) => {
        this.err.set(e.message);
        this.loading.set(false);
      },
    });
    this.loadPeer();
  }

  /**
   * 认领对方的名字与头像。`/chat/messages` 只回消息，不回头像/昵称，而本页是从 URL 进来的
   * （可能直接刷新/深链），所以顺序查两处：
   *  1. `conversations()` —— 聊过的人一定在里面（从会话列表点进来的就是这条）；
   *  2. 没聊过（从好友列表点「发消息」进来）就查 `friends()` —— **只有好友能发**，所以能发的人
   *     必然在这两者之一。
   * 都查不到就不硬编：标题回落成「对话」，页面照常可用（历史会话被删好友后可能出现这种）。
   */
  private loadPeer(): void {
    const id = this.peerId();
    this.api.conversations().subscribe({
      next: (r) => {
        const hit = (r.list ?? []).find((c) => c.peer.id === id);
        if (hit) {
          this.peer.set(hit.peer);
          return;
        }
        this.api.friends().subscribe({
          next: (f) => this.peer.set((f.list ?? []).find((u) => u.id === id) ?? null),
          error: () => this.peer.set(null),
        });
      },
      // 认名字失败不影响聊天本身：静默回落，别把整页打成错误态
      error: () => this.peer.set(null),
    });
  }

  /** 拉更早的一页，**前插**（服务端每页是旧→新，页号越大越旧） */
  protected more(): void {
    if (this.moreBusy()) return;
    this.moreBusy.set(true);
    this.moreErr.set('');
    const p = this.page() + 1;
    this.api.chatMessages(this.peerId(), p).subscribe({
      next: (r) => {
        this.msgs.set([...(r.items ?? []), ...this.msgs()]);
        this.page.set(r.page ?? p);
        this.lastPage.set(r.last_page ?? this.lastPage());
        this.moreBusy.set(false);
      },
      error: (e: ApiError) => {
        // 单独一个错误位：拉更早失败不该把已有消息换成错误页
        this.moreErr.set(e.message);
        this.moreBusy.set(false);
      },
    });
  }

  /**
   * 发送。**草稿只在成功时清** —— 403「只有好友能发」时用户得先去加好友，回来还能接着发。
   * 内容长度不在这里判：1-5000 是服务端的规则，客户端再判一次就是第二个真值源（服务端 422
   * 的原文会照样透出）。
   */
  protected send(ev: Event): void {
    ev.preventDefault();
    const content = this.draft().trim();
    if (!content || this.sending()) return;
    this.sending.set(true);
    this.sendErr.set('');
    this.api.sendChat(this.peerId(), content).subscribe({
      next: () => {
        this.draft.set('');
        this.sending.set(false);
        // 回包没有正文 ⇒ 想看见刚发的那条只能重拉（顺带把对方的新消息也带上）
        this.load();
      },
      error: (e: ApiError) => {
        this.sendErr.set(e.message);
        this.sending.set(false);
      },
    });
  }

  /**
   * 首屏/发完消息滚到最新一条。
   *
   * 用 `scrollingElement.scrollTop` 赋值而不是 `window.scrollTo`：jsdom 没实现 scrollTo
   * （会往控制台吐 not-implemented），而本页的组件用例正是在 jsdom 里直接调方法的。
   * 放在 setTimeout 里是因为此刻 DOM 还没渲染出新消息的高度。
   */
  private toBottom(): void {
    setTimeout(() => {
      const el = document.scrollingElement ?? document.documentElement;
      el.scrollTop = el.scrollHeight;
    }, 0);
  }
}
