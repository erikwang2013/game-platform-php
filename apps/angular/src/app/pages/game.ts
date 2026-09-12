/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Api, ApiError, GameDetail, LaunchResult, isAuthed } from '../core/api.service';

@Component({
  selector: 'app-game',
  imports: [RouterLink],
  template: `
    <a class="back muted" routerLink="/">← 返回大厅</a>

    @if (loading()) {
      <div class="card detail">
        <div class="skeleton sk-cover"></div>
        <div class="info">
          <div class="skeleton sk-line w60"></div>
          <div class="skeleton sk-line w40"></div>
          <div class="skeleton sk-line"></div>
        </div>
      </div>
    } @else if (error()) {
      <div class="card state">
        <strong>加载失败</strong>
        <span>{{ error() }}</span>
        <button class="btn" type="button" (click)="load()">重试</button>
      </div>
    } @else if (game(); as g) {
      <div class="card detail">
        <div class="cover">
          @if (g.cover_image) {
            <img [src]="g.cover_image" [alt]="g.name" />
          } @else {
            <span class="ph">{{ g.name.charAt(0) }}</span>
          }
        </div>
        <div class="info">
          <span class="label">{{ g.type || 'GAME' }}</span>
          <h1>{{ g.name }}</h1>
          <div class="wrap">
            @if (g.platform) {
              <span class="badge">{{ g.platform }}</span>
            }
            @if (g.region) {
              <span class="badge">{{ g.region }}</span>
            }
            @if (g.sdk_version) {
              <span class="badge">SDK {{ g.sdk_version }}</span>
            }
          </div>
          @if (g.description) {
            <p class="muted desc">{{ g.description }}</p>
          }
          <button
            class="btn primary wide go"
            type="button"
            [disabled]="launching()"
            (click)="launch()"
          >
            {{ launching() ? '正在启动…' : '开始游戏' }}
          </button>
          @if (!authed()) {
            <span class="muted hint">未登录，点击将先跳转登录</span>
          }
          @if (launchError()) {
            <div class="alert">{{ launchError() }}</div>
          }
        </div>
      </div>

      @if (launched(); as l) {
        <div class="card launch">
          <div class="between">
            <span class="label">已启动</span>
            <span class="badge on">{{ l.type || 'session' }}</span>
          </div>
          <div class="kv">
            <span class="muted">会话 ID</span>
            <span class="mono">{{ l.session_id }}</span>
          </div>
          <div class="kv">
            <span class="muted">游戏入口</span>
            <a class="mono link" [href]="l.api_endpoint" target="_blank" rel="noopener">
              {{ l.api_endpoint }}
            </a>
          </div>
          <p class="muted hint">会话有效期 5 分钟，请在游戏端尽快完成接入。</p>
        </div>
      }

      @if (g.currencies.length) {
        <h2 class="h2">支持币种</h2>
        <div class="card">
          <div class="rows">
            @for (c of g.currencies; track c.id) {
              <div class="row">
                <span class="sym">{{ c.symbol || c.name.charAt(0) }}</span>
                <div class="grow">
                  <div class="t">{{ c.name }}</div>
                  <div class="s">汇率 {{ c.exchange_rate }}</div>
                </div>
                @if (c.spread_pct !== undefined && c.spread_pct !== null) {
                  <span class="badge">点差 {{ c.spread_pct }}%</span>
                }
              </div>
            }
          </div>
        </div>
      }

      <h2 class="h2">接入信息</h2>
      <div class="card">
        <div class="kv">
          <span class="muted">接口地址</span>
          <span class="mono">{{ g.api_endpoint || '—' }}</span>
        </div>
        <div class="kv">
          <span class="muted">游戏标识</span>
          <span class="mono">{{ g.slug }}</span>
        </div>
        <div class="kv">
          <span class="muted">游戏 ID</span>
          <span class="mono">{{ g.id }}</span>
        </div>
      </div>
    }
  `,
  styles: [
    `
      .back {
        display: inline-block;
        margin-bottom: 14px;
        font-size: 13px;
      }
      .detail {
        display: flex;
        flex-direction: column;
        gap: 18px;
      }
      .cover {
        aspect-ratio: 16 / 10;
        border-radius: var(--radius-sm);
        overflow: hidden;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.22), rgba(34, 211, 238, 0.14));
        display: flex;
        align-items: center;
        justify-content: center;
        flex: none;
      }
      .cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .cover .ph {
        font-size: 44px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.5);
      }
      .info {
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
      }
      .desc {
        font-size: 14px;
        line-height: 1.6;
      }
      .go {
        margin-top: 4px;
      }
      .hint {
        font-size: 12px;
      }
      .launch {
        margin-top: 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
      }
      .kv {
        display: flex;
        align-items: baseline;
        gap: 12px;
        font-size: 13px;
      }
      .kv > span:last-child {
        flex: 1;
        min-width: 0;
        overflow-wrap: anywhere;
        text-align: right;
      }
      .link {
        color: var(--accent-2);
      }
      .h2 {
        margin: 26px 0 12px;
        font-size: 15px;
      }
      .sym {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: var(--grad);
        color: #0b0d17;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: none;
      }
      .sk-cover {
        aspect-ratio: 16 / 10;
      }
      .sk-line {
        height: 16px;
        border-radius: 8px;
      }
      .w60 {
        width: 60%;
      }
      .w40 {
        width: 40%;
      }
      @media (min-width: 768px) {
        .detail {
          flex-direction: row;
          gap: 28px;
          align-items: flex-start;
        }
        .cover {
          width: 420px;
        }
        .info {
          flex: 1;
          padding-top: 6px;
        }
        .go {
          align-self: flex-start;
          width: auto;
          min-width: 200px;
        }
      }
    `,
  ],
})
export class GamePage {
  private readonly api = inject(Api);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly game = signal<GameDetail | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal('');
  protected readonly launching = signal(false);
  protected readonly launchError = signal('');
  protected readonly launched = signal<LaunchResult | null>(null);
  protected readonly authed = signal(isAuthed());

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed()).subscribe(() => this.load());
  }

  protected load(): void {
    const id = this.route.snapshot.paramMap.get('hashid') ?? '';
    this.loading.set(true);
    this.error.set('');
    this.launched.set(null);
    this.api.gameDetail(id).subscribe({
      next: (g) => {
        this.game.set(g);
        this.loading.set(false);
      },
      error: (e: ApiError) => {
        this.error.set(e.message);
        this.game.set(null);
        this.loading.set(false);
      },
    });
  }

  protected launch(): void {
    const g = this.game();
    if (!g || this.launching()) return;
    this.launchError.set('');
    if (!isAuthed()) {
      void this.router.navigate(['/login'], { queryParams: { redirect: `/game/${g.id}` } });
      return;
    }
    this.launching.set(true);
    this.api.launchGame(g.id).subscribe({
      next: (r) => {
        this.launched.set(r);
        this.launching.set(false);
      },
      error: (e: ApiError) => {
        this.launchError.set(e.message);
        this.launching.set(false);
        // 会话失效时 api 层已跳登录，这里仅刷新本地登录态
        this.authed.set(isAuthed());
      },
    });
  }
}
