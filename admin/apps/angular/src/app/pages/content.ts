/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { idOf } from '../core/render';
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
  { name: 'title', label: '公告标题', type: 'text', required: true, placeholder: '最长 255' },
  {
    name: 'type',
    label: '公告类型',
    type: 'select',
    keepIfEmpty: true,
    options: [
      { value: 'system', label: '系统' },
      { value: 'game', label: '游戏' },
      { value: 'payment', label: '支付' },
    ],
  },
  { name: 'status', label: '上架状态', type: 'switch' },
  { name: 'target_lang', label: '目标语言', type: 'text', placeholder: '留空 = 全语言，最长 10' },
  {
    name: 'start_at',
    label: '生效时间',
    type: 'text',
    placeholder: '2026-09-30 12:00:00，留空 = 不限',
  },
  { name: 'end_at', label: '失效时间', type: 'text', placeholder: '不得早于生效时间' },
  { name: 'content', label: '公告内容', type: 'textarea', required: true },
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
    label: '成就标识',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: '小写字母/数字/_，最长 50',
  },
  { name: 'name', label: '成就名称', type: 'text', required: true, placeholder: '最长 100' },
  { name: 'description', label: '成就描述', type: 'textarea', placeholder: '最长 500' },
  { name: 'icon', label: '图标', type: 'text', full: true, placeholder: '图片 URL，最长 200' },
  {
    name: 'condition_json',
    label: '达成条件（JSON）',
    type: 'textarea',
    required: true,
    placeholder: '{"event":"game.played","metric":"count","threshold":10}',
  },
  { name: 'points', label: '奖励积分', type: 'number', required: true, placeholder: '≥ 0 的整数' },
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
    label: '活动类型',
    type: 'select',
    required: true,
    createOnly: true,
    options: [
      { value: 'signin', label: '签到' },
      { value: 'daily_task', label: '每日任务' },
      { value: 'invite', label: '邀请' },
    ],
  },
  { name: 'name', label: '活动名称', type: 'text', required: true, placeholder: '最长 100' },
  {
    name: 'game_id',
    label: '关联游戏 ID（数字，0 = 全平台）',
    type: 'text',
    placeholder: '数据库原始 ID（不是 hashid）',
  },
  {
    name: 'config',
    label: '活动配置（JSON，按类型校验）',
    type: 'textarea',
    keepIfEmpty: true,
    placeholder: '留空 = 用该类型的默认配置',
  },
  {
    name: 'status',
    label: '活动状态',
    type: 'select',
    required: true,
    options: [
      { value: '0', label: '禁用' },
      { value: '1', label: '启用' },
      { value: '2', label: '已结束' },
    ],
  },
  { name: 'start_at', label: '生效时间', type: 'text', placeholder: '2026-09-30 12:00:00，留空 = 不限' },
  { name: 'end_at', label: '失效时间', type: 'text', placeholder: '不得早于生效时间' },
  { name: 'rollout_percent', label: '灰度比例（%）', type: 'number', placeholder: '0-100，留空 = 100' },
];

/**
 * 字段真值 = LeaderboardController::create/update 的 validator。
 * game_id（游戏 hashid，create 里 decodeId）不在 update 白名单 ⇒ createOnly；
 * rule 是 JSON 文本（install.sql:842「排行规则配置(JSON)」）。
 * status 只有 0/1 且无 toggle 端点 ⇒ statused + 局部 PUT {status}；
 * 「刷新缓存」是本模块独有的动作，走 crud().extra + extra()（基类的编辑/删除/启停之外）。
 */
const LB_FIELDS: Field[] = [
  { name: 'name', label: '排行榜名称', type: 'text', required: true, placeholder: '最长 100' },
  {
    name: 'type',
    label: '榜单周期',
    type: 'select',
    required: true,
    options: [
      { value: 'daily', label: '日榜' },
      { value: 'weekly', label: '周榜' },
      { value: 'monthly', label: '月榜' },
      { value: 'alltime', label: '总榜' },
    ],
  },
  {
    name: 'metric',
    label: '排行指标',
    type: 'select',
    required: true,
    options: [
      { value: 'earned', label: '累计获得' },
      { value: 'spent', label: '累计消耗' },
      { value: 'play_count', label: '游戏次数' },
    ],
  },
  {
    name: 'game_id',
    label: '关联游戏',
    type: 'text',
    createOnly: true,
    placeholder: '游戏 hashid，留空 = 全平台',
  },
  { name: 'rule', label: '排行规则（JSON）', type: 'textarea', keepIfEmpty: true, placeholder: '可选' },
  { name: 'status', label: '启用状态', type: 'switch' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '数字越小越靠前' },
];

@Component({
  selector: 'app-content',
  imports: [StateBlock, Table, Pager, Tabs, FormModal],
  template: `
    <div class="page-head">
      <h1>内容运营</h1>
      <span class="sub">成就 / 活动 / 公告 / 排行榜</span>
      <div class="spacer"></div>
      <input
        class="input"
        placeholder="名称 / ID"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">查询</button>
      <button class="btn" (click)="load()">刷新</button>
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ 新建</button>
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

    @if (rows().length) {
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
    { key: 'achievement', label: '成就' },
    { key: 'activities', label: '活动' },
    { key: 'announcement', label: '公告' },
    { key: 'leaderboard', label: '排行榜' },
  ];
  protected readonly tab = signal('achievement');

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
      achievement: { noun: '成就', fields: ACH_FIELDS, statused: true, toggle: R + 'achievement/toggle' },
      // 活动的 status 有 0/1/2 三值，基类的 0/1 翻转表达不了「已结束」⇒ 只在表单里改
      activities: { noun: '活动', fields: ACT_FIELDS },
      announcement: { noun: '公告', fields: ANN_FIELDS, statused: true, toggle: R + 'announcement/toggle' },
      leaderboard: {
        noun: '排行榜',
        fields: LB_FIELDS,
        statused: true,
        extra: [{ key: 'refresh', label: '刷新缓存' }],
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
