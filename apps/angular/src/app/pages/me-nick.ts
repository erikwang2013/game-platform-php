/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, input, output, signal } from '@angular/core';
import { Api, ApiError } from '../core/api.service';
import { Mt, Msg } from '../core/i18n/i18n';

/**
 * 改昵称 —— 账号里唯一能在「我的」页改的字段（换头像要选图上传统，见 core/upload）。
 *
 * 单独成一个组件而不是写进 MePage：MePage 已经贴着 500 行上限（同 me-tiles.ts / me-export.ts 的理由）。
 *
 * 表单放**弹框**里而不是行内展开：按钮要摆在头像卡那一行里（`:host{display:contents}` 让它成为
 * `.wrap` 的 flex 子项），而 `position:fixed` 的 `.modal` 与宿主所处的 flex 布局无关 ——
 * 行内展开的话，表单会被挤成那一行里的第三个格子。
 *
 * 保存成功后**不在这里改父组件的资料**：PUT 的回包只有 id/username/nickname/avatar/language
 * 五个字段（不是完整资料），由父组件收 `saved` 后回读一次，本地输入不当第二真值源。
 */
@Component({
  selector: 'app-me-nick',
  imports: [Mt],
  template: `
    <button class="btn ghost" type="button" (click)="open()">改昵称</button>

    @if (shown()) {
      <div class="backdrop" (click)="cancel()"></div>
      <div class="modal" role="dialog" aria-modal="true" aria-label="修改昵称">
        <header class="between">
          <b>修改昵称</b>
          <button class="btn ghost" type="button" (click)="cancel()">关闭</button>
        </header>
        <div class="modal-body">
          <label class="field">
            <span>昵称（最长 50 字）</span>
            <input
              class="input"
              autocomplete="off"
              maxlength="50"
              placeholder="请输入昵称"
              [value]="name()"
              (input)="onInput($event)"
            />
          </label>
          @if (msg()) {
            <div class="alert">{{ msg() | mt }}</div>
          }
          <button class="btn primary wide" type="button" [disabled]="busy()" (click)="save()">
            {{ busy() ? '保存中…' : '保存' }}
          </button>
        </div>
      </div>
    }
  `,
  styles: [
    `
      /* 宿主不占格子：按钮要成为父级 .wrap 的直接 flex 子项，
         否则它会多包一层、按钮与相邻按钮的间距对不上 */
      :host {
        display: contents;
      }
    `,
  ],
})
export class MeNick {
  private readonly api = inject(Api);

  /** 当前昵称（父组件给的是**服务端资料**里的值），打开弹框时用它预填 */
  readonly nickname = input('');

  /** 保存成功通知父组件回读资料 */
  readonly saved = output<void>();

  protected readonly shown = signal(false);
  protected readonly name = signal('');
  protected readonly busy = signal(false);
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg`；同信号的每个写入点都走这两态 */
  protected readonly msg = signal<Msg>('');

  protected open(): void {
    this.name.set(this.nickname());
    this.msg.set('');
    this.shown.set(true);
  }

  protected onInput(ev: Event): void {
    this.name.set((ev.target as HTMLInputElement).value);
    this.msg.set('');
  }

  protected cancel(): void {
    this.name.set('');
    this.msg.set('');
    this.shown.set(false);
  }

  protected save(): void {
    if (this.busy()) return;
    const v = this.name().trim();
    // 服务端 nickname 是 `nullable|max:50`：送空串会把昵称清成空，空值只能在这边挡住；
    // 50 与输入框 maxlength 同源，本地先挡一次，避免明知 422 还发请求。
    if (!v) return this.msg.set({ key: 'me.err_nick_required' });
    if (v.length > 50) return this.msg.set({ key: 'me.err_nick_too_long' });
    this.busy.set(true);
    this.msg.set('');
    this.api.updateProfile({ nickname: v }).subscribe({
      next: () => {
        this.busy.set(false);
        this.shown.set(false);
        this.saved.emit();
      },
      error: (e: ApiError) => {
        this.busy.set(false);
        this.msg.set(e.message);
      },
    });
  }
}
