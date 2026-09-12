/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Api, CaptchaChallenge, Click } from '../core/api.service';
import { Auth } from '../core/auth.service';
import { imgSrc } from '../core/render';
import { errText } from '../core/util';

interface Mark extends Click {
  /** 百分比位置，仅用于叠加标记点 */
  px: number;
  py: number;
}

/** 点击式验证码登录：去服务端取图，收集 N 个点击点，坐标换算回图片原始像素 */
@Component({
  selector: 'app-login',
  template: `
    <div class="login-wrap">
      <div class="login-card">
        <h1>游戏运营后台</h1>
        <p class="sub">Slate Pro 控制台 · 管理端</p>

        @if (error()) {
          <div class="alert">{{ error() }}</div>
        }

        <div class="field">
          <label>账号</label>
          <input
            class="input"
            autocomplete="username"
            placeholder="管理员账号"
            [value]="username()"
            (input)="username.set($any($event.target).value)"
          />
        </div>

        <div class="field">
          <label>密码</label>
          <input
            class="input"
            type="password"
            autocomplete="current-password"
            placeholder="登录密码"
            [value]="password()"
            (input)="password.set($any($event.target).value)"
          />
        </div>

        <div class="field">
          <label>安全验证（依次点击图中提示文字）</label>
          @if (cap(); as c) {
            <div class="captcha-hint">
              {{ c.texts.length ? c.texts.join(' → ') : '请按图片提示依次点击' }}
            </div>
            <div class="cap-wrap">
              <img class="cap-img" [src]="image()" (click)="hit($event)" alt="点击验证码" />
              @for (m of marks(); track $index) {
                <i class="cap-dot" [style.left.%]="m.px" [style.top.%]="m.py">{{ $index + 1 }}</i>
              }
            </div>
            <div class="cap-foot">
              <span>已点击 {{ marks().length }} 点（至少 2 点）</span>
              <button class="btn" type="button" (click)="undo()">撤销</button>
              <button class="btn" type="button" (click)="reload()">换一张</button>
            </div>
          } @else {
            <div class="state"><span class="spinner"></span> 验证码加载中…</div>
          }
        </div>

        <button
          class="btn btn-primary btn-block"
          [disabled]="busy() || !canSubmit()"
          (click)="submit()"
        >
          {{ busy() ? '登录中…' : '登 录' }}
        </button>
      </div>
    </div>
  `,
})
export class Login {
  private readonly api = inject(Api);
  private readonly auth = inject(Auth);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly username = signal('');
  protected readonly password = signal('');
  protected readonly cap = signal<CaptchaChallenge | null>(null);
  protected readonly marks = signal<Mark[]>([]);
  protected readonly busy = signal(false);
  protected readonly error = signal('');

  protected readonly image = computed(() => imgSrc(this.cap()?.image));
  protected readonly canSubmit = computed(
    () =>
      this.username().trim().length > 0 &&
      this.password().length > 0 &&
      this.marks().length >= 2 &&
      this.cap() !== null,
  );

  constructor() {
    void this.reload();
  }

  protected async reload(): Promise<void> {
    this.cap.set(null);
    this.marks.set([]);
    try {
      const c = await this.api.captcha();
      if (!c.key || !c.image) throw new Error('验证码服务返回为空，请稍后重试');
      this.cap.set(c);
    } catch (e) {
      this.error.set(errText(e));
    }
  }

  /** 显示坐标 → 图片原始像素 */
  protected hit(ev: MouseEvent): void {
    const img = ev.currentTarget as HTMLImageElement;
    const box = img.getBoundingClientRect();
    if (!box.width || !box.height) return;
    const px = ((ev.clientX - box.left) / box.width) * 100;
    const py = ((ev.clientY - box.top) / box.height) * 100;
    const nw = img.naturalWidth || box.width;
    const nh = img.naturalHeight || box.height;
    this.marks.update((list) => [
      ...list,
      { x: Math.round((px / 100) * nw), y: Math.round((py / 100) * nh), px, py },
    ]);
  }

  protected undo(): void {
    this.marks.update((list) => list.slice(0, -1));
  }

  protected async submit(): Promise<void> {
    const c = this.cap();
    if (!c || !this.canSubmit()) return;
    this.busy.set(true);
    this.error.set('');
    try {
      const res = await this.api.login({
        username: this.username().trim(),
        password: this.password(),
        captcha_key: c.key,
        clicks: this.marks().map((m) => ({ x: m.x, y: m.y })),
      });
      this.auth.set(res, res.user);
      const to = this.route.snapshot.queryParamMap.get('redirect');
      await this.router.navigateByUrl(to && to.startsWith('/') ? to : '/dashboard');
    } catch (e) {
      this.error.set(errText(e));
      await this.reload();
    } finally {
      this.busy.set(false);
    }
  }
}
