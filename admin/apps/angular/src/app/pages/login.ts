/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Api, CaptchaChallenge, Click } from '../core/api.service';
import { Auth } from '../core/auth.service';
import { T, t } from '../core/i18n/i18n';
import { imgSrc } from '../core/render';
import { errText } from '../core/util';
import { ModalFocus } from '../components/ui';

interface Mark extends Click {
  /** 百分比位置，仅用于叠加标记点 */
  px: number;
  py: number;
}

/**
 * 点击式验证码登录：账号密码齐后弹框，框内取图、收集 N 个点击点，
 * 坐标换算回图片原始像素；验证码一次性，每次开框重取。
 */
@Component({
  selector: 'app-login',
  imports: [T, ModalFocus],
  template: `
    <div class="login-wrap">
      <div class="login-card">
        <h1>{{ 'app.brand' | t }}</h1>
        <p class="sub">{{ 'login.subtitle' | t }}</p>

        @if (error()) {
          <div class="alert">{{ error() }}</div>
        }

        <div class="field">
          <label>{{ 'login.username' | t }}</label>
          <input
            class="input"
            autocomplete="username"
            [placeholder]="'login.username_hint' | t"
            [value]="username()"
            (input)="username.set($any($event.target).value)"
          />
        </div>

        <div class="field">
          <label>{{ 'login.password' | t }}</label>
          <input
            class="input"
            type="password"
            autocomplete="current-password"
            [placeholder]="'login.password_hint' | t"
            [value]="password()"
            (input)="password.set($any($event.target).value)"
          />
        </div>

        <button
          class="btn btn-primary btn-block"
          [disabled]="busy() || !canSubmit()"
          (click)="submit()"
        >
          {{ (busy() ? 'login.submitting' : 'login.login') | t }}
        </button>
      </div>

      @if (capOpen()) {
        <div class="backdrop" (click)="closeCap()"></div>
        <div
          class="modal modal-sm"
          role="dialog"
          aria-modal="true"
          [attr.aria-label]="'login.captcha' | t"
          uiModal
          (dismiss)="closeCap()"
        >
          <header>
            <b>{{ 'login.captcha' | t }}</b>
            <span class="spacer"></span>
            <button class="btn" type="button" (click)="closeCap()">{{ 'app.close' | t }}</button>
          </header>
          <div class="modal-body">
            @if (cap(); as c) {
              <div class="captcha-hint">
                {{ c.texts.length ? c.texts.join(' → ') : ('login.captcha_prompt' | t) }}
              </div>
              <div class="cap-wrap">
                <img
                  class="cap-img"
                  [src]="image()"
                  (click)="hit($event)"
                  [alt]="'login.captcha_alert' | t"
                />
                @for (m of marks(); track $index) {
                  <i class="cap-dot" [style.left.%]="m.px" [style.top.%]="m.py">{{ $index + 1 }}</i>
                }
              </div>
              <div class="cap-foot">
                <span>{{ 'login.captcha_clicked' | t: { n: marks().length, need: required() } }}</span>
                <span class="spacer"></span>
                <button class="btn" type="button" [disabled]="!marks().length" (click)="undo()">
                  {{ 'login.captcha_undo' | t }}
                </button>
                <button class="btn" type="button" (click)="reload()">
                  {{ 'login.captcha_refresh' | t }}
                </button>
              </div>
              <button
                class="btn btn-primary btn-block"
                type="button"
                [disabled]="busy() || !canConfirm()"
                (click)="confirm()"
              >
                {{ (busy() ? 'login.submitting' : 'login.captcha_confirm') | t }}
              </button>
            } @else {
              <div class="state"><span class="spinner"></span> {{ 'login.captcha_loading' | t }}</div>
            }
          </div>
        </div>
      }
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
  protected readonly capOpen = signal(false);
  protected readonly marks = signal<Mark[]>([]);
  protected readonly busy = signal(false);
  protected readonly error = signal('');

  protected readonly image = computed(() => imgSrc(this.cap()?.image));
  protected readonly required = computed(() => this.cap()?.texts.length || 2);
  protected readonly canSubmit = computed(
    () => this.username().trim().length > 0 && this.password().length > 0 && !this.busy(),
  );
  protected readonly canConfirm = computed(
    () => this.cap() !== null && this.marks().length >= this.required(),
  );

  protected submit(): void {
    if (!this.canSubmit()) return;
    this.error.set('');
    this.capOpen.set(true);
    void this.reload();
  }

  /** 关框即作废本次挑战，下次开框由 submit() 重取 */
  protected closeCap(): void {
    this.capOpen.set(false);
    this.cap.set(null);
    this.marks.set([]);
  }

  protected async reload(): Promise<void> {
    this.cap.set(null);
    this.marks.set([]);
    try {
      const c = await this.api.captcha();
      if (!c.key || !c.image) throw new Error(t('login.captcha_empty'));
      this.cap.set(c);
    } catch (e) {
      this.error.set(errText(e));
      this.closeCap();
    }
  }

  /** 显示坐标 → 图片原始像素 */
  protected hit(ev: MouseEvent): void {
    if (this.marks().length >= this.required()) return;
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

  protected async confirm(): Promise<void> {
    const c = this.cap();
    if (!c || !this.canConfirm()) return;
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
      this.closeCap();
    } finally {
      this.busy.set(false);
    }
  }
}
