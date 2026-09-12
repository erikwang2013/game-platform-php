/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, effect, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';
import { Auth } from './core/auth.service';

interface NavItem {
  path: string;
  label: string;
}
interface NavGroup {
  section: string;
  items: NavItem[];
}

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  private readonly router = inject(Router);
  private readonly auth = inject(Auth);

  protected readonly user = this.auth.user;
  /** 移动端抽屉开关 */
  protected readonly menu = signal(false);

  protected readonly groups: NavGroup[] = [
    {
      section: '概览',
      items: [
        { path: '/dashboard', label: '仪表盘' },
        { path: '/analytics', label: '数据分析' },
      ],
    },
    {
      section: '运营',
      items: [
        { path: '/users', label: '用户管理' },
        { path: '/games', label: '游戏管理' },
        { path: '/content', label: '内容运营' },
        { path: '/marketing', label: '营销中心' },
      ],
    },
    {
      section: '资金与风控',
      items: [
        { path: '/finance', label: '财务中心' },
        { path: '/risk', label: '风险控制' },
      ],
    },
    {
      section: '支撑',
      items: [
        { path: '/support', label: '工单报表' },
        { path: '/infra', label: '基础设施' },
        { path: '/settings', label: '系统设置' },
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
    if (!hit) return { section: '概览', label: '仪表盘' };
    const group = this.groups.find((g) => g.items.includes(hit));
    return { section: group?.section ?? '概览', label: hit.label };
  });

  constructor() {
    effect(() => {
      this.url();
      this.menu.set(false); // 换页时收起移动端抽屉
    });
  }

  protected logout(): void {
    this.auth.signOut();
  }
}
