/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Api, ApiError, SearchGame } from '../core/api.service';

/** 一页条数。回包**没有 last_page**，末页只能按它自己算 */
const PER_PAGE = 20;

/**
 * 全局搜索 —— 公开接口，未登录也能搜。
 *
 * 与首页 `?keyword=` **不是同一条路**：那条走 `/game/list?keyword=`，服务端只匹配 name；
 * 这里走 `/api/v1/search`，匹配 name **或 description** ⇒
 * 「简介里有、名字里没有」的词只有这个页面搜得到 —— 这就是它存在的理由。
 * 外壳（app.html）那条搜索框回车后也落到这里。
 *
 * 只搜游戏：该端点在公开组，user 分支等于未鉴权的用户批量导出，服务端入口已把它强制成 game
 * （详见 `Api.searchGames` 的注释）。客户端这边**别去试** `type=user`。
 *
 * q / page 都放 URL query 上：结果页可直接分享、刷新、回退。
 */
@Component({
  selector: 'app-search',
  imports: [RouterLink],
  template: `
    <div class="between sect">
      <h2>搜索</h2>
      @if (q()) {
        <span class="badge accent">{{ total() }} 个结果</span>
      }
    </div>

    <!-- 树里无 [formGroup] 的表单一律走 (submit) + preventDefault：
         ngSubmit 由 NgForm 提供，只有 FormsModule 导出，这里不引它（同 security.ts 的注释） -->
    <form class="sbar" (submit)="go(box.value, $event)" novalidate>
      <div class="search">
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.5-3.5" />
        </svg>
        <!-- 不做双向绑定：打字时回写 value 会让光标跳到末尾。
             这里只在 q 变化（= URL 变化）时刷新输入框里的字 -->
        <input
          #box
          type="search"
          placeholder="搜索游戏名称或简介"
          aria-label="搜索游戏"
          autocomplete="off"
          [value]="q()"
        />
      </div>
      <button class="btn primary" type="submit" [disabled]="loading()">搜索</button>
    </form>

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
          <button class="btn" type="button" (click)="retry()">重试</button>
        </div>
      } @else if (!q()) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>输入关键词开始搜索</strong>
          <span>按游戏名称与简介匹配</span>
        </div>
      } @else if (!items().length) {
        <div class="state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ page() > 1 ? '这一页没有结果' : '没有找到与「' + q() + '」相关的游戏' }}</strong>
          <span>{{ page() > 1 ? '这个关键词的结果没有这么多页' : '换个关键词试试' }}</span>
          @if (page() > 1) {
            <button class="btn" type="button" (click)="to(1)">回到第 1 页</button>
          }
        </div>
      } @else {
        <div class="rows">
          @for (g of items(); track g.id) {
            <a class="row" [routerLink]="['/game', g.id]">
              <div class="grow">
                <div class="t">{{ g.name }}</div>
                <div class="s">{{ g.description || g.slug }}</div>
              </div>
              <span class="badge">查看</span>
            </a>
          }
        </div>
        @if (lastPage() > 1) {
          <div class="pager">
            <button class="btn" type="button" [disabled]="page() <= 1" (click)="to(page() - 1)">
              上一页
            </button>
            <span class="s muted">{{ page() }} / {{ lastPage() }}</span>
            <button
              class="btn"
              type="button"
              [disabled]="page() >= lastPage()"
              (click)="to(page() + 1)"
            >
              下一页
            </button>
          </div>
        }
      }
    </div>
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
      .sbar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
      }
      .sbar .search {
        flex: 1;
      }
      .sk-row {
        height: 16px;
        width: 70%;
        border-radius: 8px;
      }
      .pager {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding-top: 14px;
      }
    `,
  ],
})
export class SearchPage {
  private readonly api = inject(Api);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly q = signal('');
  protected readonly items = signal<SearchGame[]>([]);
  protected readonly loading = signal(false);
  protected readonly error = signal('');
  protected readonly page = signal(1);
  protected readonly total = signal(0);

  /** 回包没有 last_page ⇒ 自己算。用本地常量而不是回包里的 per_page：值同源，少一处可变 */
  protected readonly lastPage = computed(() => Math.max(1, Math.ceil(this.total() / PER_PAGE)));

  constructor() {
    // queryParamMap 同时覆盖「首次进入」与「页面内翻页/改词」两种情况
    this.route.queryParamMap.pipe(takeUntilDestroyed()).subscribe((p) => {
      const q = (p.get('q') ?? '').trim();
      this.q.set(q);
      if (!q) {
        // 空关键词不打请求（服务端也会直接回空列表，但没必要来回一趟）
        this.items.set([]);
        this.total.set(0);
        this.error.set('');
        this.loading.set(false);
        return;
      }
      this.load(q, Math.max(1, Number(p.get('page') ?? 1) || 1));
    });
  }

  /** 提交。**不带 page** ⇒ 换词与原页回车都回到第 1 页（同一关键词重复回车时 URL 相同，路由不重发请求，天然幂等） */
  protected go(raw: string, ev: Event): void {
    ev.preventDefault();
    const v = raw.trim();
    void this.router.navigate(['/search'], { queryParams: v ? { q: v } : {} });
  }

  protected to(p: number): void {
    void this.router.navigate(['/search'], { queryParams: { q: this.q(), page: p } });
  }

  protected retry(): void {
    this.load(this.q(), this.page());
  }

  private load(q: string, page: number): void {
    this.loading.set(true);
    this.error.set('');
    this.api.searchGames(q, page, PER_PAGE).subscribe({
      next: (r) => {
        this.items.set(r.list ?? []);
        this.total.set(Number(r.total) || 0);
        this.page.set(Number(r.page) || page);
        this.loading.set(false);
      },
      error: (e: ApiError) => {
        this.items.set([]);
        this.total.set(0);
        this.error.set(e.message);
        this.loading.set(false);
      },
    });
  }
}
