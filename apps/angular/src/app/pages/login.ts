/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AbstractControl } from '@angular/forms';
import { Api, ApiError, AuthResult, CaptchaProof, tokens } from '../core/api.service';
import { CaptchaBox } from '../core/captcha';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, CaptchaBox],
  template: `
    <div class="auth">
      <div class="card auth-card">
        <div class="brand big"><img class="dot" src="mascot.svg" alt="" /><span>Aurora</span></div>
        <p class="muted tagline">登录后即可开局、查看钱包与消息</p>

        <div class="chips">
          <button type="button" class="chip" [class.on]="tab() === 'in'" (click)="switch('in')">
            登录
          </button>
          <button type="button" class="chip" [class.on]="tab() === 'up'" (click)="switch('up')">
            注册
          </button>
        </div>

        @if (error()) {
          <div class="alert">{{ error() }}</div>
        }

        @if (tab() === 'in') {
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
      .auth-card {
        width: 100%;
        max-width: 420px;
        padding: 28px;
        display: flex;
        flex-direction: column;
        gap: 16px;
      }
      .brand.big {
        font-size: 22px;
        position: static;
        margin: 0;
      }
      .tagline {
        margin: -8px 0 0;
        font-size: 13px;
      }
    `,
  ],
})
export class LoginPage {
  private readonly fb = inject(FormBuilder);
  private readonly api = inject(Api);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly tab = signal<'in' | 'up'>('in');
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
  });

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

  private done(r: AuthResult): void {
    this.busy.set(false);
    if (r.require_2fa) {
      this.error.set(
        '该账号已开启二次验证（2FA），本客户端暂不支持，请改用支持 2FA 的客户端登录。',
      );
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
