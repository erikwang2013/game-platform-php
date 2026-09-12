/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AbstractControl } from '@angular/forms';
import { Api, ApiError, AuthResult, tokens } from '../core/api.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule],
  template: `
    <div class="auth">
      <div class="card auth-card">
        <div class="brand big"><span class="dot"></span><span>Aurora</span></div>
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
                placeholder="至少 6 个字符"
              />
              @if (show(regForm.controls.password)) {
                <span class="err">密码至少 6 个字符</span>
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

  protected readonly loginForm = this.fb.nonNullable.group({
    username: ['', [Validators.required, Validators.minLength(3)]],
    password: ['', [Validators.required, Validators.minLength(6)]],
  });

  protected readonly regForm = this.fb.nonNullable.group({
    username: ['', [Validators.required, Validators.minLength(3)]],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(6)]],
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
    this.error.set('');
    this.busy.set(true);
    const { username, password } = f.getRawValue();
    this.api.login(username.trim(), password).subscribe({
      next: (r) => this.done(r),
      error: (e: ApiError) => this.failed(e),
    });
  }

  protected register(): void {
    const f = this.regForm;
    if (f.invalid) {
      f.markAllAsTouched();
      return;
    }
    this.error.set('');
    this.busy.set(true);
    const v = f.getRawValue();
    this.api
      .register({
        username: v.username.trim(),
        password: v.password,
        email: v.email.trim(),
        nickname: v.nickname.trim(),
      })
      .subscribe({
        next: (r) => this.done(r),
        error: (e: ApiError) => this.failed(e),
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
