/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, effect, inject, input, model, output, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { Api, ApiError, CaptchaChallenge, CaptchaProof, Click } from './api.service';
import { Mt, Msg, T } from './i18n/i18n';

interface Mark extends Click {
  /** 百分比位置，仅用于叠加标记点 */
  px: number;
  py: number;
}

/**
 * 点击式验证码弹框 —— 登录/注册/提现/兑换卖出四处共用一个实例（不要各页复制一份）。
 *
 * 调用方只持有开框信号与请求：
 *   <app-captcha [(open)]="capOpen" [busy]="busy()" action="withdraw.captcha_action" (proof)="onProof($event)" />
 * 取图/标记/撤销/换一张/确认门控都在组件内；**请求本身由调用方发起**（失败关框后，
 * 服务端 message 显示在页面原有错误位）。
 *
 * 坐标：画布恒 300×200，点击位置按「显示框 → 图片原始像素」等比换算（用 naturalWidth/Height，
 * 不写死任何画布尺寸）。
 */
@Component({
  selector: 'app-captcha',
  host: { '(document:keydown.escape)': 'onEsc()' },
  imports: [T, Mt],
  template: `
    @if (open()) {
      <div class="backdrop" (click)="close()"></div>
      <div class="modal" role="dialog" aria-modal="true" [attr.aria-label]="'captcha.title' | t">
        <header class="between">
          <b>{{ 'captcha.title' | t }}</b>
          <button class="btn ghost" type="button" (click)="close()">{{ 'common.close' | t }}</button>
        </header>

        <div class="modal-body">
          @if (cap(); as c) {
            <div class="captcha-hint">
              {{
                c.texts.length
                  ? ('captcha.hint_ordered' | t) + c.texts.join(' → ')
                  : ('captcha.hint_plain' | t)
              }}
            </div>
            <div class="cap-wrap">
              <img
                class="cap-img"
                [src]="image()"
                (click)="hit($event)"
                [alt]="'captcha.image_alt' | t"
              />
              @for (m of marks(); track $index) {
                <i class="cap-dot" [style.left.%]="m.px" [style.top.%]="m.py">{{ $index + 1 }}</i>
              }
            </div>
            <div class="cap-foot between">
              <span>{{ 'captcha.progress' | t: { done: marks().length, need: required() } }}</span>
              <span class="wrap">
                <button
                  class="btn ghost"
                  type="button"
                  [disabled]="!marks().length"
                  (click)="undo()"
                >
                  {{ 'captcha.undo' | t }}
                </button>
                <button class="btn ghost" type="button" (click)="reload()">
                  {{ 'captcha.reload' | t }}
                </button>
              </span>
            </div>
            <button
              class="btn primary wide"
              type="button"
              [disabled]="busy() || !canConfirm()"
              (click)="confirm()"
            >
              {{ (busy() ? 'common.submitting' : action()) | t }}
            </button>
          } @else if (capError()) {
            <div class="alert">{{ capError() | mt }}</div>
            <button class="btn ghost wide" type="button" (click)="reload()">
              {{ 'common.retry' | t }}
            </button>
          } @else {
            <div class="state"><span class="spin"></span> {{ 'captcha.loading' | t }}</div>
          }
        </div>
      </div>
    }
  `,
})
export class CaptchaBox {
  private readonly api = inject(Api);

  /** 开框状态由调用方持有（双向绑定） */
  readonly open = model(false);
  /** 调用方的请求进行中：确认按钮禁用，防重复提交 */
  readonly busy = input(false);
  /**
   * 确认按钮的**词条键**（如 `'login.captcha_login'`），不是译好的串 —— 模板里
   * `{{ … | t }}` 渲染期才查表，所以切语言时这个按钮跟着变（存译好的串就冻在 set 那一刻）。
   * 形状与 `Msg` 的键那一态一致：**存键、渲染期求值**。
   */
  readonly action = input('common.confirm');
  /** 点数点满后确认 → 调用方带 captcha_key/clicks 调原接口 */
  readonly proof = output<CaptchaProof>();

  protected readonly cap = signal<CaptchaChallenge | null>(null);
  protected readonly marks = signal<Mark[]>([]);
  /** 两态：服务端原文原样透出，本地兜底走词条键（渲染期才算，切语言跟着变） */
  protected readonly capError = signal<Msg>('');

  protected readonly image = computed(() => {
    const s = this.cap()?.image ?? '';
    if (!s || s.startsWith('data:')) return s;
    return `data:image/png;base64,${s}`;
  });
  protected readonly required = computed(() => this.cap()?.texts.length || 2);
  protected readonly canConfirm = computed(
    () => this.cap() !== null && this.marks().length >= this.required(),
  );

  constructor() {
    // 开框现取新图（验证码一次性，复用必验不过）；关框即作废本次挑战
    effect(() => {
      if (this.open()) void this.reload();
      else this.reset();
    });
  }

  protected onEsc(): void {
    if (this.open()) this.close();
  }

  protected close(): void {
    this.open.set(false);
  }

  protected async reload(): Promise<void> {
    this.reset();
    try {
      const c = await firstValueFrom(this.api.captcha());
      // ⚠ 空响应是**本地判定**，不是服务端原文 ⇒ 不能走 raw 那一态：本地抛的中文 Error
      // 会经 catch 的 `e.message` 原样出现在英文界面上。直接落键，渲染期查表。
      //
      // ⚠ **这条路径的用户可见文案变了**（复用已有的键，省 13 格译文）：
      //   改前：`验证码服务返回为空，请稍后重试`（硬编码中文，英文界面也出中文）
      //   改后：`common.captcha_load_failed` = zh「验证码加载失败」/ en「CAPTCHA failed to load」
      // 代价是**具体性下降**（不再区分"空响应"与"取图失败"），换来的是能翻译 + 会随语言切换。
      // 之所以必须写在这里：这条分支**没有任何仪器覆盖**（渲染比对走不到"空响应"这个失败态），
      // 不声明就等于静默改文案。
      if (!c.key || !c.image) {
        this.capError.set({ key: 'common.captcha_load_failed' });
        return;
      }
      this.cap.set(c);
    } catch (e) {
      this.capError.set(
        e instanceof ApiError || e instanceof Error ? e.message : { key: 'common.captcha_load_failed' },
      );
    }
  }

  /** 显示坐标 → 图片原始像素 */
  protected hit(ev: MouseEvent): void {
    if (!this.cap() || this.marks().length >= this.required()) return;
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

  protected confirm(): void {
    const c = this.cap();
    if (!c || !this.canConfirm()) return;
    this.proof.emit({
      captcha_key: c.key,
      clicks: this.marks().map((m) => ({ x: m.x, y: m.y })),
    });
  }

  private reset(): void {
    this.cap.set(null);
    this.marks.set([]);
    this.capError.set('');
  }
}
