/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { ListBase } from '../core/list-base';
import { T } from '../core/i18n/i18n';
import { idOf, nested, rowsAny } from '../core/render';
import { dash, errText } from '../core/util';
import { Drawer, Pager, StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';
import type { Act } from '../components/table';

const C = '/admin/v1/';

/**
 * 组列表的列 —— 与 react 树 community.tsx 的 GROUP_COLUMNS **逐列相同**。
 * 那边砍到 8 列是因为它的列推导有 8 列上限（10 个字段会把 expire_at 挤没）；本树没有这个上限，
 * 仍然只摆这 8 列：两棵树的管理员看到的要是同一张表，而不是各看各的。
 */
const GROUP_HEADS: Record<string, string> = {
  id: 'col.id',
  name: 'col.name',
  type: 'col.type',
  game_id: 'col.game_id',
  owner_id: 'col.owner_id',
  member_count: 'col.member_count',
  status: 'col.status',
  expire_at: 'col.expire_at',
};

/** 审计抽屉里那张组表：键就是 GroupController::audit 返回的 `group` 那 5 个 */
const AUDIT_HEADS: Record<string, string> = {
  id: 'col.id',
  name: 'col.name',
  type: 'col.type',
  status: 'col.status',
  member_count: 'col.member_count',
};

/** 成员表：成员变动流水（`members` 的 5 个键全给上，这张表没有列数压力） */
const MEMBER_HEADS: Record<string, string> = {
  user_id: 'col.user_id',
  role: 'col.role',
  contrib: 'col.contrib',
  joined_at: 'col.joined_at',
  left_at: 'col.left_at',
};

/** 分享统计的按天表（ShareController::stats 的 `daily`，服务端已按天倒序截到 30 行） */
const DAILY_HEADS: Record<string, string> = {
  day: 'col.day',
  shares: 'community.shares',
  clicks: 'community.clicks',
  conversions: 'community.conversions',
};

/** 漏斗三项：与按天表的表头同一组词（同一个后端字段，不另起一套名字） */
const FUNNEL = ['shares', 'clicks', 'conversions'];

/** type 下拉 = GroupController::list 认识的值域（team/guild，精确匹配） */
const TYPES = [
  { value: 'team', label: 'community.team' },
  { value: 'guild', label: 'community.guild' },
];

/**
 * 社群：**组队/公会**（GET /groups + /groups/{hashid}/audit）与**分享裂变统计**（GET /share/stats）
 * —— 三个 M4 端点本树此前都没有入口。
 *
 * 全是只读：后端没有群组的增删改（成员由游戏侧写入），所以本页不继承 CrudPage、也不摆任何写按钮。
 */
@Component({
  selector: 'app-community',
  imports: [StateBlock, StatCard, Table, Pager, Tabs, Drawer, T],
  template: `
    <div class="page-head">
      <h1>{{ 'nav.community' | t }}</h1>
      <span class="sub">{{ 'community.subtitle' | t }}</span>
      <div class="spacer"></div>

      @if (tab() === 'groups') {
        <!-- 三个筛选都是后端的精确匹配参数；select 不能绑 [value]（绑定早于 @for 生成的 option，
             预选会被吞）⇒ 逐项 [selected]，与 finance/marketing 两页同款 -->
        <select class="input" (change)="fType.set($any($event.target).value)">
          <option value="" [selected]="fType() === ''">{{ 'community.any_type' | t }}</option>
          @for (o of TYPES; track o.value) {
            <option [value]="o.value" [selected]="fType() === o.value">{{ o.label | t }}</option>
          }
        </select>
        <select class="input" (change)="fStatus.set($any($event.target).value)">
          <option value="" [selected]="fStatus() === ''">{{ 'community.any_status' | t }}</option>
          <option value="1" [selected]="fStatus() === '1'">{{ 'app.enabled' | t }}</option>
          <option value="0" [selected]="fStatus() === '0'">{{ 'app.disabled' | t }}</option>
        </select>
        <select class="input" (change)="fGame.set($any($event.target).value)">
          <option value="" [selected]="fGame() === ''">{{ 'community.any_game' | t }}</option>
          @for (g of games(); track g.id) {
            <option [value]="g.id" [selected]="fGame() === g.id">{{ g.name }}</option>
          }
        </select>
        <button class="btn" (click)="apply()">{{ 'app.search' | t }}</button>
        <button class="btn" (click)="resetFilters()">{{ 'community.reset' | t }}</button>
      } @else {
        <!-- 日期范围：后端只在给了 from/to 时才加 created_at 条件，留空即全量 -->
        <input
          class="input"
          type="date"
          [value]="from()"
          (change)="from.set($any($event.target).value)"
        />
        <input class="input" type="date" [value]="to()" (change)="to.set($any($event.target).value)" />
        <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
        <button class="btn" (click)="resetRange()">{{ 'community.reset' | t }}</button>
      }
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    @if (tab() === 'share') {
      <div class="tiles">
        @for (f of funnel(); track f.label) {
          <ui-stat [label]="f.label | t" [value]="f.value" />
        }
      </div>
      <div class="hint">{{ 'community.range_hint' | t }}</div>
    }

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      <div class="card">
        <div class="card-body">
          <ui-table
            [rows]="rows()"
            [heads]="heads()"
            [actions]="actions()"
            (act)="run($event.row, $event.key)"
          />
        </div>
      </div>
    </ui-state>

    <!-- 按天表没有分页参数（服务端固定 limit 30）⇒ 只有组列表出分页条 -->
    @if (tab() === 'groups' && rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <!-- title 是**值**不是词条键（ui-drawer 的 title() 不过 t 管道，与 ui-form 的 title 不同） -->
    <ui-drawer [open]="audit() !== null" [title]="'community.audit' | t" (close)="audit.set(null)">
      @if (audit(); as a) {
        <ui-table [rows]="groupRows()" [heads]="auditHeads" />
        <ui-table [rows]="members()" [heads]="memberHeads" />
      }
    </ui-drawer>
  `,
})
export class Community extends ListBase<Row> {
  private readonly api = inject(Api);

  /** 模板作用域只认类成员，模块级 const 不可见（同 components/table.ts 的 dash/idOf） */
  protected readonly TYPES = TYPES;
  protected readonly auditHeads = AUDIT_HEADS;
  protected readonly memberHeads = MEMBER_HEADS;

  protected readonly tabs = [
    { key: 'groups', label: 'community.tab_groups' },
    { key: 'share', label: 'community.tab_share' },
  ];
  protected readonly tab = signal('groups');

  /**
   * 组列表的三个筛选分 draft 与 applied 两份：只有点「查询」才把 draft 交给后端（三个参数都是
   * 精确匹配，边改边发请求会让页码与总数一直跳）。日期范围不必分两份 —— 它的两个框只有点了
   * 「查询」才会读一次，`search()` 自己就落值。
   */
  protected readonly fType = signal('');
  protected readonly fStatus = signal('');
  protected readonly fGame = signal('');
  protected readonly from = signal('');
  protected readonly to = signal('');
  private readonly applied = signal<Record<string, string>>({});

  /** 游戏下拉的选项域（筛选用，走 game_id） */
  protected readonly games = signal<{ id: string; name: string }[]>([]);
  /** 审计抽屉的载荷 `{group, members}`；null = 抽屉关着 */
  protected readonly audit = signal<Row | null>(null);
  /** 分享统计的原始响应 `{funnel, daily}`（按天表进 rows()，漏斗进 funnel()） */
  protected readonly stats = signal<Row | null>(null);

  protected readonly heads = computed<Record<string, string>>(() =>
    this.tab() === 'groups' ? GROUP_HEADS : DAILY_HEADS,
  );

  protected readonly actions = computed<Act[]>(() =>
    this.tab() === 'groups' ? [{ key: 'audit', label: 'community.audit' }] : [],
  );

  protected readonly funnel = computed(() => {
    const f = nested(this.stats(), 'funnel');
    const o = f && typeof f === 'object' && !Array.isArray(f) ? (f as Row) : {};
    return FUNNEL.map((k) => ({ label: `community.${k}`, value: dash(o[k]) }));
  });

  /** 审计载荷里的组本身：一行，列为 AUDIT_HEADS（不在数组里，服务端给的是对象） */
  protected readonly groupRows = computed<Row[]>(() => {
    const g = nested(this.audit(), 'group');
    return g && typeof g === 'object' && !Array.isArray(g) ? [g as Row] : [];
  });

  protected readonly members = computed<Row[]>(() => rowsAny(this.audit(), 'members'));

  override ngOnInit(): void {
    super.ngOnInit();
    void this.loadGames(); // 落地页签就是组列表，游戏下拉要就绪
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.audit.set(null);
    this.stats.set(null);
    void this.load();
  }

  /** 组列表的「查询」：把 draft 交给 applied，再走基类的 search()（page=1 + load） */
  protected apply(): void {
    this.applied.set({ type: this.fType(), status: this.fStatus(), game_id: this.fGame() });
    this.search();
  }

  protected resetFilters(): void {
    this.fType.set('');
    this.fStatus.set('');
    this.fGame.set('');
    this.apply();
  }

  protected resetRange(): void {
    this.from.set('');
    this.to.set('');
    this.search();
  }

  protected run(row: Row, key: string): void {
    if (key === 'audit') void this.openAudit(row);
  }

  /**
   * 成员变动流水 —— GET /groups/{hashid}/audit，回 `{group, members}`。
   * 取不到就只报错、不开抽屉：抽屉里的内容全来自这个端点，退回渲染列表行会让人以为「成员是空的」。
   */
  protected async openAudit(row: Row): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    this.error.set('');
    try {
      this.audit.set(await this.api.get<Row>(C + 'groups/' + id + '/audit'));
    } catch (e) {
      this.audit.set(null);
      this.error.set(errText(e));
    }
  }

  /** 游戏下拉：失败只让这一个筛选退化成「全部游戏」，另外两个筛选与列表照常（同 marketing.ts 的口径） */
  private async loadGames(): Promise<void> {
    try {
      const res = await this.api.list<Row>(C + 'game/list', { page: 1, page_size: 200 });
      this.games.set(
        res.list
          .map((g) => ({ id: idOf(g), name: String(g['name'] ?? g['game_name'] ?? '') }))
          .filter((g) => !!g.id),
      );
    } catch {
      this.games.set([]);
    }
  }

  protected override fetch(): Promise<Page<Row>> {
    if (this.tab() === 'share') return this.fetchStats();
    this.stats.set(null);
    return this.api.list<Row>(C + 'groups', {
      page: this.page(),
      page_size: this.pageSize,
      ...this.applied(),
    });
  }

  /**
   * 分享统计没有分页参数：`daily` 是服务端按天聚合后**截到 30 行**的结果，漏斗是同一批数据的
   * 三个总数 ⇒ 自己拼一个 Page（total = 行数，界面上不出分页条）。
   */
  private async fetchStats(): Promise<Page<Row>> {
    this.stats.set(null);
    const d = await this.api.get<Row>(C + 'share/stats', { from: this.from(), to: this.to() });
    this.stats.set(d);
    const daily = rowsAny(d, 'daily');
    return { list: daily, total: daily.length, page: 1, limit: daily.length };
  }
}
