/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Api, ApiError, Notify, UserProfile, dt } from '../core/api.service';

@Component({
  selector: 'app-me',
  template: `
    <div class="card prof">
      <span class="av">
        @if (user(); as u) {
          @if (u.avatar) {
            <img [src]="u.avatar" [alt]="u.nickname || u.username" />
          } @else {
            {{ (u.nickname || u.username).charAt(0) }}
          }
        } @else {
          ·
        }
      </span>
      <div class="grow">
        @if (user(); as u) {
          <div class="who">{{ u.nickname || u.username }}</div>
          <div class="s muted">@{{ u.username }}</div>
          <div class="chips info">
            @if (u.email) {
              <span class="chip">{{ u.email }}</span>
            }
            @if (u.phone) {
              <span class="chip">{{ u.phone }}</span>
            }
            @if (u.country) {
              <span class="chip">{{ u.country }}</span>
            }
            @if (u.language) {
              <span class="chip">{{ u.language }}</span>
            }
            @if (u.created_at) {
              <span class="chip">注册于 {{ dt(u.created_at) }}</span>
            }
          </div>
        } @else if (profError()) {
          <div class="alert">{{ profError() }}</div>
        } @else {
          <div class="skeleton sk-line w60"></div>
        }
      </div>
      <button class="btn ghost out" type="button" (click)="signout()">退出登录</button>
    </div>

    <div class="between sect" id="notifications">
      <h2>消息</h2>
      <div class="wrap">
        @if (unread() > 0) {
          <span class="badge accent">{{ unread() }} 条未读</span>
          <button class="btn ghost" type="button" [disabled]="busy()" (click)="readAll()">
            全部已读
          </button>
        }
      </div>
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
          <strong>暂无消息</strong>
          <span>平台公告与账户通知会出现在这里</span>
        </div>
      } @else {
        <div class="rows">
          @for (n of items(); track n.id) {
            <div class="row" [class.unread]="!n.is_read">
              <span class="dotmark" [class.on]="!n.is_read"></span>
              <div class="grow">
                <div class="t">{{ n.title }}</div>
                <div class="s">{{ dt(n.created_at) }}{{ n.type ? ' · ' + n.type : '' }}</div>
                @if (n.content) {
                  <div class="body">{{ n.content }}</div>
                }
              </div>
              @if (!n.is_read) {
                <button class="btn ghost mark" type="button" (click)="read(n)">标记已读</button>
              }
            </div>
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
  `,
  styles: [
    `
      .prof {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
      }
      .av {
        width: 56px;
        height: 56px;
        border-radius: 18px;
        background: var(--grad);
        color: #0b0d17;
        font-size: 22px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: none;
        overflow: hidden;
      }
      .av img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .who {
        font-size: 17px;
        font-weight: 650;
      }
      .info {
        margin-top: 9px;
      }
      .out {
        margin-left: auto;
      }
      .sect {
        margin: 26px 0 14px;
      }
      .sect h2 {
        margin: 0;
        font-size: 17px;
      }
      .dotmark {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex: none;
        background: var(--stroke-2);
      }
      .dotmark.on {
        background: var(--grad);
        box-shadow: var(--glow);
      }
      .row .body {
        font-size: 13px;
        color: var(--muted);
        margin-top: 3px;
        overflow-wrap: anywhere;
      }
      .row.unread .t {
        color: #fff;
      }
      .mark {
        padding: 7px 14px;
        font-size: 12px;
      }
      .sk-line,
      .sk-row {
        height: 16px;
        border-radius: 8px;
      }
      .sk-row {
        width: 70%;
      }
      .w60 {
        width: 60%;
      }
      .more {
        display: flex;
        justify-content: center;
        padding-top: 14px;
      }
      @media (min-width: 768px) {
        .out {
          margin-left: 0;
        }
        .prof .grow {
          flex: 1;
        }
      }
    `,
  ],
})
export class MePage {
  private readonly api = inject(Api);
  private readonly router = inject(Router);

  protected readonly user = signal<UserProfile | null>(null);
  protected readonly profError = signal('');
  protected readonly items = signal<Notify[]>([]);
  protected readonly unread = signal(0);
  protected readonly loading = signal(true);
  protected readonly more = signal(false);
  protected readonly busy = signal(false);
  protected readonly error = signal('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly dt = dt;

  constructor() {
    this.api.profile().subscribe({
      next: (u) => this.user.set(u),
      error: (e: ApiError) => this.profError.set(e.message),
    });
    this.api.unreadCount().subscribe({
      next: (r) => this.unread.set(r.count),
      error: () => this.unread.set(0),
    });
    this.load(1);
  }

  private load(p: number): void {
    const first = p === 1;
    (first ? this.loading : this.more).set(true);
    this.error.set('');
    this.api.notifications(p, 20).subscribe({
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

  protected reload(): void {
    this.load(1);
  }

  protected loadMore(): void {
    this.load(this.page() + 1);
  }

  protected read(n: Notify): void {
    this.api.markRead(n.id).subscribe({
      next: () => {
        this.items.update((list) => list.map((x) => (x.id === n.id ? { ...x, is_read: true } : x)));
        this.unread.update((c) => Math.max(0, c - 1));
      },
      error: (e: ApiError) => this.error.set(e.message),
    });
  }

  protected readAll(): void {
    if (this.busy()) return;
    this.busy.set(true);
    this.api.markRead().subscribe({
      next: () => {
        this.items.update((list) => list.map((x) => ({ ...x, is_read: true })));
        this.unread.set(0);
        this.busy.set(false);
      },
      error: (e: ApiError) => {
        this.error.set(e.message);
        this.busy.set(false);
      },
    });
  }

  protected signout(): void {
    this.api.logout();
    void this.router.navigate(['/login']);
  }
}
