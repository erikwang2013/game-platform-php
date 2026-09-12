/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Api, ApiError, Game, PlatformStats } from '../core/api.service';

@Component({
  selector: 'app-home',
  imports: [RouterLink],
  template: `
    <section class="card hero">
      <span class="label">AURORA PLATFORM</span>
      <h1>发现你的下一款游戏</h1>
      <p class="muted">多平台游戏大厅 · 实时开局 · 统一钱包</p>
    </section>

    <div class="stats">
      @for (c of cards(); track c.k) {
        <div class="card lift stat">
          <span class="label">{{ c.k }}</span>
          <strong>{{ c.v }}</strong>
        </div>
      }
    </div>

    <div class="between sect" id="games">
      <h2>游戏大厅</h2>
      @if (keyword()) {
        <span class="badge accent">“{{ keyword() }}”</span>
      }
    </div>

    @if (loading()) {
      <div class="grid">
        @for (i of [1, 2, 3, 4, 5, 6, 7, 8]; track i) {
          <div class="skeleton sk"></div>
        }
      </div>
    } @else if (error()) {
      <div class="card state">
        <strong>加载失败</strong>
        <span>{{ error() }}</span>
        <button class="btn" type="button" (click)="reload()">重试</button>
      </div>
    } @else if (!games().length) {
      <div class="card state">
        <strong>暂无游戏</strong>
        <span>{{ keyword() ? '没有匹配的游戏，换个关键词试试' : '平台还没有上架游戏' }}</span>
      </div>
    } @else {
      <div class="grid">
        @for (g of games(); track g.id) {
          <a class="card lift game" [routerLink]="['/game', g.id]">
            <div class="cover">
              @if (g.cover_image) {
                <img [src]="g.cover_image" [alt]="g.name" loading="lazy" />
              } @else {
                <span class="ph">{{ g.name.charAt(0) }}</span>
              }
              @if (g.platform) {
                <span class="badge">{{ g.platform }}</span>
              }
            </div>
            <div class="meta">
              <span class="t">{{ g.name }}</span>
              <span class="s">{{ g.categories[0]?.name || g.type }}</span>
            </div>
          </a>
        }
      </div>
      @if (page() < lastPage()) {
        <div class="more">
          <button class="btn" type="button" [disabled]="loadingMore()" (click)="loadMore()">
            {{ loadingMore() ? '加载中…' : '加载更多' }}
          </button>
        </div>
      }
    }
  `,
  styles: [
    `
      .hero {
        padding: 30px 28px;
        background:
          radial-gradient(120% 160% at 0% 0%, rgba(124, 58, 237, 0.22), transparent 55%),
          radial-gradient(90% 140% at 100% 0%, rgba(34, 211, 238, 0.16), transparent 60%),
          var(--panel);
      }
      .hero h1 {
        margin: 8px 0 6px;
        font-size: 26px;
        letter-spacing: -0.02em;
      }
      .hero p {
        margin: 0;
        font-size: 13px;
      }
      .stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-top: 16px;
      }
      .stat {
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .stat strong {
        font-size: 22px;
        font-variant-numeric: tabular-nums;
      }
      .sect {
        margin: 26px 0 14px;
      }
      .sect h2 {
        margin: 0;
        font-size: 17px;
      }
      .grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
      }
      .sk {
        height: 208px;
      }
      .game {
        padding: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        text-decoration: none;
        color: inherit;
      }
      .cover {
        position: relative;
        aspect-ratio: 16 / 10;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.22), rgba(34, 211, 238, 0.14));
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .cover .ph {
        font-size: 34px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.55);
      }
      .cover .badge {
        position: absolute;
        top: 10px;
        left: 10px;
      }
      .meta {
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 12px 14px 14px;
      }
      .meta .t {
        font-weight: 650;
        font-size: 14px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .meta .s {
        font-size: 12px;
        color: var(--muted);
      }
      .more {
        display: flex;
        justify-content: center;
        margin-top: 18px;
      }
      @media (min-width: 768px) {
        .hero {
          padding: 40px 36px;
        }
        .hero h1 {
          font-size: 32px;
        }
        .stats {
          grid-template-columns: repeat(4, 1fr);
        }
        .grid {
          grid-template-columns: repeat(4, 1fr);
          gap: 18px;
        }
      }
    `,
  ],
})
export class HomePage {
  private readonly api = inject(Api);
  private readonly route = inject(ActivatedRoute);

  protected readonly stats = signal<PlatformStats | null>(null);
  protected readonly games = signal<Game[]>([]);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly loadingMore = signal(false);
  protected readonly error = signal('');
  protected readonly keyword = signal('');

  protected readonly cards = computed(() => {
    const s = this.stats();
    return [
      { k: 'GAMES', v: s ? s.total_games : '—' },
      { k: 'PLAYERS', v: s ? s.total_users : '—' },
      { k: 'TODAY PLAYS', v: s ? s.today_game_plays : '—' },
      { k: '7D ACTIVE', v: s ? s.active_users_7d : '—' },
    ];
  });

  constructor() {
    this.api.platformStats().subscribe({
      next: (s) => this.stats.set(s),
      error: () => this.stats.set(null),
    });
    this.route.queryParamMap.pipe(takeUntilDestroyed()).subscribe((p) => {
      this.keyword.set((p.get('keyword') ?? '').trim());
      this.load(1);
    });
  }

  private load(p: number): void {
    const first = p === 1;
    (first ? this.loading : this.loadingMore).set(true);
    this.error.set('');
    const query: Record<string, string | number> = { page: p, per_page: 12 };
    if (this.keyword()) query['keyword'] = this.keyword();
    this.api.gameList(query).subscribe({
      next: (r) => {
        this.games.set(first ? r.items : [...this.games(), ...r.items]);
        this.page.set(r.page);
        this.lastPage.set(r.last_page);
        this.loading.set(false);
        this.loadingMore.set(false);
      },
      error: (e: ApiError) => {
        this.error.set(e.message);
        this.loading.set(false);
        this.loadingMore.set(false);
      },
    });
  }

  protected reload(): void {
    this.load(1);
  }

  protected loadMore(): void {
    this.load(this.page() + 1);
  }
}
