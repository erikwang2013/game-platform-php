/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { idOf } from '../core/render';
import { T } from '../core/i18n/i18n';
import { Pager, StateBlock, Tabs } from '../components/ui';
import { Table, type Act } from '../components/table';
import { FormModal } from '../components/form-modal';

const R = '/admin/v1/';

/**
 * 字段真值 = AnnouncementController::create/update 的 validator。
 * type 枚举取自 game_announcement.type 的列注释与 install/test-data.sql 存量数据
 * （system/game/payment；早期后端曾误写 system/event）。规则是 sometimes|required|in:
 * 非 nullable ⇒ 清空会被判 422，所以标 keepIfEmpty：留空 = 不提交。
 * start_at/end_at 是 datetime 列，列表回 ISO 串；不动它就不会提交（局部更新），
 * 要改就按「2026-09-30 12:00:00」重填。
 */
const ANN_FIELDS: Field[] = [
  {
    name: 'title',
    label: 'announcement.field_title',
    type: 'text',
    required: true,
    placeholder: 'announcement.title_hint',
  },
  {
    name: 'type',
    label: 'announcement.type',
    type: 'select',
    keepIfEmpty: true,
    // 值仍是库里的枚举原文（system/game/payment），只有文案可译
    options: [
      { value: 'system', label: 'announcement.type_system' },
      { value: 'game', label: 'announcement.type_game' },
      { value: 'payment', label: 'announcement.type_payment' },
    ],
  },
  { name: 'status', label: 'announcement.status', type: 'switch' },
  {
    name: 'target_lang',
    label: 'announcement.target_lang',
    type: 'text',
    placeholder: 'announcement.target_lang_hint',
  },
  {
    name: 'start_at',
    label: 'announcement.start_at',
    type: 'text',
    placeholder: 'announcement.start_hint',
  },
  {
    name: 'end_at',
    label: 'announcement.end_at',
    type: 'text',
    placeholder: 'announcement.end_hint',
  },
  { name: 'content', label: 'announcement.content', type: 'textarea', required: true },
];

/**
 * 字段真值 = AchievementController::create/update 的 validator。
 * key 只在 create（update 白名单里没有）⇒ createOnly；condition_json 是 JSON 文本，
 * schema 见 service/app/service/AchievementService.php:20（event/metric/table/threshold）。
 * status 走本模块 toggle 端点（行内「启用/停用」），表单里就不摆第二个开关了。
 */
const ACH_FIELDS: Field[] = [
  {
    name: 'key',
    label: 'achievement.key',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'achievement.key_hint',
  },
  {
    name: 'name',
    label: 'achievement.name',
    type: 'text',
    required: true,
    placeholder: 'achievement.name_hint',
  },
  {
    name: 'description',
    label: 'achievement.description',
    type: 'textarea',
    placeholder: 'achievement.description_hint',
  },
  { name: 'icon', label: 'achievement.icon', type: 'image', placeholder: 'achievement.icon_hint' },
  {
    name: 'condition_json',
    label: 'achievement.condition',
    type: 'textarea',
    required: true,
    placeholder: 'achievement.condition_hint',
  },
  {
    name: 'points',
    label: 'achievement.points',
    type: 'number',
    required: true,
    placeholder: 'achievement.points_hint',
  },
];

/**
 * 字段真值 = ActivityController::create/update 的 validator。
 * type 只在 create ⇒ createOnly；config 是 JSON 文本，后端按 type 校验（ActivityController:166）：
 *   signin     {"rewards":[{"day":1,"reward":{"type":"platform_coin","amount":"100"}}]}
 *   daily_task {"tasks":[{"event":"game.played","target":3,"reward":{...}}]}
 *   invite     {"target":3,"rewards":[{"reward":{...}}]}
 * reward.type 只有 platform_coin / game_coin 发得出去，amount 是 0 < x ≤ 10000 的十进制串。
 * config 留空 = 用 type 的默认配置（后端 parseConfig 走 handler 默认值）⇒ keepIfEmpty，
 * 免得手滑清空一次就把手工调好的配置悄悄换回默认值。
 * status 三值（0=禁用 1=启用 2=已结束，install.sql:1577）：无 toggle 端点、switch 也表达不了
 * 2 ⇒ 用 select，改了走局部 PUT {status}。
 */
