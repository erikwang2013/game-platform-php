/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api, ApiError } from '../core/api.service';
import { Mt, Msg, T } from '../core/i18n/i18n';

/**
 * 账号安全 —— 两步验证（2FA）的自助开关。
 *
 * 为什么这一页现在才有意义：服务端 `verify()` 的 validator 原先是 `size:6`，
 * 把备份码分支整段挡成了死代码 —— 用户在这一页开启 2FA、拿到 8 个备份码，
 * 然后**一个都兑不了**，丢验证器即锁死账号。服务端已改成 `between:6,10`，
 * 这条链路才闭合。客户端这边曾同步写着 `maxlength=6`（同一缺陷的镜像），一并放宽到 10。
 *
 * 两个输入框**故意不等宽**，别按对称去「统一」：
 *  - 启用 / 关闭：服务端 enable()、disable() 的 validator 都是 `size:6`
 *    （disable 只走 verifyTOTP，不查备份码表）⇒ 这里只收 6 位 TOTP；
 *  - 登录第二步 verify() 才是 `between:6,10`（6=TOTP、10=备份码）⇒ 在 login.ts。
 *
 * `qr_url` 是 `otpauth://` 协议串，**不是图片**，直接当 <img src> 会是个破图。
 * 本页不引二维码库：secret 按 4 位分组显示（可手抄），otpauth 串放进折叠区（可粘贴给 App）。
 */
@Component({
  selector: 'app-security',
  imports: [RouterLink, T, Mt],
  template: `
    <div class="between sect">
      <h2>{{ 'security.title' | t }}</h2>
      <a class="btn ghost" routerLink="/me">{{ 'common.back' | t }}</a>
    </div>

    @if (loading()) {
      <div class="card stack">
        <div class="skeleton sk-line"></div>
        <div class="skeleton sk-line w70"></div>
      </div>
    } @else if (loadErr()) {
      <div class="card state">
        <strong>{{ 'common.load_failed' | t }}</strong>
        <span>{{ loadErr() }}</span>
        <button class="btn" type="button" (click)="load()">{{ 'common.retry' | t }}</button>
      </div>
    } @else {
      <!-- 备份码：只在 enable 成功那一次拿得到，服务端之后不再回显 -->
      @if (codes(); as list) {
        <div class="card stack">
          <strong>{{ 'security.codes_title' | t }}</strong>
          <p class="muted hint">{{ 'security.codes_hint' | t }}</p>
          <div class="codes mono">
            @for (c of list; track c) {
              <span class="code">{{ c }}</span>
            }
          </div>
          <div class="acts">
            <button class="btn" type="button" (click)="copy(list.join('\n'))">
              {{ (copied() ? 'common.copied' : 'security.copy_all') | t }}
            </button>
            <button class="btn primary" type="button" (click)="codes.set(null)">
              {{ 'security.codes_close' | t }}
            </button>
          </div>
        </div>
      }

      @if (enabled()) {
        <div class="card stack">
          <div class="between">
            <strong>{{ 'security.on_title' | t }}</strong>
            <span class="badge on">{{ 'security.badge_on' | t }}</span>
          </div>
          <p class="muted hint">{{ 'security.on_hint' | t }}</p>
          @if (offErr()) {
            <div class="alert">{{ offErr() }}</div>
          }
          @if (offOk()) {
            <div class="alert ok">{{ offOk() | mt }}</div>
          }
          <form class="stack" (submit)="disable($event)" novalidate>
            <label class="field">
              <span>{{ 'security.password' | t }}</span>
              <input
                class="input"
                type="password"
                autocomplete="current-password"
                [placeholder]="'security.password_ph' | t"
                [value]="pwd()"
                (input)="pwd.set(val($event))"
              />
            </label>
            <label class="field">
              <span>{{ 'security.code' | t }}</span>
              <input
                class="input mono"
                autocomplete="one-time-code"
                maxlength="6"
                [placeholder]="'security.code_ph' | t"
                [value]="offCode()"
                (input)="offCode.set(val($event))"
              />
            </label>
            <button
              class="btn ghost wide"
              type="submit"
              [disabled]="offBusy() || !pwd().trim() || offCode().trim().length !== 6"
            >
              {{ (offBusy() ? 'security.off_busy' : 'security.disable') | t }}
            </button>
          </form>
        </div>
      } @else if (setup(); as s) {
        <div class="card stack">
          <strong>{{ 'security.step1' | t }}</strong>
          <p class="muted hint">{{ 'security.step1_hint' | t }}</p>
          <div class="kv">
            <span>{{ 'security.secret' | t }}</span><span class="mono secret">{{ grouped() }}</span>
          </div>
          <div class="acts">
            <button class="btn" type="button" (click)="copy(s.secret)">
              {{ (copied() ? 'common.copied' : 'security.copy_secret') | t }}
            </button>
          </div>
          <details>
            <summary class="muted">{{ 'security.otpauth' | t }}</summary>
            <div class="mono url">{{ s.qr_url }}</div>
          </details>

          <strong>{{ 'security.step2' | t }}</strong>
          @if (err()) {
            <div class="alert">{{ err() }}</div>
          }
          <form class="stack" (submit)="enable($event)" novalidate>
            <label class="field">
              <span>{{ 'security.code' | t }}</span>
              <input
                class="input mono"
                autocomplete="one-time-code"
                maxlength="6"
                [placeholder]="'security.code_ph' | t"
                [value]="code()"
                (input)="code.set(val($event))"
              />
            </label>
            <button
              class="btn primary wide"
              type="submit"
              [disabled]="busy() || code().trim().length !== 6"
            >
              {{ (busy() ? 'security.verifying' : 'security.enable') | t }}
            </button>
            <button class="btn ghost wide" type="button" [disabled]="busy()" (click)="cancelSetup()">
              {{ 'common.cancel' | t }}
            </button>
          </form>
        </div>
      } @else {
        <div class="card stack">
          <div class="between">
            <strong>{{ 'security.on_title' | t }}</strong>
            <span class="badge">{{ 'security.badge_off' | t }}</span>
          </div>
          <p class="muted hint">{{ 'security.off_hint' | t }}</p>
          @if (err()) {
            <div class="alert">{{ err() }}</div>
          }
          <button class="btn primary wide" type="button" [disabled]="busy()" (click)="beginSetup()">
            {{ (busy() ? 'security.generating' : 'security.enable') | t }}
          </button>
        </div>
      }
    }
  `,
  styles: [
    `
      .hint {
        margin: 0;
        font-size: 13px;
        line-height: 1.65;
      }
      .sk-line {
        height: 16px;
      }
      .w70 {
        width: 70%;
      }
      .acts {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
      }
      .secret {
        font-size: 15px;
        letter-spacing: 0.08em;
        word-break: break-all;
        text-align: right;
      }
      .url {
        margin-top: 8px;
        font-size: 12px;
        word-break: break-all;
        color: var(--muted);
      }
      .codes {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
      }
      /* 备份码是**要一个一个抄下来**的东西：等宽 + 大字距 + 整块可选中 */
      .code {
        padding: 10px 12px;
        border-radius: var(--r-sm);
        border: 1px solid var(--line);
        background: var(--surface-2);
        font-family: var(--mono);
        font-size: var(--fs-md);
        letter-spacing: 0.08em;
        text-align: center;
        user-select: all;
      }
      @media (min-width: 768px) {
        .codes {
          grid-template-columns: repeat(4, 1fr);
        }
      }
    `,
  ],
})
export class SecurityPage {
  private readonly api = inject(Api);

