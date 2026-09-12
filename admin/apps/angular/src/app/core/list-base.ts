/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { signal } from '@angular/core';
import { Page } from './api.service';
import { errText } from './util';

/**
 * 列表页公共状态机：加载中 / 出错 / 空 / 分页。
 * 十几个列表页重复的只有这套东西，抽干它，页面只管 fetch()。
 */
export abstract class ListBase<T> {
  readonly rows = signal<T[]>([]);
  readonly loading = signal(true);
  readonly error = signal('');
  readonly page = signal(1);
  readonly total = signal(0);
  readonly pageSize = 20;
  readonly keyword = signal('');

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
