/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, effect, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';
import { FormModal } from './components/form-modal';
import { Api, Row } from './core/api.service';
import { Field, payload } from './core/crud';
import { I18n, T } from './core/i18n/i18n';
import { errText } from './core/util';

const PROFILE = '/admin/v1/profile';

/**
 * 个人资料字段 = `ProfileController::updateProfile` 的三个可写键（real_name/phone/email，其余键后端不看）。
 * 没有读接口（route.php 只注册了 PUT /profile）⇒ phone/email 没法预填。留空即**不提交**
 * （payload 的局部更新语义），所以空白框不会把已设的值清掉，hint 里写明这一点。
 */
const PROFILE_FIELDS: Field[] = [
  { name: 'real_name', label: 'profile.real_name', type: 'text' },
  { name: 'phone', label: 'profile.phone', type: 'text', hint: 'profile.blank_keeps' },
  { name: 'email', label: 'profile.email', type: 'text', hint: 'profile.blank_keeps' },
];

/**
 * 改密码字段 = `ProfileController::updatePassword` 的 validator（两个都必填、都收字符串）。
 * 强度规则（8-32 位含大小写与数字）**只进 hint 不在前端拦**：真值在服务端，
 * 两处规则漂移时被前端拦下的那次看起来像"按钮坏了"。
 */
const PASSWORD_FIELDS: Field[] = [
  { name: 'old_password', label: 'profile.old_password', type: 'password', required: true },
  {
    name: 'new_password',
    label: 'profile.new_password',
    type: 'password',
    required: true,
    hint: 'profile.password_hint',
  },
];

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
  imports: [RouterOutlet, RouterLink, T, FormModal],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  private readonly router = inject(Router);
  private readonly api = inject(Api);
  private readonly i18n = inject(I18n);

  protected readonly user = this.api.user;
  /** 移动端抽屉开关 */
  protected readonly menu = signal(false);

  /** 账号菜单开关（个人资料 / 修改密码 / 退出）—— 与语言菜单同一套遮罩 + 面板 */
  protected readonly userOpen = signal(false);

  protected readonly profileOpen = signal(false);
  protected readonly profileMode = signal<'profile' | 'password'>('profile');
  protected readonly profileTitle = signal('profile.tab_profile');
  protected readonly profileFields = signal<Field[]>(PROFILE_FIELDS);
  protected readonly profileValue = signal<Row | null>(null);
  protected readonly profileError = signal('');
  protected readonly profileSaving = signal(false);
  /** 成功回执（服务端 message 原文）：改密码/改资料的回执是后端文案，别自己编一句 */
  protected readonly note = signal('');

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
        { path: '/community', label: 'nav.community' },
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
      this.userOpen.set(false); // 账号菜单同理
    });
  }

  /** 打开个人中心弹框。mode 决定字段集与端点（改资料 PUT /profile、改密码 PUT /profile/password） */
  protected openProfile(mode: 'profile' | 'password'): void {
    this.userOpen.set(false);
    this.profileMode.set(mode);
    this.profileTitle.set(mode === 'password' ? 'profile.tab_password' : 'profile.tab_profile');
    this.profileFields.set(mode === 'password' ? PASSWORD_FIELDS : PROFILE_FIELDS);
    // 只预填拿得到的 real_name；phone/email 没有读端点，预填空串会被 payload 当成「没改」跳过
    this.profileValue.set(mode === 'profile' ? { real_name: this.user()?.real_name ?? '' } : null);
    this.profileError.set('');
    this.profileOpen.set(true);
  }

  protected closeProfile(): void {
    this.profileOpen.set(false);
    this.profileError.set('');
  }

  /**
   * 提交。改密码是全量（两个字段都必填）；改资料走局部更新 —— 与预填值相同的键不发，
   * 于是一个键都没改时是合法的空操作，不必打扰后端（同 CrudPage::submit）。
   */
  protected async submitProfile(v: Row): Promise<void> {
    const pwd = this.profileMode() === 'password';
    const body = pwd ? v : payload(PROFILE_FIELDS, this.profileValue() ?? {}, v);
    if (!pwd && !Object.keys(body).length) {
      this.closeProfile();
      return;
    }
    this.profileSaving.set(true);
    this.profileError.set('');
    try {
      const { message } = await this.api.envelope('PUT', pwd ? PROFILE + '/password' : PROFILE, body);
      this.closeProfile();
      this.flash(message || this.i18n.t(pwd ? 'profile.password_changed' : 'profile.saved'));
    } catch (e) {
      // 422（旧密码不对/强度不够）：服务端 message 留在框里，框不关，改完可重试
      this.profileError.set(errText(e));
    } finally {
      this.profileSaving.set(false);
    }
  }

  /** 成功横幅，几秒后自己消失（没有全局 toast，用顶栏下方一条 notice 就够） */
  private flash(text: string): void {
    this.note.set(text);
    setTimeout(() => this.note.set(''), 6000);
  }

  /** 退出：先让服务端吊销令牌（Api.logout 里失败也不拦），再回登录页 */
  protected async logout(): Promise<void> {
    this.userOpen.set(false);
    await this.api.logout();
    void this.router.navigate(['/login']);
  }
}
