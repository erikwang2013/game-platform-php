/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { idOf } from '../core/render';
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
  { name: 'name', label: '游戏名称', type: 'text', required: true, placeholder: '最长 100' },
  {
    name: 'slug',
    label: '游戏标识',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: '小写字母/数字/_/-，最长 50',
  },
  {
    name: 'type',
    label: '游戏类型',
    type: 'select',
    required: true,
    options: [
      { value: 'self', label: '自研' },
      { value: 'embedded', label: '内嵌' },
      { value: 'third_party', label: '第三方' },
    ],
  },
  {
    name: 'platform',
    label: '平台',
    type: 'select',
    keepIfEmpty: true,
    options: [
      { value: 'h5', label: 'H5' },
      { value: 'unity', label: 'Unity' },
      { value: 'web', label: 'Web' },
      { value: 'native', label: '原生' },
    ],
  },
  { name: 'region', label: '地区', type: 'text', placeholder: '如 global，最长 10' },
  { name: 'status', label: '上架状态', type: 'switch' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '数字越小越靠前' },
  { name: 'sdk_version', label: 'SDK 版本', type: 'text', placeholder: '最长 20' },
  {
    name: 'cover_image',
    label: '封面图',
    type: 'image',
    placeholder: '图片 URL，最长 255',
  },
  {
    name: 'api_endpoint',
    label: 'API 端点',
    type: 'text',
    full: true,
    placeholder: '最长 255',
  },
  {
    name: 'api_key',
    label: 'API Key',
    type: 'text',
    keepIfEmpty: true,
    placeholder: '编辑时留空 = 不修改',
  },
  {
    name: 'api_secret',
    label: 'API Secret',
    type: 'text',
    keepIfEmpty: true,
    placeholder: '编辑时留空 = 不修改；自研/内嵌留空自动生成',
  },
  { name: 'description', label: '游戏描述', type: 'textarea' },
];

/**
 * 字段真值 = GameCategoryController::create/update 的 validator。
 * slug 只在 create（update 的落库白名单里没有它，且后端没有对应规则）⇒ createOnly。
 * 表单里**不放** status：create 硬编码 `$category->status = 1`（:67），提交什么都被忽略 ——
 * 摆个开关就是骗人。update 认 status（in:0,1），状态改走行内的「启用/停用」（局部 PUT {status}）。
 */
const CATEGORY_FIELDS: Field[] = [
  { name: 'name', label: '分类名称', type: 'text', required: true, placeholder: '最长 50' },
  {
    name: 'slug',
    label: '分类标识',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: '小写字母/数字/_/-，最长 50',
  },
  {
    name: 'icon',
    label: '图标',
    type: 'image',
    placeholder: '图片 URL 或图标名，最长 255',
  },
  { name: 'sort', label: '排序', type: 'number', placeholder: '数字越小越靠前' },
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
    label: '所属游戏',
    type: 'text',
    required: true,
    createOnly: true,
    full: true,
    placeholder: '游戏 hashid（从「游戏列表」标签页复制）',
  },
  { name: 'name', label: '区服名称', type: 'text', required: true, placeholder: '最长 50' },
  { name: 'region', label: '所属区域', type: 'text', placeholder: '如 global/asia/eu/na，最长 20' },
  {
    name: 'status',
    label: '区服状态（留空 = 不改）',
    type: 'select',
    keepIfEmpty: true,
    options: [
      { value: '0', label: '维护' },
      { value: '1', label: '正常' },
      { value: '2', label: '火爆' },
      { value: '3', label: '新服' },
    ],
  },
  { name: 'sort', label: '排序', type: 'number', placeholder: '数字越小越靠前' },
];

@Component({
  selector: 'app-games',
  imports: [StateBlock, Table, Pager, Tabs, FormModal],
  template: `
    <div class="page-head">
      <h1>游戏管理</h1>
      <span class="sub">游戏 / 分类 / 区服</span>
      <div class="spacer"></div>
      @if (tab() === 'server') {
        <input
          class="input"
          placeholder="游戏 hashid（区服列表按游戏查）"
          [value]="gameId()"
          (input)="gameId.set($any($event.target).value)"
          (keyup.enter)="search()"
        />
      }
      <input
        class="input"
        placeholder="游戏名 / 标识"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">查询</button>
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ 新建</button>
      }
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state
      [loading]="loading()"
      [error]="error()"
      [empty]="!rows().length"
      [text]="needGameId() ? '先在上面填游戏 hashid 再查询' : '暂无数据'"
    >
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
export class Games extends CrudPage {
  protected readonly tabs = [
    { key: 'game', label: '游戏列表' },
    { key: 'category', label: '游戏分类' },
    { key: 'server', label: '区服' },
  ];
  protected readonly tab = signal('game');
  /** 区服标签页的游戏过滤（= create/update 那个 game_id 的同一个值） */
  protected readonly gameId = signal('');

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
      game: { noun: '游戏', fields: GAME_FIELDS, path: '' },
      category: { noun: '分类', fields: CATEGORY_FIELDS, path: 'category/' },
      server: { noun: '区服', fields: SERVER_FIELDS, path: 'server/' },
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
