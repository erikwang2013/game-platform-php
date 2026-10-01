/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { idOf } from '../core/render';
import { T } from '../core/i18n/i18n';
import { Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';

const G = '/admin/v1/game/';

/**
 * 字段真值 = GameController::create/update 的 validator（+ game_game 列宽）。
 * api_key/api_secret 在模型 $hidden 里、列表不回显，所以标 keepIfEmpty：留空即不提交。
 * slug 不在 update 的落库白名单（$request->only）里，只有新建能填。
 */
const GAME_FIELDS: Field[] = [
  { name: 'name', label: 'game.name', type: 'text', required: true, placeholder: 'game.name_hint' },
  {
    name: 'slug',
    label: 'game.slug',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'game.slug_hint',
  },
  {
    name: 'type',
    label: 'game.type',
    type: 'select',
    required: true,
    options: [
      { value: 'self', label: 'game.self' },
      { value: 'embedded', label: 'game.embedded' },
      { value: 'third_party', label: 'game.third_party' },
    ],
  },
  {
    name: 'platform',
    label: 'game.platform',
    type: 'select',
    keepIfEmpty: true,
    // H5 / Unity / Web 是后端枚举原文（不译）；只有 native 这侧写的是中文，故只有它挂词条
    options: [
      { value: 'h5', label: 'H5' },
      { value: 'unity', label: 'Unity' },
      { value: 'web', label: 'Web' },
      { value: 'native', label: 'game.platform_native' },
    ],
  },
  { name: 'region', label: 'game.region', type: 'text', placeholder: 'game.region_hint' },
  { name: 'status', label: 'game.status', type: 'switch' },
  { name: 'sort', label: 'game.sort', type: 'number', placeholder: 'game.sort_hint' },
  {
    name: 'sdk_version',
    label: 'game.sdk_version',
    type: 'text',
    placeholder: 'game.sdk_version_hint',
  },
  {
    name: 'cover_image',
    label: 'game.cover_image',
    type: 'image',
    placeholder: 'game.cover_image_hint',
  },
  {
    name: 'api_endpoint',
    label: 'game.api_endpoint',
    type: 'text',
    full: true,
    placeholder: 'game.api_endpoint_hint',
  },
  {
    name: 'api_key',
    label: 'game.api_key',
    type: 'text',
    keepIfEmpty: true,
    placeholder: 'game.api_key_hint',
  },
  {
    name: 'api_secret',
    label: 'game.api_secret',
    type: 'text',
    keepIfEmpty: true,
    placeholder: 'game.api_secret_hint',
  },
  { name: 'description', label: 'game.description', type: 'textarea' },
];

/**
 * 字段真值 = GameCategoryController::create/update 的 validator。
 * slug 只在 create（update 的落库白名单里没有它，且后端没有对应规则）⇒ createOnly。
 * 表单里**不放** status：create 硬编码 `$category->status = 1`（:67），提交什么都被忽略 ——
 * 摆个开关就是骗人。update 认 status（in:0,1），状态改走行内的「启用/停用」（局部 PUT {status}）。
 */
const CATEGORY_FIELDS: Field[] = [
  {
    name: 'name',
    label: 'game_category.name',
    type: 'text',
    required: true,
    placeholder: 'game_category.name_hint',
  },
  {
    name: 'slug',
    label: 'game_category.slug',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'game_category.slug_hint',
  },
  {
    name: 'icon',
    label: 'game_category.icon',
    type: 'image',
    placeholder: 'game_category.icon_hint',
  },
  {
    name: 'sort',
    label: 'game_category.sort',
    type: 'number',
    placeholder: 'game_category.sort_hint',
  },
];

/**
 * 字段真值 = GameServerController::create/update 的 validator。
 * game_id（游戏 hashid）是 create 的必填，且不在 update 白名单 ⇒ createOnly；
 * 列表接口同样按 game_id 过滤（见 fetch() 里那个输入框）。
 * status 是 4 值（0=维护 1=正常 2=火爆 3=新服，install.sql:557）⇒ 用 select 不用 switch，
 * 也不开 statused —— 基类的行内「启用/停用」只会翻 0/1，把 2/3 静默压成 0。
 */
const SERVER_FIELDS: Field[] = [
  {
    name: 'game_id',
    label: 'game_server.game_id',
    type: 'text',
    required: true,
    createOnly: true,
    full: true,
    placeholder: 'game_server.game_id_hint',
  },
  {
    name: 'name',
    label: 'game_server.name',
    type: 'text',
    required: true,
    placeholder: 'game_server.name_hint',
  },
  {
    name: 'region',
    label: 'game_server.region',
    type: 'text',
    placeholder: 'game_server.region_hint',
  },
  {
    name: 'status',
    label: 'game_server.status',
    type: 'select',
    keepIfEmpty: true,
    options: [
      { value: '0', label: 'game_server.status_maintenance' },
      { value: '1', label: 'game_server.status_normal' },
      { value: '2', label: 'game_server.status_hot' },
      { value: '3', label: 'game_server.status_new' },
    ],
  },
  {
    name: 'sort',
    label: 'game_server.sort',
    type: 'number',
    placeholder: 'game_server.sort_hint',
  },
];

@Component({
  selector: 'app-games',
  imports: [StateBlock, Table, Pager, Tabs, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'nav.games' | t }}</h1>
      <span class="sub">{{ 'game.subtitle' | t }}</span>
      <div class="spacer"></div>
      @if (tab() === 'server') {
        <input
          class="input"
          [placeholder]="'game_server.game_id_filter' | t"
          [value]="gameId()"
          (input)="gameId.set($any($event.target).value)"
          (keyup.enter)="search()"
        />
      }
      <input
        class="input"
        [placeholder]="'game.search_hint' | t"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ {{ 'app.create' | t }}</button>
      }
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state
      [loading]="loading()"
      [error]="error()"
      [empty]="!rows().length"
      [text]="(needGameId() ? 'game_server.need_game_id' : 'app.no_data') | t"
    >
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
export class Games extends CrudPage {
  protected readonly tabs = [
    { key: 'game', label: 'game.title' },
    { key: 'category', label: 'game_category.title' },
    { key: 'server', label: 'game_server.title' },
  ];
  protected readonly tab = signal('game');
  /** 区服标签页的游戏过滤（= create/update 那个 game_id 的同一个值） */
  protected readonly gameId = signal('');

  /**
   * 只有游戏列表是 page+limit 分页的：分类端点是 `orderBy('sort')->get()`（整表、无 total），
   * 区服端点连分页参数都不看、直接回裸数组 ⇒ 这两页签挂分页器就是给出一个假第 2 页。
   */
  protected readonly paged = computed(() => this.tab() === 'game');

  private readonly paths: Record<string, string> = {
    game: G + 'list',
    category: G + 'category/list',
  };

  protected needGameId(): boolean {
    return this.tab() === 'server' && !this.gameId().trim();
  }

  // ponytail: 货币只读列表后端未提供（仅 POST /game/currency/manage 写接口），故不设该标签页
  protected override crud(): Crud | null {
    const tab = this.tab();
    const specs: Record<string, { noun: string; fields: Field[]; path: string }> = {
      game: { noun: 'game.noun', fields: GAME_FIELDS, path: '' },
      category: { noun: 'game_category.noun', fields: CATEGORY_FIELDS, path: 'category/' },
      server: { noun: 'game_server.noun', fields: SERVER_FIELDS, path: 'server/' },
    };
    const spec = specs[tab];
    if (!spec) return null;
    return {
      noun: spec.noun,
      fields: spec.fields,
      // 区服的 status 是 0..3，基类的 0/1 翻转会压掉「火爆/新服」⇒ 只在表单里改
      statused: tab !== 'server',
      label: (row) => String(row['name'] ?? idOf(row)),
      ends: {
        create: G + spec.path + 'create',
        update: (id) => G + spec.path + id,
        remove: (id) => G + spec.path + id,
        // 三个模块都没有 toggle 端点：状态切换走局部 update（PUT {hashid} + {status}）
      },
    };
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    void this.load();
  }

  protected override async fetch(): Promise<Page<Row>> {
    const tab = this.tab();
    if (tab === 'server') {
      // GameServerController::list 的 game_id 是 required，且返回裸数组：没填就别发请求，
      // 否则每次进页面都拿回一条 422「The game_id field is required.」
      const gameId = this.gameId().trim();
      if (!gameId) return { list: [], total: 0, page: 1, limit: this.pageSize };
      const list = await this.api.list<Row>(G + 'server/list', { game_id: gameId });
      return { ...list, page: 1, limit: this.pageSize };
    }
    return this.api.list<Row>(this.paths[tab]!, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }
}