  protected readonly loading = signal(true);
  protected readonly loadErr = signal('');
  protected readonly enabled = signal(false);
  /** 进行中的 setup：非空 = 正在第二步 */
  protected readonly setup = signal<{ secret: string; qr_url: string } | null>(null);
  /** 刚启用拿到的备份码；**只此一次**，用户点「已抄好」后清掉 */
  protected readonly codes = signal<string[] | null>(null);

  protected readonly code = signal('');
  protected readonly busy = signal(false);
  protected readonly err = signal('');

  protected readonly pwd = signal('');
  protected readonly offCode = signal('');
  protected readonly offBusy = signal(false);
  protected readonly offErr = signal('');
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg` */
  protected readonly offOk = signal<Msg>('');

  protected readonly copied = signal(false);

  /** Base32 密钥按 4 位分组，方便手抄（提交/复制仍用原串） */
  protected readonly grouped = computed(() =>
    (this.setup()?.secret ?? '').replace(/(.{4})/g, '$1 ').trim(),
  );

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.loadErr.set('');
    this.api.twoFactorStatus().subscribe({
      next: (r) => {
        this.enabled.set(!!r.enabled);
        this.loading.set(false);
      },
      error: (e: ApiError) => {
        this.loadErr.set(e.message);
        this.loading.set(false);
      },
    });
  }

  protected beginSetup(): void {
    this.busy.set(true);
    this.err.set('');
    this.api.twoFactorSetup().subscribe({
      next: (r) => {
        this.busy.set(false);
        this.setup.set(r);
      },
      error: (e: ApiError) => {
        this.busy.set(false);
        this.err.set(e.message);
      },
    });
  }

  protected cancelSetup(): void {
    this.setup.set(null);
    this.code.set('');
    this.err.set('');
  }

  /**
   * 表单没有 [formGroup] ⇒ 不能用 (ngSubmit)（NgForm 不在 ReactiveFormsModule 的导出里，
   * 那是死绑定，浏览器会走原生提交刷页）。这里自己 preventDefault，理由同 login.ts。
   */
  protected enable(ev: Event): void {
    ev.preventDefault();
    const s = this.setup();
    const code = this.code().trim();
    if (!s || this.busy() || code.length !== 6) return;
    this.busy.set(true);
    this.err.set('');
    this.api.twoFactorEnable(code).subscribe({
      next: (r) => {
        this.busy.set(false);
        this.setup.set(null);
        this.code.set('');
        this.enabled.set(true);
        this.codes.set(r.backup_codes ?? []);
      },
      error: (e: ApiError) => {
        // 码错留在第二步可重试，密钥不失效（服务端 setup 行还在，is_enabled 仍为 0）
        this.busy.set(false);
        this.err.set(e.message);
      },
    });
  }

  protected disable(ev: Event): void {
    ev.preventDefault();
    const password = this.pwd();
    const code = this.offCode().trim();
    if (this.offBusy() || !password || code.length !== 6) return;
    this.offBusy.set(true);
    this.offErr.set('');
    this.offOk.set('');
    this.api.twoFactorDisable(password, code).subscribe({
      next: () => {
        this.offBusy.set(false);
        this.enabled.set(false);
        this.pwd.set('');
        this.offCode.set('');
        this.offOk.set({ key: 'security.2fa_off_done' });
      },
      error: (e: ApiError) => {
        this.offBusy.set(false);
        this.offErr.set(e.message);
      },
    });
  }

  protected val(ev: Event): string {
    return (ev.target as HTMLInputElement).value;
  }

  /** 复制：clipboard 在非安全上下文（http 非 localhost）下不可用，失败就静默 —— 码本身在页面上可手抄 */
  protected copy(text: string): void {
    void navigator.clipboard?.writeText(text).then(
      () => this.copied.set(true),
      () => this.copied.set(false),
    );
  }
}
