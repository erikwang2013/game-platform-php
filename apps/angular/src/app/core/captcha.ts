/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, effect, inject, input, model, output, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { Api, ApiError, CaptchaChallenge, CaptchaProof, Click } from './api.service';

interface Mark extends Click {
  /** 百分比位置，仅用于叠加标记点 */
  px: number;
  py: number;
}

/**
 * 点击式验证码弹框 —— 登录/注册/提现/兑换卖出四处共用一个实例（不要各页复制一份）。
 *
 * 调用方只持有开框信号与请求：
 *   <app-captcha [(open)]="capOpen" [busy]="busy()" action="确认提现" (proof)="onProof($event)" />
 * 取图/标记/撤销/换一张/确认门控都在组件内；**请求本身由调用方发起**（失败关框后，
 * 服务端 message 显示在页面原有错误位）。
 *
 * 坐标：画布恒 300×200，点击位置按「显示框 → 图片原始像素」等比换算（用 naturalWidth/Height，
 * 不写死任何画布尺寸）。
 */
@Component({
  selector: 'app-captcha',
  host: { '(document:keydown.escape)': 'onEsc()' },
  template: `
    @if (open()) {
      <div class="backdrop" (click)="close()"></div>
      <div class="modal" role="dialog" aria-modal="true" aria-label="安全验证">
        <header class="between">
          <b>安全验证</b>
          <button class="btn ghost" type="button" (click)="close()">关闭</button>
        </header>

        <div class="modal-body">
          @if (cap(); as c) {
            <div class="captcha-hint">
              {{
                c.texts.length
                  ? '按顺序点击图中文字：' + c.texts.join(' → ')
                  : '请按图片提示依次点击'
              }}
            </div>
            <div class="cap-wrap">
              <img class="cap-img" [src]="image()" (click)="hit($event)" alt="点击验证码" />
              @for (m of marks(); track $index) {
                <i class="cap-dot" [style.left.%]="m.px" [style.top.%]="m.py">{{ $index + 1 }}</i>
              }
            </div>
            <div class="cap-foot between">
              <span>已点击 {{ marks().length }} 点（需 {{ required() }} 点）</span>
              <span class="wrap">
                <button
                  class="btn ghost"
                  type="button"
                  [disabled]="!marks().length"
                  (click)="undo()"
                >
                  撤销
                </button>
                <button class="btn ghost" type="button" (click)="reload()">换一张</button>
              </span>
            </div>
            <button
              class="btn primary wide"
              type="button"
              [disabled]="busy() || !canConfirm()"
              (click)="confirm()"
            >
              {{ busy() ? '提交中…' : action() }}
            </button>
          } @else if (capError()) {
            <div class="alert">{{ capError() }}</div>
            <button class="btn ghost wide" type="button" (click)="reload()">重试</button>
          } @else {
            <div class="state"><span class="spin"></span> 验证码加载中…</div>
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
  /** 确认按钮文案，如「确认登录」 */
  readonly action = input('确认');
  /** 点数点满后确认 → 调用方带 captcha_key/clicks 调原接口 */
  readonly proof = output<CaptchaProof>();

  protected readonly cap = signal<CaptchaChallenge | null>(null);
  protected readonly marks = signal<Mark[]>([]);
  protected readonly capError = signal('');

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
      if (!c.key || !c.image) throw new Error('验证码服务返回为空，请稍后重试');
      this.cap.set(c);
    } catch (e) {
      this.capError.set(e instanceof ApiError || e instanceof Error ? e.message : '验证码加载失败');
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
