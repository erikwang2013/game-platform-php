/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Api, ApiError, dt } from '../core/api.service';
import { exportBlob, exportCounts, exportName, saveBlob } from '../core/export-data';
import { Mt, Msg, T } from '../core/i18n/i18n';

/**
 * 「导出我的数据」（GDPR 数据可携带）卡片 —— 放在「我的」页、注销账号之前。
 *
 * 单独成一个组件而不是写进 MePage：当时 MePage 已是单文件 491 行（模板内联，me.html 尚未拆出），
 * 再塞一块就破 500 上限。原注释写的「已 488 行」在 git 历史里读不到 —— me.ts 的提交态只有
 * 290/292/450/491/492/349，故按 6cf4f77 的提交态更正：这是**当时的读数**，不是现状。先例见 me-tiles.ts。
 * 端点回的是**普通信封**不是文件 ⇒ 走 `api.exportData()` 取 JSON 再自己捏 Blob 落盘
 * （别照搬 admin 树那条按 content-type 分流的附件链路，理由见 core/export-data.ts）。
 *
 * 屏幕上的时刻与计数全部来自**服务端回包**（`exported_at` 与四类明细长度），本机时钟不进这句话。
 */
@Component({
  selector: 'app-me-export',
  imports: [Mt, T],
  template: `
    <div class="card">
      <h2>{{ 'me.export_title' | t }}</h2>
      <p class="hint">
        {{ 'me.export_hint_a' | t }}<b>{{ 'me.export_hint_b' | t }}</b
        >{{ 'me.export_hint_c' | t }}
        <b>{{ 'me.export_hint_d' | t }}</b
        >{{ 'me.export_hint_e' | t }}
      </p>

      @if (msg()) {
        <div class="alert" [class.ok]="ok()" [attr.role]="ok() ? 'status' : 'alert'">{{ msg() | mt }}</div>
      }

      <div class="acts">
        <button class="btn" type="button" [disabled]="busy()" (click)="run()">
          {{ busy() ? ('me.export_busy' | t) : ('me.export_download' | t) }}
        </button>
      </div>
    </div>
  `,
  styles: [
    `
      .card {
        margin-top: 22px;
      }
      h2 {
        margin: 0;
        font-size: 17px;
      }
      .hint {
        margin: 8px 0 0;
        font-size: 13px;
        color: var(--muted);
        line-height: 1.6;
        max-width: 62ch;
      }
      .alert {
        margin-top: 14px;
      }
      .acts {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
      }
    `,
  ],
})
export class MeExport {
  private readonly api = inject(Api);

  protected readonly busy = signal(false);
  protected readonly ok = signal(false);
  /**
   * 导出结果 —— 两态（键 / 服务端原文），见 `core/i18n/i18n.ts` 的 `Msg`。
   * ⚠ 成功那一句**只存键 + 三个参数**：文件名/服务端时刻/行数摘要都在异步回调里才算得出来，
   * 存拼好的句子就是把「导出那一刻的语言」冻在屏幕上（同 `core/i18n/dict/profile.ts` 的注释）。
   */
  protected readonly msg = signal<Msg>('');

  protected run(): void {
    if (this.busy()) return;
    this.busy.set(true);
    this.msg.set('');
    this.api.exportData().subscribe({
      next: (d) => {
        // 文件名只认服务端的 exported_at（本机时钟是第二真值源，见 exportName）
        const name = exportName(d.exported_at);
        saveBlob(exportBlob(d), name);
        this.ok.set(true);
        this.msg.set({
          key: 'me.export_done',
          params: { name, at: dt(d.exported_at), counts: exportCounts(d) },
        });
        this.busy.set(false);
      },
      error: (e: ApiError) => {
        // 服务端拒绝原因原样透出，不吞成「导出失败」
        this.ok.set(false);
        this.msg.set(e.message);
        this.busy.set(false);
      },
    });
  }
}
