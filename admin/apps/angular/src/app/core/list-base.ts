/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Directive, OnInit, inject, signal } from '@angular/core';
import { Page } from './api.service';
import { I18n } from './i18n/i18n';
import { errText } from './util';

/**
 * 列表页公共状态机：加载中 / 出错 / 空 / 分页。
 * 十几个列表页重复的只有这套东西，抽干它，页面只管 fetch()。
 *
 * ⚠ ngOnInit 里的首次 load() 不能省：原先只有 pick()（切标签）和「查询」按钮触发 load，
 * 于是每个列表页首屏都停在 loading 的初值 true 上显示「加载中…」而**一个请求都不发**
 * （实测 /users、/games 进页面 0 个 /admin/v1 请求，点「查询」才发出）。
 * 放在 ngOnInit 而不是构造函数：子类的 fetch() 要读 this.paths 等字段初始化结果，
 * 基类构造函数里调会读到 undefined。生命周期钩子在构造与输入绑定之后跑，正合适。
 */
// @Directive()（无 selector）：抽象组件基类要用生命周期钩子就必须带装饰器，
// 否则 NG2007「Class is using Angular features but is not decorated」
@Directive()
export abstract class ListBase<T> implements OnInit {
  /** 页面侧的查表入口（壳/组件的文案在模板里过 `| t`，类里的按钮名、confirm 文案走这里） */
  protected readonly i18n = inject(I18n);

  readonly rows = signal<T[]>([]);
  readonly loading = signal(true);
  readonly error = signal('');
  readonly page = signal(1);
  readonly total = signal(0);
  readonly pageSize = 20;
  readonly keyword = signal('');

  ngOnInit(): void {
    void this.load();
  }

  protected abstract fetch(): Promise<Page<T>>;

  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set('');
    try {
      const res = await this.fetch();
      this.rows.set(res.list ?? []);
      this.total.set(res.total ?? 0);
    } catch (e) {
      this.error.set(errText(e));
      this.rows.set([]);
    } finally {
      this.loading.set(false);
    }
  }

  search(): void {
    this.page.set(1);
    void this.load();
  }

  get pages(): number {
    return Math.max(1, Math.ceil(this.total() / this.pageSize));
  }

  go(p: number): void {
    const next = Math.min(Math.max(1, p), this.pages);
    if (next === this.page()) return;
    this.page.set(next);
    void this.load();
  }
}
