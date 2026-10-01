/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Api, ApiError, ShareCreated } from '../core/api.service';

/**
 * 邀请好友（分享短码）。两个端点在这棵树里**都有真实消费者**，不是摆设：
 *
 *  1. `POST /shares`（登录态）生成 8 位短码 —— 本页做这一步；
 *  2. 被邀请人打开邀请链接 → **登录页**（`/login?code=xxx`）作为落地页调
 *     `POST /shares/visit`（匿名公开路由）上报点击 ⇒ `clicks` 原子自增；
 *  3. 被邀请人用该码注册 → `AuthController::register` 收 `share_code` →
 *     `ShareLink::bindConversion` ⇒ `conversions` 自增，且若短码带了 `activity_id`，
 *     还会写一条邀请活动的 `user.registered` 进度（可能带真钱奖励）。
 *     见 login.ts 的 `?code=` 入口与 `Api.register` 的 `share_code` 参数。
 *
 * ⚠ 别与已撤下的 `/referral`（推荐码）混为一谈：那是**另一套码**，且整套端点因为
 * 无 bootstrap 写入路径已于 2026-10-01 一并撤除（见 api.domains.ts 的墓碑注释）。
 * 本页的短码是**注册前**用的落地链接（服务端 `ShareLink`，8 位随机，可反复生成），
 * 与推荐码互不认账 —— 这也是当初没有把它做成「推荐页上一个按钮」的原因。
 *
 * ⚠ 两个**后端缺口**，本页按现状如实呈现、不假装有：
 *  - `clicks`/`conversions` **没有任何读端点**（ShareController 只有 create/visit），
 *    也没有「我的短码列表」⇒ 本页展示不了邀请战绩，也**不缓存**短码（缓存＝第二真值源，
 *    刷新就没了、且与服务端可能不一致）。每点一次「生成」多一个码，旧码仍有效但查不回来。
 *  - `expires_at` 在 `create()` 里从不赋值、列默认 NULL ⇒ **实际永不过期**，回包恒 null。
 *    所以本页不显示有效期，也不写"长期有效"这种没依据的承诺。
 */
@Component({
  selector: 'app-invite',
  template: `
    <div class="sect"><h2>邀请好友</h2></div>

    <div class="card stack">
      <p class="hint">
        生成一个邀请码发给朋友。对方打开链接、用它注册之后，这次邀请才会计入转化。
      </p>

      @if (!code()) {
        <button class="btn primary wide" type="button" [disabled]="busy()" (click)="generate()">
          {{ busy() ? '生成中…' : '生成邀请码' }}
        </button>
      } @else {
        <span class="label">邀请码</span>
        <div class="code-row">
          <code class="code">{{ code() }}</code>
          <button class="btn ghost" type="button" (click)="copy(code()!, 'code')">
            {{ copied() ? '已复制' : '复制码' }}
          </button>
        </div>
        <span class="label">邀请链接</span>
        <div class="code-row">
          <code class="link">{{ link() }}</code>
          <button class="btn ghost" type="button" (click)="copy(link(), 'link')">
            {{ linkCopied() ? '已复制' : '复制链接' }}
          </button>
        </div>
        <p class="hint">
          把链接发给朋友。对方打开后会自动跳到注册页并填好这个码，注册成功即完成一次转化。
        </p>
        <button class="btn wide" type="button" [disabled]="busy()" (click)="generate()">
          {{ busy() ? '生成中…' : '再生成一个' }}
        </button>
      }

      @if (err()) {
        <div class="alert">{{ err() }}</div>
      }
    </div>
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
        display: flex;
        flex-direction: column;
        gap: 10px;
      }
      .hint {
        margin: 0;
        font-size: 13px;
        color: var(--muted);
        line-height: 1.6;
      }
      .code-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
      }
      .code {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.14em;
        padding: 8px 14px;
        border-radius: 10px;
        background: var(--panel);
        border: 1px solid var(--stroke);
      }
      .link {
        flex: 1 1 220px;
        min-width: 0;
        font-size: 12px;
        padding: 8px 10px;
        border-radius: 10px;
        background: var(--panel);
        border: 1px solid var(--stroke);
        overflow-wrap: anywhere;
      }
      .wide {
        width: 100%;
      }
    `,
  ],
})
export class InvitePage {
  private readonly api = inject(Api);

  protected readonly code = signal<string | null>(null);
  protected readonly busy = signal(false);
  protected readonly err = signal('');
  protected readonly copied = signal(false);
  protected readonly linkCopied = signal(false);

  /**
   * 链接**不退回服务端查**：短码就是全部所需信息，挂载点用本树自己的 `location.origin`
   * ＋ baseHref。用 `document.baseURI` 而不是写死 `/` —— 子路径部署（`--base=/app/`）也拼得对。
   */
  protected link(): string {
    return this.code() ? `${new URL('login', document.baseURI).href}?code=${this.code()}` : '';
  }

  protected generate(): void {
    if (this.busy()) return;
    this.busy.set(true);
    this.err.set('');
    this.api.shareCreate().subscribe({
      next: (r: ShareCreated) => {
        this.busy.set(false);
        this.code.set(r.short_code);
        this.copied.set(false);
        this.linkCopied.set(false);
      },
      error: (e: ApiError) => {
        this.busy.set(false);
        this.err.set(e.message);
      },
    });
  }

  /** 剪贴板在非安全上下文（http 局域网）不可用 ⇒ 失败不报错，码与链接都在屏幕上可手抄 */
  protected copy(text: string, flag: 'code' | 'link'): void {
    const which = flag === 'code' ? this.copied : this.linkCopied;
    const done = (): void => {
      which.set(true);
      setTimeout(() => which.set(false), 1600);
    };
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(text).then(done, () => undefined);
      return;
    }
    done();
  }
}
