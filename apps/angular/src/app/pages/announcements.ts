/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ModalFocus } from '../components/modal-focus';
import { Api, AnnouncementBrief, AnnouncementDetail, ApiError, dt } from '../core/api.service';
import { T } from '../core/i18n/i18n';

/**
 * 平台公告 —— 公开接口，未登录也能看。
 *
 * 后端 /announcement/list **不分页**（硬 limit 20、按 id 倒序），且列表项不含正文；
 * 正文只有 /announcement/detail/{hashid} 给，所以点开时再取一次。
 *
 * **URL 是弹框的唯一真值源**（`/announcements/:hashid` ↔ 打开对应公告），
 * 于是地址栏里那条链接可以直接发给别人（公开页，对方未登录也打得开）。
 * 由 URL 驱动而不是各存一份状态：否则「打开 A、再点 B」这类操作会让两者漂移。
 */
@Component({
  selector: 'app-announcements',
  imports: [ModalFocus, T],
  template: `
    <div class="between sect">
      <h2>{{ 'announcements.title' | t }}</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load()">
        {{ 'common.refresh' | t }}
      </button>
    </div>

    <div class="card">
      @if (loading()) {
        <div class="rows">
          @for (i of [1, 2, 3]; track i) {
            <div class="row"><div class="skeleton sk-row"></div></div>
          }
        </div>
      } @else if (error()) {
        <div class="state">
          <strong>{{ 'common.load_failed' | t }}</strong>
          <span>{{ error() }}</span>
          <button class="btn" type="button" (click)="load()">{{ 'common.retry' | t }}</button>
        </div>
      } @else if (!items().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ 'announcements.empty_title' | t }}</strong>
          <span>{{ 'announcements.empty_hint' | t }}</span>
        </div>
      } @else {
        <div class="rows">
          @for (a of items(); track a.id) {
            <button class="row asbtn" type="button" (click)="view(a.id)">
              <div class="grow">
                <div class="t">{{ a.title }}</div>
                <div class="s">{{ dt(a.created_at) }}{{ a.type ? ' · ' + a.type : '' }}</div>
              </div>
              <span class="badge">{{ 'common.view' | t }}</span>
            </button>
          }
        </div>
      }
    </div>

    @if (cur() || detailErr() || detailLoading()) {
      <div class="backdrop" (click)="back()"></div>
      <!-- uiModal：开框聚焦首个可聚焦元素 / Tab 圈在框内 / Esc 关框 / 关框把焦点还给打开者 -->
      <div
        class="modal"
        uiModal
        (dismiss)="back()"
        role="dialog"
        aria-modal="true"
        aria-label="{{ 'announcements.detail_title' | t }}"
      >
        <header class="between">
          <b>{{ cur()?.title || ('announcements.fallback_title' | t) }}</b>
          <button class="btn ghost" type="button" (click)="back()">{{ 'common.close' | t }}</button>
        </header>
        <div class="modal-body">
          @if (detailLoading()) {
            <div class="state"><span class="spin"></span> {{ 'common.loading' | t }}</div>
          } @else if (detailErr()) {
            <div class="alert">{{ detailErr() }}</div>
          } @else if (cur(); as d) {
            <div class="s muted">{{ dt(d.created_at) }}{{ d.type ? ' · ' + d.type : '' }}</div>
            <div class="body">{{ d.content }}</div>
          }
        </div>
      </div>
    }
  `,
  styles: [
    `
      .sect {
        margin: 0 0 14px;
      }
      .sect h2 {
        margin: 0;
        font-size: 17px;
      }
      .sk-row {
        height: 16px;
        width: 70%;
        border-radius: 8px;
      }
      .asbtn {
        width: 100%;
        background: transparent;
        border: 0;
        border-bottom: 1px solid var(--line);
        color: inherit;
        text-align: left;
        cursor: pointer;
        font: inherit;
        transition: background var(--t-fast) var(--ease);
      }
      .asbtn:hover {
        background: var(--surface-2);
      }
      .modal-body .body {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        font-size: 14px;
        line-height: 1.7;
      }
    `,
  ],
})
export class AnnouncementsPage {
  private readonly api = inject(Api);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly items = signal<AnnouncementBrief[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal('');
  protected readonly dt = dt;

  protected readonly cur = signal<AnnouncementDetail | null>(null);
  protected readonly detailLoading = signal(false);
  protected readonly detailErr = signal('');

  constructor() {
    this.load();
    // 深链：/announcements/<hashid> 打开对应弹框，返回列表 URL 时收起。
    // 只读 URL 不反向写信号，所以浏览器前进/后退也能正确地开或关。
    this.route.paramMap.pipe(takeUntilDestroyed()).subscribe((p) => {
      const id = p.get('hashid');
      if (id) this.open(id);
      else this.clear();
    });
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set('');
    this.api.announcements().subscribe({
      next: (r) => {
        this.items.set(r.list ?? []);
        this.loading.set(false);
      },
      error: (e: ApiError) => {
        this.error.set(e.message);
        this.loading.set(false);
      },
    });
  }

  /** 点列表行：只改 URL，弹框由上面的 paramMap 订阅打开（URL 是唯一真值源） */
  protected view(id: string): void {
    void this.router.navigate(['/announcements', id]);
  }

  /** 关闭 / 点遮罩：同样是改 URL，让地址栏与界面一起回到列表 */
  protected back(): void {
    void this.router.navigate(['/announcements']);
  }

  /** 正在拉的那条（弹框后面还能点别的行，回包要按它对号入座，别把 A 的正文挂到 B 的 URL 下） */
  private loadingId = '';

  /** 列表没有正文，展开时单取一次详情 */
  private open(id: string): void {
    if (this.cur()?.id === id) return;
    this.loadingId = id;
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(true);
    this.api.announcementDetail(id).subscribe({
      next: (d) => {
        if (this.loadingId !== id) return;
        this.cur.set(d);
        this.detailLoading.set(false);
      },
      error: (e: ApiError) => {
        if (this.loadingId !== id) return;
        this.detailErr.set(e.message);
        this.detailLoading.set(false);
      },
    });
  }

  private clear(): void {
    // 同时作废在途请求：否则关掉之后回包一到，弹框会自己又弹回来
    this.loadingId = '';
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(false);
  }
}
