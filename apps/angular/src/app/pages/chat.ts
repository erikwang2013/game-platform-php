/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api, ApiError, Conversation, FriendUser, dt } from '../core/api.service';
import { Avatars } from '../core/avatar';
import { T } from '../core/i18n/i18n';

/**
 * 消息 —— 会话列表。
 *
 * 服务端契约里三件**必须**照做、做错了也不报错只会显示错东西的事：
 *  1. **没有会话表**：服务端把「我发出的」与「我收到的」两组合并、各取每组最大 id 再回表查最后一条
 *     ⇒ 「是好友但没聊过」的人**不在这里**（别拿好友列表去补行，那是第二真值源）；
 *     `last_message` 已被服务端 `mb_substr` 截到 100 字，是摘要不是全文。
 *  2. **打开对话即已读**：`GET /chat/messages/{peer}` 那次调用本身就是已读回执（服务端在那次请求里
 *     把对方发来的未读全置 1）⇒ 从这里点进去再回来，**未读数已经变了**，得刷新本页才看得到。
 *     `.note` 里那句「看完再回来也要刷新」就是为这条写的。
 *  3. **本树没有 WS 客户端**：服务端有 `ChatWebSocket` 进程在投递（`chat:delivery_queue`），
 *     但 C 端 angular 没接 ⇒ 新消息不会自己冒出来。顶部如实写「新消息到达后刷新」。
 */
@Component({
  selector: 'app-chat',
  imports: [RouterLink, T],
  template: `
    <div class="between sect">
      <h2>{{ 'chat.title' | t }}</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load()">
        {{ 'common.refresh' | t }}
      </button>
    </div>

    <p class="muted note">
      {{ 'chat.note_a' | t }}<strong>{{ 'chat.note_b' | t }}</strong
      >{{ 'chat.note_c' | t }}
    </p>

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
      } @else if (!list().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ 'chat.empty_title' | t }}</strong>
          <span>{{ 'chat.empty_hint' | t }}</span>
          <a class="btn" routerLink="/friends">{{ 'chat.go_friends' | t }}</a>
        </div>
      } @else {
        <div class="rows">
          @for (c of list(); track c.peer.id) {
            <a class="row" [routerLink]="['/chat', c.peer.id]">
              <span class="av" aria-hidden="true">
                @if (av.of(c.peer.avatar)(); as src) {
                  <img [src]="src" alt="" />
                } @else {
                  {{ name(c.peer).slice(0, 1) }}
                }
              </span>
              <div class="grow">
                <div class="t">{{ name(c.peer) }}</div>
                <div class="s last">{{ c.last_message }}</div>
              </div>
              <div class="meta">
                <span class="s">{{ dt(c.updated_at) }}</span>
                @if (c.unread_count) {
                  <span class="chip on">{{ c.unread_count }}</span>
                }
              </div>
            </a>
          }
        </div>
      }
    </div>
  `,
  styles: [
    `
      .sect {
        margin: 0 0 10px;
      }
      .sect h2 {
        margin: 0;
        font-size: 17px;
      }
      .note {
        font-size: 12px;
        line-height: 1.6;
        margin: 0 0 14px;
      }
      .av {
        flex: 0 0 auto;
        width: 40px;
        height: 40px;
        border-radius: 13px;
        background: var(--grad);
        color: var(--primary-ink);
        font-size: 16px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
      }
      .av img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      /* 摘要只占一行，长了省略号（服务端已截 100 字，这里再兜一次版式） */
      .last {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .meta {
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
      }
      .sk-row {
        height: 16px;
        width: 70%;
      }
    `,
  ],
})
export class ChatPage {
  private readonly api = inject(Api);
  protected readonly av = inject(Avatars);

  protected readonly dt = dt;
  protected readonly list = signal<Conversation[]>([]);
  protected readonly loading = signal(true);
  protected readonly err = signal('');

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.err.set('');
    this.api.conversations().subscribe({
      next: (r) => {
        this.list.set(r.list ?? []);
        this.loading.set(false);
      },
      error: (e: ApiError) => {
        this.err.set(e.message);
        this.loading.set(false);
      },
    });
  }

  protected name(u: FriendUser): string {
    return u.nickname || u.username;
  }
}
