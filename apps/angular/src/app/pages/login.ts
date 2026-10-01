/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AbstractControl } from '@angular/forms';
import { Api, ApiError, AuthResult, CaptchaProof, tokens } from '../core/api.service';
import { CaptchaBox } from '../core/captcha';

/** 邀请链接 `?code=` 的取值：服务端 `share_code` 收 `nullable|string|max:12`，此处同口径（形同 react 树） */
const readInviteCode = (raw: string | null): string => {
  const v = (raw ?? '').trim();
  return v.length > 0 && v.length <= 12 ? v : '';
};

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, CaptchaBox],
  template: `
    <div class="auth">
      <div class="card auth-card">
        <div class="brand big"><img class="dot" src="mascot.svg" alt="" /><span>NeonArcade</span></div>
        <p class="muted tagline">登录后即可开局、查看钱包与消息</p>

        @if (!tfa()) {
          <div class="chips">
            <button type="button" class="chip" [class.on]="tab() === 'in'" (click)="switch('in')">
              登录
            </button>
            <button type="button" class="chip" [class.on]="tab() === 'up'" (click)="switch('up')">
              注册
            </button>
          </div>
        }

        @if (error()) {
          <div class="alert">{{ error() }}</div>
        }

        @if (tfa()) {
          <!--
            第二步：账号已开二次验证。密码已验过，这一步认两种码 ——
            6 位 TOTP（验证器 App）或 10 位备份码（服务端 enable() 一次发 8 个）。
            长度上限必须放到 10：曾写死 maxlength=6，10 位备份码会被**输入框自己截成 6 位**
            再送去校验 ⇒ 必然 422，服务端把 validator 放宽到 between:6,10 也救不回来。
            同理不能加 inputmode=numeric —— 备份码是大小写字母+数字（generateBackupCode()）。
          -->
          <form class="stack" (submit)="submit2fa($event)" novalidate>
            <p class="muted hint2">
              该账号已开启两步验证：请输入验证器 App 中的 6 位动态码；设备丢失时可改用 10 位备份码。
            </p>
            <label class="field">
              <span>动态码 / 备份码</span>
              <input
                class="input mono"
                name="code2fa"
                autocomplete="one-time-code"
                maxlength="10"
                placeholder="6 位动态码或 10 位备份码"
                [value]="code2fa()"
                (input)="on2fa($event)"
              />
            </label>
            <button class="btn primary wide" type="submit" [disabled]="busy() || !code2fa().trim()">
              {{ busy() ? '验证中…' : '验证并登录' }}
            </button>
            <button class="btn ghost wide" type="button" [disabled]="busy()" (click)="cancel2fa()">
              返回重新登录
            </button>
          </form>
        } @else if (tab() === 'in') {
          <form class="stack" [formGroup]="loginForm" (ngSubmit)="login()" novalidate>
            <label class="field">
              <span>用户名</span>
              <input
                class="input"
                formControlName="username"
                autocomplete="username"
                placeholder="请输入用户名"
              />
              @if (show(loginForm.controls.username)) {
                <span class="err">用户名至少 3 个字符</span>
              }
            </label>
            <label class="field">
              <span>密码</span>
              <input
                class="input"
                type="password"
                formControlName="password"
                autocomplete="current-password"
                placeholder="请输入密码"
              />
              @if (show(loginForm.controls.password)) {
                <span class="err">密码至少 6 个字符</span>
              }
            </label>
            <button class="btn primary wide" type="submit" [disabled]="busy()">
              {{ busy() ? '登录中…' : '登录' }}
            </button>
          </form>
        } @else {
          <form class="stack" [formGroup]="regForm" (ngSubmit)="register()" novalidate>
            <label class="field">
              <span>用户名</span>
              <input
                class="input"
                formControlName="username"
                autocomplete="username"
                placeholder="3-20 位字母或数字"
              />
              @if (show(regForm.controls.username)) {
                <span class="err">用户名至少 3 个字符</span>
              }
            </label>
            <label class="field">
              <span>邮箱</span>
              <input
                class="input"
                type="email"
                formControlName="email"
                autocomplete="email"
                placeholder="you@example.com"
              />
              @if (show(regForm.controls.email)) {
                <span class="err">请输入有效邮箱</span>
              }
            </label>
            <label class="field">
              <span>密码</span>
              <input
                class="input"
                type="password"
                formControlName="password"
                autocomplete="new-password"
                placeholder="8-32 位，含大小写字母和数字"
              />
              @if (show(regForm.controls.password)) {
                <span class="err">密码 8-32 个字符，需含大小写字母和数字</span>
              }
            </label>
            <label class="field">
              <span>昵称（可选）</span>
              <input class="input" formControlName="nickname" placeholder="展示用昵称" />
            </label>
            <label class="field">
              <span>邀请码（可选）</span>
              <input
                class="input mono"
                formControlName="invite"
                maxlength="12"
                autocomplete="off"
                placeholder="朋友分享的 8 位码"
              />
              @if (show(regForm.controls.invite)) {
                <span class="err">邀请码最多 12 个字符</span>
              }
            </label>
            <button class="btn primary wide" type="submit" [disabled]="busy()">
              {{ busy() ? '注册中…' : '创建账号' }}
            </button>
          </form>
        }
      </div>
    </div>

    <!-- 登录/注册服务端强制验证码：本地校验通过后弹框，确认才发原请求 -->
    <app-captcha
      [(open)]="capOpen"
      [busy]="busy()"
      [action]="pending() === 'in' ? '确认登录' : '确认注册'"
      (proof)="onProof($event)"
    />
  `,
  styles: [
    `
      .auth {
        display: flex;
        justify-content: center;
        padding: 34px 0;
      }
      /* 登录卡是全站第一屏，给它一档更高的浮起 + 一圈品牌紫描边 */
      .auth-card {
        width: 100%;
        max-width: 420px;
        padding: 28px;
        border-color: color-mix(in srgb, var(--primary) 26%, var(--line));
        box-shadow: var(--sh-3);
        display: flex;
        flex-direction: column;
        gap: 16px;
      }
      .brand.big {
        font-size: 23px;
        position: static;
        margin: 0;
      }
      .tagline {
        margin: -8px 0 0;
        font-size: 13px;
      }
      .hint2 {
        margin: 0;
        font-size: 13px;
        line-height: 1.6;
      }
    `,
  ],
})
export class LoginPage {
  private readonly fb = inject(FormBuilder);
  private readonly api = inject(Api);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  /**
   * 邀请链接带来的短码（`/login?code=xxx`）。**必须先于 tab 声明** ——
   * 字段初始化器按声明顺序执行，tab 的初值要读它来决定落在注册页签。
   */
  private readonly inviteCode = readInviteCode(this.route.snapshot.queryParamMap.get('code'));

