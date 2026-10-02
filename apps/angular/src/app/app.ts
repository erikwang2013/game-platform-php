/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, HostListener, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { filter } from 'rxjs';
import { Api, Suggestion, isAuthed } from './core/api.service';
import { I18n, T } from './core/i18n/i18n';

interface NavItem {
  link: string[];
  frag: string;
  /** 词条键（不再是中文字面量）—— 模板里过 `t` 管道 */
  label: string;
  d: string;
}

const NAV: NavItem[] = [
  {
    link: ['/'],
    frag: '',
    label: 'nav.home',
    d: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
  },
  {
    link: ['/'],
    frag: 'games',
    label: 'nav.games',
    d: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
  },
  {
    link: ['/wallet'],
    frag: '',
    label: 'nav.wallet',
    d: 'M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM16 12h3',
  },
  {
    link: ['/me'],
    frag: 'notifications',
    label: 'nav.messages',
    d: 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
  },
  {
    link: ['/me'],
    frag: '',
    label: 'nav.me',
    d: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
  },
];

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, FormsModule, T],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  private readonly api = inject(Api);
  private readonly router = inject(Router);
  private readonly i18n = inject(I18n);

  protected readonly nav = NAV;
  protected readonly url = signal('/');
  protected readonly authed = signal(isAuthed());
  protected readonly q = signal('');
  protected readonly sugg = signal<Suggestion[]>([]);
  private timer?: ReturnType<typeof setTimeout>;

  /** 语言菜单：13 种平铺用母语名（用户看不懂当前界面语言时也得能选对） */
  protected readonly langs = this.i18n.langs;
  protected readonly lang = this.i18n.lang;
  protected readonly langOpen = signal(false);
  protected readonly native = computed(
    () => this.langs.find((l) => l.code === this.lang())?.native ?? '',
  );

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
        this.langOpen.set(false); // 点菜单里的链接跳转后不该留在屏幕上
      });
  }

  /**
   * 切界面语言。`use()` 同时落三处（信号 / `gp_language` / `document.lang|dir`，见 i18n.ts），
   * 其中 `gp_language` 就是 `session.ts` 拦截器读来发 `X-Language` 的那个键
   * ⇒ 界面文案与服务端响应文案一起变。**不 await**：en/zh 同步就绪，其余 11 种的译文到货后
   * 由表版本号标脏重绘（等它会让点击到生效之间卡一下）。
   */
  protected pickLang(code: string): void {
    this.langOpen.set(false);
    void this.i18n.use(code);
  }

  /** 开合语言菜单：阻止冒泡，否则下面那条 document 级监听会立刻把它收掉 */
  protected toggleLang(ev: Event): void {
    ev.stopPropagation();
    this.langOpen.set(!this.langOpen());
  }

  /**
   * 点页面任意处关闭语言菜单。
   *
   * 不复用管理端树的 `.lang-catch`（`position: fixed; inset: 0` 的透明遮罩）：那个写法的前提是
   * 「遮罩相对**视口**铺满」，而本树 `.topbar` 带 `backdrop-filter`（`_shell.scss`）——
   * 按规范它会成为 fixed 后代的包含块，遮罩就只盖住顶栏那一条，点下面的内容关不掉。
   * 换 document 级监听后没有这个前提，层叠顺序也不再参与判断。
   */
  @HostListener('document:click')
  protected closeLang(): void {
    this.langOpen.set(false);
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

  /**
   * 搜索框回车 → 搜索页。
   *
   * 落点是 `/search` 不是首页 `?keyword=`：首页那条走 `/game/list?keyword=`，服务端**只匹配
   * name**；`/api/v1/search` 匹配 name **或 description** ⇒ 简介里的词只有搜索页搜得到。
   * 首页的 keyword 过滤仍然可用（老链接/手输 URL），只是不再是搜索框的落点。
   */
  protected submit(): void {
    const s = this.q().trim();
    this.sugg.set([]);
    void this.router.navigate(['/search'], { queryParams: s ? { q: s } : {} });
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
