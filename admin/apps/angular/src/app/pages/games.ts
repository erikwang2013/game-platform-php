/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { idOf, json, rowsAny } from '../core/render';
import { errText } from '../core/util';
import { T } from '../core/i18n/i18n';
import { Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';
import type { Act } from '../components/table';
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
    hint: 'game.slug_hint',
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
    hint: 'game.api_key_hint',
  },
  {
    name: 'api_secret',
    label: 'game.api_secret',
    type: 'text',
    keepIfEmpty: true,
    hint: 'game.api_secret_hint',
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
    hint: 'game_category.slug_hint',
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
 * status 是 4 值（0=维护 1=正常 2=火爆 3=新服，install.sql:564）⇒ 用 select 不用 switch，
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
    hint: 'game_server.region_hint',
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

/**
 * 游戏币种（POST /game/currency/manage，`{game_id, currencies:[…]}`）。
 *
 * 提交的是**一整份 JSON 数组文本**（本树的表单没有 jsonarr 这种字段类型，转换成数组放在
 * games.ts 里做 —— 端点收的是数组，字符串会被 validator 打回）。三条语义写在 hint 里，
 * 少一条运营就会当成整表替换：不写的不删、**带 id 才是改**、单条不过整批拒绝。
 */
const CURRENCY_FIELDS: Field[] = [
  {
    name: 'currencies',
    label: 'game.currency_act',
    type: 'textarea',
    required: true,
    hint: 'game.currency_hint',
  },
];

/**
 * 分配游戏（POST /game/category/assign，`{category_id, game_ids:[…]}`）。
 *
 * **整体替换**：后端先删光该分类的关联再插入，而分类侧没有任何读端点能拿回当前关联
 * （GameCategoryController 只有 list/create/update/destroy/assign）⇒ 表单只能空白开局，
 * 提示必须把「没列出来的会被解绑」说死（hint 原文照抄 react 的 `f.from_the_games_list_id`）。
 */
const ASSIGN_FIELDS: Field[] = [
  {
    name: 'game_ids',
    label: 'game_category.assign_games',
    type: 'textarea',
    required: true,
    placeholder: 'game_category.assign_placeholder',
    hint: 'game_category.assign_hint',
  },
];

/** 各标签页的行内动作（crud().extra）。区服页签没有动作 ⇒ 查不到就是空数组 */
const EXTRAS: Record<string, Act[]> = {
  game: [{ key: 'currency', label: 'game.currency_act' }],
  category: [{ key: 'assign', label: 'game_category.assign_games' }],
};

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

    <!-- 行内动作的表单（游戏币种 / 分配游戏）：另起一个框，字段集与端点都和新建/编辑不同，
         混用会把 CRUD 的 ends 带歪（提交落到错的端点） -->
    <ui-form
      [open]="actOpen()"
      [title]="actTitle()"
      [fields]="actFields()"
      [value]="actValue()"
      [error]="actError()"
      [saving]="actSaving()"
      (save)="submitAct($event)"
      (close)="actOpen.set(false)"
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
      // 货币 / 分配游戏两个动作都挂在列表页的行上（端点收的是 id + 数组，不是字段级 CRUD）
      extra: EXTRAS[tab],
    };
  }

  // ---- 行内动作：游戏币种（game 页签）/ 分配游戏（category 页签）----

  protected readonly actOpen = signal(false);
  protected readonly actTitle = signal('');
  /** 当前动作的字段集（模板要读 ⇒ 必须是类成员，模块级 const 在模板作用域里看不见） */
  protected readonly actFields = signal<Field[]>(CURRENCY_FIELDS);
  protected readonly actValue = signal<Row | null>(null);
  protected readonly actError = signal('');
  protected readonly actSaving = signal(false);
  /** 动作归属的行 + 是哪个动作：提交时才知道打哪个端点、发哪个键 */
  private actRow: Row | null = null;
  private actKey = '';

  /**
   * 取现值再开框（币种）/ 直接开空框（分配游戏）。
   *
   * 币种的现值**必须**先取：GET /game/{hashid} 的 `currencies` 带 id，抹掉 id 就是「新建一条」
   * —— 拿一份空表去提交，会把整张币种表复制成重复行。故取不到就照抛（由基类落进列表级 error），
   * 宁可不给框，也不给一个「id 全丢」的空框。
   */
  protected override async extra(row: Row, key: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    if (key !== 'currency' && key !== 'assign') return;
    this.actRow = row;
    this.actKey = key;
    this.actError.set('');
    this.actValue.set(null);
    if (key === 'currency') {
      this.actTitle.set('game.currency_act');
      this.actFields.set(CURRENCY_FIELDS);
      const d = await this.api.get<Row>(G + id);
      this.actValue.set({ currencies: json(rowsAny(d, 'currencies')) });
    } else {
      this.actTitle.set('game_category.assign_title');
      this.actFields.set(ASSIGN_FIELDS);
    }
    this.actOpen.set(true);
  }

  /** 动作弹框提交：文本 → 端点要的数组形状，再 POST */
  protected async submitAct(v: Row): Promise<void> {
    const id = idOf(this.actRow ?? {});
    if (!id) return;
    const cur = this.actKey === 'currency';
    const text = String((cur ? v['currencies'] : v['game_ids']) ?? '').trim();
    let parsed: unknown = null;
    if (cur) {
      try {
        parsed = JSON.parse(text);
      } catch {
        parsed = null;
      }
      // 空文本/语法错/解出来不是数组一律拦下：`required|array` 只认数组，发字符串过去
      // 会回一句英文 validator 原文（本树 13 语言的界面里突兀），这里给本地提示
      if (!Array.isArray(parsed)) {
        this.actError.set(
          this.i18n.t('app.field_must_be_json_array', { name: this.i18n.t('game.currency_act') }),
        );
        return;
      }
    }
    const ids = text
      .split('\n')
      .map((s) => s.trim())
      .filter(Boolean);
    this.actSaving.set(true);
    this.actError.set('');
    try {
      await this.api.post(
        cur ? G + 'currency/manage' : G + 'category/assign',
        cur ? { game_id: id, currencies: parsed } : { category_id: id, game_ids: ids },
      );
      this.actOpen.set(false);
      await this.load();
    } catch (e) {
      this.actError.set(errText(e));
    } finally {
      this.actSaving.set(false);
    }
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