const ACT_FIELDS: Field[] = [
  {
    name: 'type',
    label: 'activity.type',
    type: 'select',
    required: true,
    createOnly: true,
    options: [
      { value: 'signin', label: 'activity.type_signin' },
      { value: 'daily_task', label: 'activity.type_daily_task' },
      { value: 'invite', label: 'activity.type_invite' },
    ],
  },
  {
    name: 'name',
    label: 'activity.name',
    type: 'text',
    required: true,
    placeholder: 'activity.name_hint',
  },
  {
    name: 'game_id',
    label: 'activity.game_id',
    type: 'text',
    placeholder: 'activity.game_id_hint',
  },
  {
    name: 'config',
    label: 'activity.config',
    type: 'textarea',
    keepIfEmpty: true,
    placeholder: 'activity.config_hint',
  },
  {
    name: 'status',
    label: 'activity.status',
    type: 'select',
    required: true,
    // 三值（0 禁用 / 1 启用 / 2 已结束）：0/1 翻转控件表达不了「已结束」⇒ 用 select
    options: [
      { value: '0', label: 'activity.status_disabled' },
      { value: '1', label: 'activity.status_enabled' },
      { value: '2', label: 'activity.status_ended' },
    ],
  },
  {
    name: 'start_at',
    label: 'activity.start_at',
    type: 'text',
    placeholder: 'activity.time_hint',
  },
  {
    name: 'end_at',
    label: 'activity.end_at',
    type: 'text',
    placeholder: 'activity.end_hint',
  },
  {
    name: 'rollout_percent',
    label: 'activity.rollout_percent',
    type: 'number',
    placeholder: 'activity.rollout_hint',
  },
];

/**
 * 字段真值 = LeaderboardController::create/update 的 validator。
 * game_id（游戏 hashid，create 里 decodeId）不在 update 白名单 ⇒ createOnly；
 * rule 是 JSON 文本（install.sql:842「排行规则配置(JSON)」）。
 * status 只有 0/1 且无 toggle 端点 ⇒ statused + 局部 PUT {status}；
 * 「刷新缓存」是本模块独有的动作，走 crud().extra + extra()（基类的编辑/删除/启停之外）。
 */
const LB_FIELDS: Field[] = [
  {
    name: 'name',
    label: 'leaderboard.name',
    type: 'text',
    required: true,
    placeholder: 'leaderboard.name_hint',
  },
  {
    name: 'type',
    label: 'leaderboard.type',
    type: 'select',
    required: true,
    options: [
      { value: 'daily', label: 'leaderboard.type_daily' },
      { value: 'weekly', label: 'leaderboard.type_weekly' },
      { value: 'monthly', label: 'leaderboard.type_monthly' },
      { value: 'alltime', label: 'leaderboard.type_alltime' },
    ],
  },
  {
    name: 'metric',
    label: 'leaderboard.metric',
    type: 'select',
    required: true,
    options: [
      { value: 'earned', label: 'leaderboard.metric_earned' },
      { value: 'spent', label: 'leaderboard.metric_spent' },
      { value: 'play_count', label: 'leaderboard.metric_play_count' },
    ],
  },
  {
    name: 'game_id',
    label: 'leaderboard.game_id',
    type: 'text',
    createOnly: true,
    placeholder: 'leaderboard.game_id_hint',
  },
  {
    name: 'rule',
    label: 'leaderboard.rule',
    type: 'textarea',
    keepIfEmpty: true,
    placeholder: 'leaderboard.rule_hint',
  },
  { name: 'status', label: 'leaderboard.status', type: 'switch' },
  {
    name: 'sort',
    label: 'leaderboard.sort',
    type: 'number',
    placeholder: 'leaderboard.sort_hint',
  },
];

