/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Api, AnnouncementBrief, AnnouncementDetail, ApiError, dt } from '../core/api.service';

/**
 * 平台公告 —— 公开接口，未登录也能看。
 *
 * 后端 /announcement/list **不分页**（硬 limit 20、按 id 倒序），且列表项不含正文；
 * 正文只有 /announcement/detail/{hashid} 给，所以点开时再取一次。
 */
@Component({
  selector: 'app-announcements',
  template: `
    <div class="between sect">
      <h2>平台公告</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load()">刷新</button>
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
          <strong>加载失败</strong>
          <span>{{ error() }}</span>
          <button class="btn" type="button" (click)="load()">重试</button>
        </div>
      } @else if (!items().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>暂无公告</strong>
          <span>平台活动与维护通知会发布在这里</span>
        </div>
      } @else {
        <div class="rows">
          @for (a of items(); track a.id) {
            <button class="row asbtn" type="button" (click)="open(a)">
              <div class="grow">
                <div class="t">{{ a.title }}</div>
                <div class="s">{{ dt(a.created_at) }}{{ a.type ? ' · ' + a.type : '' }}</div>
              </div>
              <span class="badge">查看</span>
            </button>
          }
        </div>
      }
    </div>

    @if (cur() || detailErr() || detailLoading()) {
      <div class="backdrop" (click)="close()"></div>
      <div class="modal" role="dialog" aria-modal="true" aria-label="公告详情">
        <header class="between">
          <b>{{ cur()?.title || '公告' }}</b>
          <button class="btn ghost" type="button" (click)="close()">关闭</button>
        </header>
        <div class="modal-body">
          @if (detailLoading()) {
            <div class="state"><span class="spin"></span> 加载中…</div>
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

  protected readonly items = signal<AnnouncementBrief[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal('');
  protected readonly dt = dt;

  protected readonly cur = signal<AnnouncementDetail | null>(null);
  protected readonly detailLoading = signal(false);
  protected readonly detailErr = signal('');

  constructor() {
    this.load();
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

  /** 列表没有正文，展开时单取一次详情 */
  protected open(a: AnnouncementBrief): void {
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(true);
    this.api.announcementDetail(a.id).subscribe({
      next: (d) => {
        this.cur.set(d);
        this.detailLoading.set(false);
      },
      error: (e: ApiError) => {
        this.detailErr.set(e.message);
        this.detailLoading.set(false);
      },
    });
  }

  protected close(): void {
    this.cur.set(null);
    this.detailErr.set('');
    this.detailLoading.set(false);
  }
}
