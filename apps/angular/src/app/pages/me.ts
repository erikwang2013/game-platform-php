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
          <!-- 吉祥物小骰（Dicey）：相对 public/，由 <base href> 解析到子路径 -->
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
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

    <div class="card danger" id="delete-account">
      <div class="dh">
        <h2>注销账号</h2>
        <span class="badge bad">不可撤销</span>
      </div>
      <p class="hint">
        注销后该账号无法再登录，个人资料将被匿名化。账号内余额需先自行提现清零，否则服务端会拒绝注销。
      </p>

      @if (delMsg()) {
        <div class="alert">{{ delMsg() }}</div>
      }

      @if (delOpen()) {
        <div class="fields">
          <label class="field">
            <span>当前密码</span>
            <input
              class="input"
              type="password"
              autocomplete="current-password"
              placeholder="请输入当前密码"
              [value]="delPw()"
              (input)="onDelPw($event)"
            />
          </label>
          <label class="field">
            <span>确认注销（输入 yes）</span>
            <input
              class="input mono"
              autocomplete="off"
              placeholder="yes"
              [value]="delYes()"
              (input)="onDelYes($event)"
            />
          </label>
        </div>
        <div class="acts">
          <button class="btn primary" type="button" [disabled]="delBusy()" (click)="submitDel()">
            {{ delBusy() ? '注销中…' : '确认注销' }}
          </button>
          <button class="btn ghost" type="button" [disabled]="delBusy()" (click)="cancelDel()">
            取消
          </button>
        </div>
      } @else {
        <button class="btn ghost red" type="button" (click)="openDel()">注销账号</button>
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
      .danger {
        margin-top: 22px;
        border-color: rgba(248, 113, 113, 0.28);
      }
      .dh {
        display: flex;
        align-items: center;
        gap: 10px;
      }
      .dh h2 {
        margin: 0;
        font-size: 17px;
      }
      .hint {
        margin: 8px 0 16px;
        font-size: 13px;
        color: var(--muted);
        line-height: 1.6;
      }
      .fields {
        display: flex;
        flex-direction: column;
        gap: 14px;
        max-width: 380px;
      }
      .acts {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
      }
      .red {
        border-color: rgba(248, 113, 113, 0.45);
        color: #fecaca;
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

  /* ---- 注销账号 ---- */

  protected readonly delOpen = signal(false);
  protected readonly delPw = signal('');
  protected readonly delYes = signal('');
  protected readonly delBusy = signal(false);
  protected readonly delMsg = signal('');

  protected onDelPw(ev: Event): void {
    this.delPw.set((ev.target as HTMLInputElement).value);
    this.delMsg.set('');
  }

  protected onDelYes(ev: Event): void {
    this.delYes.set((ev.target as HTMLInputElement).value);
    this.delMsg.set('');
  }

  protected openDel(): void {
    this.delMsg.set('');
    this.delOpen.set(true);
  }

  protected cancelDel(): void {
    this.delPw.set('');
    this.delYes.set('');
    this.delMsg.set('');
    this.delOpen.set(false);
  }

  protected submitDel(): void {
    if (this.delBusy()) return;
    this.delBusy.set(true);
    this.delMsg.set('');
    this.api.deleteAccount(this.delPw(), this.delYes()).subscribe({
      // 成功不以「请求发出去了」为准，必须回读确认真注销掉了
      next: () => this.confirmGone(),
      error: (e: ApiError) => {
        // 服务端拒绝原因原样透出（如「请先提现所有余额后再注销账号」），不吞成「操作失败」
        this.delBusy.set(false);
        this.delMsg.set(this.delHint(e));
      },
    });
  }

  /** 回读：注销后资料接口必须已取不到；仍读得到 ⇒ 没注销掉，如实报告而不是宣布成功 */
  private confirmGone(): void {
    this.api.accountGone().subscribe({
      next: (gone) => {
        this.delBusy.set(false);
        if (!gone) {
          this.delMsg.set('注销请求已提交，但账号资料仍可读取，请刷新后确认');
          return;
        }
        this.delPw.set('');
        this.delYes.set('');
        this.signout();
      },
      error: (e: ApiError) => {
        this.delBusy.set(false);
        this.delMsg.set(`注销结果无法确认：${e.message}`);
      },
    });
  }

  /** 服务端原文照实展示，仅在末尾补可操作的建议 */
  private delHint(e: ApiError): string {
    if (e.code === 401 || e.code === 403) {
      return `${e.message}（登录状态可能已失效，请重新登录后再试）`;
    }
    return e.message;
  }
}