@Component({
  selector: 'app-content',
  imports: [StateBlock, Table, Pager, Tabs, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'content.title' | t }}</h1>
      <span class="sub">{{ 'content.subtitle' | t }}</span>
      <div class="spacer"></div>
      <input
        class="input"
        [placeholder]="'content.search_hint' | t"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ {{ 'app.create' | t }}</button>
      }
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      <div class="card">
        <div class="card-body">
          <ui-table [rows]="rows()" [actions]="actions()" (act)="run($event.row, $event.key)" />
        </div>
      </div>
    </ui-state>

    @if (paged() && rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <ui-form
      [open]="formOpen()"
      [title]="formTitle()"
      [fields]="formFields()"
      [value]="formValue()"
      [error]="formError()"
      [saving]="saving()"
      (save)="submit($event)"
      (close)="closeForm()"
    />
  `,
})
export class Content extends CrudPage {
  protected readonly tabs = [
    { key: 'achievement', label: 'achievement.title' },
    { key: 'activities', label: 'activity.title' },
    { key: 'announcement', label: 'announcement.title' },
    { key: 'leaderboard', label: 'leaderboard.title' },
  ];
  protected readonly tab = signal('achievement');

  /**
   * 分页器只在真分页的端点下出现。成就走 /achievement/list —— `Achievement::orderBy('id')->get()`
   * 一次返回**整表**且响应里没有 total（api.list 只能拿 list.length 顶数）⇒ 挂上分页器就是撒谎：
   * 第 2 页点进去还是同一批数据。活动 / 公告 / 排行榜三个端点都是 page+limit 真分页。
   */
  protected readonly paged = computed(() => this.tab() !== 'achievement');

  private readonly paths: Record<string, string> = {
    achievement: R + 'achievement/list',
    activities: R + 'activities/list',
    announcement: R + 'announcement/list',
    leaderboard: R + 'leaderboard/list',
  };

  protected override crud(): Crud | null {
    const tab = this.tab();
    const specs: Record<
      string,
      { noun: string; fields: Field[]; statused?: boolean; toggle?: string; extra?: Act[] }
    > = {
      achievement: {
        noun: 'achievement.noun',
        fields: ACH_FIELDS,
        statused: true,
        toggle: R + 'achievement/toggle',
      },
      // 活动的 status 有 0/1/2 三值，基类的 0/1 翻转表达不了「已结束」⇒ 只在表单里改
      activities: { noun: 'activity.noun', fields: ACT_FIELDS },
      announcement: {
        noun: 'announcement.noun',
        fields: ANN_FIELDS,
        statused: true,
        toggle: R + 'announcement/toggle',
      },
      leaderboard: {
        noun: 'leaderboard.noun',
        fields: LB_FIELDS,
        statused: true,
        extra: [{ key: 'refresh', label: 'leaderboard.refresh' }],
      },
    };
    const spec = specs[tab];
    if (!spec) return null;
    return {
      noun: spec.noun,
      fields: spec.fields,
      statused: spec.statused,
      extra: spec.extra,
      label: (row) => String(row['name'] ?? row['title'] ?? idOf(row)),
      ends: {
        create: R + tab + '/create',
        update: (id) => `${R}${tab}/${id}`,
        remove: (id) => `${R}${tab}/${id}`,
        toggle: spec.toggle,
      },
    };
  }

  /** 排行榜「刷新缓存」：POST /leaderboard/{hashid}/refresh（只清缓存并重算，不动定义） */
  protected override async extra(row: Row, key: string): Promise<void> {
    const id = idOf(row);
    if (key !== 'refresh' || !id) return;
    await this.api.post(`${R}leaderboard/${id}/refresh`);
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    void this.load();
  }

  protected override fetch(): Promise<Page<Row>> {
    const url = this.paths[this.tab()] ?? this.paths['achievement']!;
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }
}