  protected readonly tab = signal<'in' | 'up'>(this.inviteCode ? 'up' : 'in');
  protected readonly busy = signal(false);
  protected readonly error = signal('');
  /** 验证码弹框开框状态；pending 记住开框时是登录还是注册（框开后 tab 仍可被键盘改动） */
  protected readonly capOpen = signal(false);
  protected readonly pending = signal<'in' | 'up'>('in');

  protected readonly loginForm = this.fb.nonNullable.group({
    username: ['', [Validators.required, Validators.minLength(3)]],
    password: ['', [Validators.required, Validators.minLength(6)]],
  });

  protected readonly regForm = this.fb.nonNullable.group({
    username: ['', [Validators.required, Validators.minLength(3)]],
    email: ['', [Validators.required, Validators.email]],
    password: [
      '',
      [
        Validators.required,
        Validators.minLength(8),
        Validators.maxLength(32),
        Validators.pattern(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/),
      ],
    ],
    nickname: [''],
    invite: [this.inviteCode, [Validators.maxLength(12)]],
  });

  /**
   * 带邀请码进来 = 朋友点的落地页：注册页签已预填好码，这里**补上另一半 —— 上报点击**
   * （`POST /shares/visit`，公开路由）。没有这一步，短码只被生成、从不被点击（`clicks` 恒 0），
   * 邀请链接就只剩一个假仪式。失败**静默**：网络抖动或码已失效都不该挡住注册。
   */
  constructor() {
    if (!this.inviteCode) return;
    this.api.shareVisit(this.inviteCode).subscribe({ error: () => undefined });
  }

  protected switch(t: 'in' | 'up'): void {
    this.tab.set(t);
    this.error.set('');
  }

  protected show(c: AbstractControl): boolean {
    return c.invalid && (c.touched || c.dirty);
  }

  protected login(): void {
    const f = this.loginForm;
    if (f.invalid) {
      f.markAllAsTouched();
      return;
    }
    this.openCap('in');
  }

  protected register(): void {
    const f = this.regForm;
    if (f.invalid) {
      f.markAllAsTouched();
      return;
    }
    this.openCap('up');
  }

  /** 本地校验通过才弹框；开框由验证码组件现取新图 */
  private openCap(which: 'in' | 'up'): void {
    this.error.set('');
    this.pending.set(which);
    this.capOpen.set(true);
  }

