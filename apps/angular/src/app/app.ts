/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { filter } from 'rxjs';
import { Api, Suggestion, isAuthed } from './core/api.service';

interface NavItem {
  link: string[];
  frag: string;
  label: string;
  d: string;
}

const NAV: NavItem[] = [
  {
    link: ['/'],
    frag: '',
    label: '首页',
    d: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
  },
  {
    link: ['/'],
    frag: 'games',
    label: '游戏',
    d: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
  },
  {
    link: ['/wallet'],
    frag: '',
    label: '钱包',
    d: 'M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM16 12h3',
  },
  {
    link: ['/me'],
    frag: 'notifications',
    label: '消息',
    d: 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
  },
  {
    link: ['/me'],
    frag: '',
    label: '我的',
    d: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
  },
];

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, FormsModule],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  private readonly api = inject(Api);
  private readonly router = inject(Router);

  protected readonly nav = NAV;
  protected readonly url = signal('/');
  protected readonly authed = signal(isAuthed());
  protected readonly q = signal('');
  protected readonly sugg = signal<Suggestion[]>([]);
  private timer?: ReturnType<typeof setTimeout>;

  constructor() {
    this.router.events
      .pipe(
        filter((e): e is NavigationEnd => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe((e) => {
        this.url.set(e.urlAfterRedirects);
        this.authed.set(isAuthed());
        this.sugg.set([]);
      });
  }

  protected active(n: NavItem): boolean {
    const [path = '/'] = this.url().split('?');
    const [upath, ufrag = ''] = path.split('#');
    return upath === n.link[0] && ufrag === n.frag;
  }

  /** 搜索建议：250ms 防抖，避免逐字打请求 */
  protected onQuery(v: string): void {
    this.q.set(v);
    clearTimeout(this.timer);
    const s = v.trim();
    if (!s) {
      this.sugg.set([]);
      return;
    }
    this.timer = setTimeout(() => {
      this.api.gameSuggest(s).subscribe({
        next: (r) => {
          if (this.q().trim() === s) this.sugg.set(r.suggestions ?? []);
        },
        error: () => this.sugg.set([]),
      });
    }, 250);
  }

  protected submit(): void {
    const s = this.q().trim();
    this.sugg.set([]);
    void this.router.navigate(['/'], { queryParams: s ? { keyword: s } : {} });
  }

  protected open(id: string): void {
    this.q.set('');
    this.sugg.set([]);
    void this.router.navigate(['/game', id]);
  }

  protected signout(): void {
    this.api.logout();
    this.authed.set(false);
    void this.router.navigate(['/login']);
  }
}
