/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { HttpClient } from '@angular/common/http';
import { Component, OnDestroy, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Api, ApiError, Notify, UserProfile, dt } from '../core/api.service';
import { Mt, Msg, T } from '../core/i18n/i18n';
import { fileBlob } from '../core/upload';
import { MeTiles } from './me-tiles';
import { MeExport } from './me-export';
import { MeNick } from './me-nick';

@Component({
  selector: 'app-me',
  imports: [MeTiles, MeExport, MeNick, Mt, T],
  templateUrl: './me.html',
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
        border-radius: var(--r-lg);
        background: var(--grad);
        color: var(--primary-ink);
        font-size: 22px;
        font-weight: 800;
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
      /* 未读用品牌紫点出，不整行反色。⚠ 别写死 #fff：亮色皮肤下白字白底看不见 */
      .row.unread .t {
        color: var(--primary-2);
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
        border-color: color-mix(in srgb, var(--neg) 32%, var(--line));
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
        border-color: var(--neg);
        color: var(--neg);
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
export class MePage implements OnDestroy {
  private readonly api = inject(Api);
  private readonly router = inject(Router);
  private readonly http = inject(HttpClient);

  protected readonly user = signal<UserProfile | null>(null);
  /**
   * 头像的真实可显示地址：库里的值是 `/api/v1/user/file/{savedPath}`，该端点**只认 Bearer 头**
   * 而 `<img src>` 带不上 ⇒ 直接绑 avatar 是碎图；故带 token 取 blob 转 objectURL，
   * 取不到退回首字母兜底。第三方 OAuth 的绝对地址（http…）不用走这一步。
   */
  protected readonly avatarSrc = signal('');
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
    this.loadProfile();
    this.api.unreadCount().subscribe({
      next: (r) => this.unread.set(r.count),
      error: () => this.unread.set(0),
    });
    this.load(1);
  }

  ngOnDestroy(): void {
    const u = this.avatarSrc();
    if (u.startsWith('blob:')) URL.revokeObjectURL(u);
  }

  /** 资料读取：首屏与「改完昵称」（app-me-nick 的 saved）共用同一条回读路径 */
  protected loadProfile(): void {
    this.api.profile().subscribe({
      next: (u) => {
        this.user.set(u);
        this.loadAvatar(u.avatar);
      },
      error: (e: ApiError) => this.profError.set(e.message),
    });
  }

  /** 见 avatarSrc 的注释：相对地址必须带 token 取字节，绝对地址直接用 */
  private loadAvatar(stored: string): void {
    if (!stored) return;
    if (/^https?:/i.test(stored)) {
      this.avatarSrc.set(stored);
      return;
    }
    fileBlob(this.http, stored).subscribe({
      next: (b) => {
        const old = this.avatarSrc();
        if (old.startsWith('blob:')) URL.revokeObjectURL(old);
        this.avatarSrc.set(URL.createObjectURL(b));
      },
      error: () => this.avatarSrc.set(''),
    });
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
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg`：异步拉回的文案不能在 set 时定稿 */
  protected readonly delMsg = signal<Msg>('');

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
          this.delMsg.set({ key: 'me.del_pending' });
          return;
        }
        this.delPw.set('');
        this.delYes.set('');
        this.signout();
      },
      error: (e: ApiError) => {
        this.delBusy.set(false);
        this.delMsg.set({ key: 'me.del_unknown', params: { msg: e.message } });
      },
    });
  }

  /**
   * 服务端原文照实展示，仅在末尾补可操作的建议。
   *
   * ⚠ 返回**两态 `Msg` 而不是拼好的句子**：本方法在异步回调（`error:`）里被调用，
   * 返回字符串就等于在那一刻把译文定稿 —— 切语言后这一行不跟着变。同 `withdraw.ts` 的 `hint()`。
   */
  private delHint(e: ApiError): Msg {
    if (e.code === 401 || e.code === 403) {
      return { key: 'me.del_hint_relogin', params: { msg: e.message } };
    }
    return e.message;
  }
}
