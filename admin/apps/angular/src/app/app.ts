/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, effect, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';
import { Auth } from './core/auth.service';
import { I18n, T } from './core/i18n/i18n';

interface NavItem {
  path: string;
  /** i18n 键（不是译文）：文案在模板里过 `| t`，切语言即重绘 */
  label: string;
}
interface NavGroup {
  section: string;
  items: NavItem[];
}

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, T],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  private readonly router = inject(Router);
  private readonly auth = inject(Auth);
  private readonly i18n = inject(I18n);

  protected readonly user = this.auth.user;
  /** 移动端抽屉开关 */
  protected readonly menu = signal(false);

  /** 语言菜单开关 + 13 种语言（母语名，与两棵 flutter 同一份清单） */
  protected readonly langOpen = signal(false);
  protected readonly langs = this.i18n.langs;
  protected readonly lang = this.i18n.lang;
  /** 按钮上显示当前语言的**母语名**（英文界面下也要认得「简体中文」） */
  protected readonly native = computed(
    () => this.langs.find((l) => l.code === this.lang())?.native ?? '',
  );

  protected pickLang(code: string): void {
    this.i18n.use(code);
    this.langOpen.set(false);
  }

  protected readonly groups: NavGroup[] = [
    {
      section: 'nav.section.overview',
      items: [
        { path: '/dashboard', label: 'nav.dashboard' },
        { path: '/analytics', label: 'nav.analytics' },
      ],
    },
    {
      section: 'nav.section.ops',
      items: [
        { path: '/users', label: 'nav.users' },
        { path: '/games', label: 'nav.games' },
        { path: '/content', label: 'nav.content' },
        { path: '/marketing', label: 'nav.marketing' },
      ],
    },
    {
      section: 'nav.section.money',
      items: [
        { path: '/finance', label: 'nav.finance' },
        { path: '/risk', label: 'nav.risk' },
      ],
    },
    {
      section: 'nav.section.support',
      items: [
        { path: '/support', label: 'nav.support' },
        { path: '/infra', label: 'nav.infra' },
        // 「管理员」= 后台账号（/admin/v1/user）；「用户管理」是 C 端平台用户，两者不是一个模块
        { path: '/admins', label: 'nav.admins' },
        { path: '/settings', label: 'nav.settings' },
      ],
    },
  ];

  private readonly items: NavItem[] = this.groups.flatMap((g) => g.items);

  private readonly url = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map((e) => e.urlAfterRedirects),
    ),
    { initialValue: this.router.url },
  );

  /** 登录页不套后台外壳 */
  protected readonly chrome = computed(() => !this.url().startsWith('/login'));

  /** 面包屑：最长前缀匹配，子路由也能落到正确的分组 */
  protected readonly crumb = computed(() => {
    const url = this.url();
    const hit = [...this.items]
      .filter((i) => url.startsWith(i.path))
      .sort((a, b) => b.path.length - a.path.length)[0];
    if (!hit) return { section: 'nav.section.overview', label: 'nav.dashboard' };
    const group = this.groups.find((g) => g.items.includes(hit));
    return { section: group?.section ?? 'nav.section.overview', label: hit.label };
  });

  constructor() {
    effect(() => {
      this.url();
      this.menu.set(false); // 换页时收起移动端抽屉
      this.langOpen.set(false); // 语言菜单同理（点菜单里的链接跳转后不该留在屏幕上）
    });
  }

  protected logout(): void {
    this.auth.signOut();
  }
}