  /** 弹框确认 → 带 captcha_key/clicks 调原接口；失败关框，服务端 message 回到顶部错误位 */
  protected onProof(p: CaptchaProof): void {
    const log = this.loginForm.getRawValue();
    const reg = this.regForm.getRawValue();
    this.busy.set(true);
    this.error.set('');
    const call =
      this.pending() === 'in'
        ? this.api.login(log.username.trim(), log.password, p)
        : this.api.register({
            username: reg.username.trim(),
            password: reg.password,
            email: reg.email.trim(),
            nickname: reg.nickname.trim(),
            // 空码**不进请求体**：多余的 `share_code: ''` 虽被服务端 nullable 放行，
            // 但会把"这次注册带了邀请"这件事变成看不出来的假象（同 react 树）
            ...(reg.invite.trim() ? { share_code: reg.invite.trim() } : {}),
            ...p,
          });
    call.subscribe({
      next: (r) => {
        this.capOpen.set(false);
        this.done(r);
      },
      error: (e: ApiError) => {
        this.capOpen.set(false);
        this.failed(e);
      },
    });
  }

  private failed(e: ApiError): void {
    this.busy.set(false);
    this.error.set(e.message);
  }

  /* ---- 两步验证（2FA） ---- */

  /** 非空 = 已过密码校验、等第二步码；此时隐藏登录/注册表单 */
  protected readonly tfa = signal('');
  protected readonly code2fa = signal('');

  protected on2fa(ev: Event): void {
    this.code2fa.set((ev.target as HTMLInputElement).value);
    this.error.set('');
  }

  protected cancel2fa(): void {
    this.tfa.set('');
    this.code2fa.set('');
    this.busy.set(false);
    this.error.set('');
    this.loginForm.reset();
  }

  /**
   * 第二步：拿 pending_2fa_token + 验证码（6 位 TOTP 或 10 位备份码）换正式令牌。
   * 失败**不退出**这一步（码错/锁定都能重试），只有返回按钮才回到登录表单；
   * 票据本身会过期（服务端 jwt 校验），过期后重试会回 401，届时提示用户重新登录。
   */
  /**
   * 第二步的表单**没有 [formGroup]**，所以不能用树里别处那种 `(ngSubmit)`：
   * ngSubmit 由 NgForm / FormGroupDirective 提供，而 ReactiveFormsModule 只导出后者
   * （NgForm 在 FormsModule 里）⇒ 这里 `(ngSubmit)` 是死绑定，浏览器会走**原生提交**，
   * 整页刷新成 `/login?code2fa=123456`，用户看到的是「点了没反应」。必须自己 preventDefault。
   * 真机实测：改前点按钮 → location.href 变成 /login?code2fa=123456 且不发任何请求。
   */
  protected submit2fa(ev: Event): void {
    ev.preventDefault();
    this.verify2fa();
  }

  protected verify2fa(): void {
    const code = this.code2fa().trim();
    if (this.busy() || !code) return;
    // 服务端是 between:6,10，7~9 位既不是 TOTP 也不是备份码 ⇒ 必然 422。
    // 本地先拦，省一次注定失败的往返；**故意不禁用按钮**：按钮一灰用户就不知道错在哪，
    // 这里给一句说明比让他对着灰按钮发呆强（react 那棵同形，闸也是 len===6||len===10）。
    if (code.length !== 6 && code.length !== 10) {
      this.error.set('请输入 6 位动态码，或 10 位备份码。');
      return;
    }
    this.busy.set(true);
    this.error.set('');
    this.api.twoFactorVerify(this.tfa(), code).subscribe({
      next: (r) => {
        this.tfa.set('');
        this.code2fa.set('');
        this.done(r);
      },
      error: (e: ApiError) => {
        this.busy.set(false);
        // 票据失效/账号被停用：留着这一步也没用，退回登录表单
        if (e.code === 401 || e.code === 403) {
          this.tfa.set('');
          this.code2fa.set('');
          this.error.set(`${e.message}（请重新登录）`);
          return;
        }
        this.error.set(e.message);
      },
    });
  }

  private done(r: AuthResult): void {
    this.busy.set(false);
    if (r.require_2fa) {
      if (!r.pending_2fa_token) {
        this.error.set('服务端要求二次验证但未下发票据，请稍后重试。');
        return;
      }
      this.code2fa.set('');
      this.error.set('');
      this.tfa.set(r.pending_2fa_token);
      return;
    }
    if (!r.access_token) {
      this.error.set('登录响应缺少令牌，请稍后重试。');
      return;
    }
    tokens.save(r.access_token, r.refresh_token);
    const redirect = this.route.snapshot.queryParamMap.get('redirect');
    const safe =
      redirect && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/';
    void this.router.navigateByUrl(safe);
  }
}
