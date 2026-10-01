/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Params, Row } from '../core/api.service';
import { Crud, CrudPage, Field, Opt } from '../core/crud';
import { idOf, kvLabel, kvOf } from '../core/render';
import { enabledLabel, errText } from '../core/util';
import { t, T } from '../core/i18n/i18n';
import { Drawer, Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';

const M = '/admin/v1/';

/** 券状态筛选（CouponController::list 的 status 参数，落 game_coupon.status 的 0/1）；label 是**词条键** */
const COUPON_STATUS = [
  { value: '', label: 'coupon.all_status' },
  { value: '1', label: 'app.enabled' },
  { value: '0', label: 'app.disabled' },
];

/** 运行期把选项注进常量字段（crud() 是 computed ⇒ 读得到信号，选项随游戏表刷新） */
function withOptions(fields: Field[], name: string, options: Opt[]): Field[] {
  return fields.map((f) => (f.name === name ? { ...f, options } : f));
}

/**
 * 字段真值 = CouponController::create/update 的 validator + game_coupon 列宽。
 * 金额（value / min_amount / max_discount）全是 DECIMAL(18,4) ⇒ **text**，格式交服务端 numeric 规则；
 * 三个都可留空不提交（update 是 sometimes：空串既过不了 numeric 也过不了 date，留空即不修改）。
 * total_qty / user_limit 是 INT UNSIGNED 计数（不是金额）⇒ 走 number 控件。
 * status **不在表单里**：create 硬编码 1（CouponController.php:116），摆开关只是骗人；
 * 启用/停用走行内动作（update 收 status，见 crud() 的 statused）。
 * game_id 必须 keepIfEmpty：update 里 `$gameId !== null` 就 decodeId()，空串会 400「无效的加密ID」。
 * conditions 是 JSON 列、admin 的 validator 不收它（只有 service 侧的 conditionsBlockReason 读它）
 * ⇒ 表单里没有这个字段，别照 DB 列名硬凑一个。
 */
const COUPON_FIELDS: Field[] = [
  { name: 'name', label: 'coupon.name', type: 'text', required: true, placeholder: 'coupon.name_hint' },
  {
    name: 'type',
    label: 'coupon.type',
    type: 'select',
    required: true,
    options: [
      { value: 'fixed', label: 'coupon.type_fixed' },
      { value: 'rate', label: 'coupon.type_rate' },
    ],
  },
  {
    name: 'value',
    label: 'coupon.value',
    type: 'text',
    required: true,
    hint: 'coupon.value_hint',
  },
  {
    name: 'min_amount',
    label: 'coupon.min_amount',
    type: 'text',
    keepIfEmpty: true,
    placeholder: 'coupon.min_amount_hint',
  },
  {
    name: 'max_discount',
    label: 'coupon.max_discount',
    type: 'text',
    keepIfEmpty: true,
    placeholder: 'coupon.max_discount_hint',
  },
  { name: 'game_id', label: 'coupon.game_id', type: 'select', keepIfEmpty: true },
  { name: 'total_qty', label: 'coupon.total_qty', type: 'number', placeholder: 'coupon.total_qty_hint' },
  { name: 'user_limit', label: 'coupon.user_limit', type: 'number', placeholder: 'coupon.user_limit_hint' },
  {
    name: 'start_at',
    label: 'coupon.start_at',
    type: 'text',
    keepIfEmpty: true,
    hint: 'coupon.time_hint',
  },
  {
    name: 'end_at',
    label: 'coupon.end_at',
    type: 'text',
    keepIfEmpty: true,
    hint: 'coupon.end_hint',
  },
];

/**
 * 字段真值 = VipLevelController::create/update 的 validator + validateBenefits 的键白名单。
 * level 只在 create（update 白名单里没有）⇒ createOnly；
 * benefits 是 JSON 对象文本，键只认 exchange_discount / withdraw_fee_discount / rate_bonus
 * （VipLevelController::validateBenefits 的白名单，值都是 [0,1] 的十进制数）。
 * 键名打错不会报错、按 0 处理 ⇒ 别自造键。
 * VIP 等级没有 status 列，故不开 statused。
 */
const VIP_FIELDS: Field[] = [
  {
    name: 'level',
    label: 'vip.level',
    type: 'number',
    required: true,
    createOnly: true,
    placeholder: 'vip.level_hint',
  },
  { name: 'name', label: 'vip.name', type: 'text', required: true, placeholder: 'vip.name_hint' },
  {
    name: 'required_exp',
    label: 'vip.required_exp',
    type: 'number',
    required: true,
    placeholder: 'vip.required_exp_hint',
  },
  {
    name: 'benefits',
    label: 'vip.benefits',
    type: 'textarea',
    required: true,
    placeholder: 'vip.benefits_hint',
  },
];

@Component({
  selector: 'app-marketing',
  imports: [StateBlock, Table, Pager, Tabs, Drawer, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'nav.marketing' | t }}</h1>
      <span class="sub">{{ 'marketing.subtitle' | t }}</span>
      <div class="spacer"></div>
      @if (tab() === 'coupon') {
        <!-- 券列表只认 status/type/game_id（keyword 它不看）；原先的「券码」框筛什么都不变。
             select 不能绑 [value]（早于 @for 的 option）⇒ 逐项 [selected] -->
        <select class="input" (change)="setStatus($any($event.target).value)">
          @for (s of COUPON_STATUS; track s.value) {
            <option [value]="s.value" [selected]="status() === s.value">{{ s.label | t }}</option>
          }
        </select>
      }
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ {{ 'app.create' | t }}</button>
      }
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      <div class="card">
        <div class="card-body">
          <ui-table
            [rows]="rows()"
            [heads]="heads()"
            [clickable]="tab() === 'coupon'"
            [actions]="actions()"
            (pick)="open($event)"
            (act)="run($event.row, $event.key)"
          />
        </div>
      </div>
    </ui-state>

    @if (paged() && rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <!-- ui-drawer 的 title() 不过 t 管道（是**值**不是词条键）⇒ 这里先查表再传 -->
    <ui-drawer [open]="detail() !== null" [title]="'coupon.stats_title' | t" (close)="close()">
      @if (detail(); as d) {
        <dl class="kv">
          @for (p of info(); track p.label) {
            <dt>{{ p.label }}</dt>
            <dd>{{ p.value }}</dd>
          } @empty {
            <dt>{{ 'coupon.empty_hint' | t }}</dt>
            <dd>{{ (statsLoading() ? 'coupon.stats_loading' : 'coupon.stats_empty') | t }}</dd>
          }
        </dl>
      }
    </ui-drawer>

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
export class Marketing extends CrudPage {
  protected readonly tabs = [
    { key: 'coupon', label: 'coupon.title' },
    { key: 'vip', label: 'vip.title' },
  ];
  protected readonly tab = signal('coupon');

  /**
   * 只有券列表是 page+limit 分页的：/vip/level/list 是 `VipLevel::orderBy('level')->get()`（整表、
   * 无 total），且它同时被别处当「全部等级」下拉的数据源 ⇒ 给它挂分页器会给出一个假的第 2 页。
   */
  protected readonly paged = computed(() => this.tab() === 'coupon');

  protected readonly detail = signal<Row | null>(null);
  protected readonly stats = signal<unknown>(null);
  protected readonly statsLoading = signal(false);
  protected readonly COUPON_STATUS = COUPON_STATUS;
  protected readonly status = signal('');

  /** 适用游戏的选项（券表的 game_id 要 hashid） */
  private readonly games = signal<Opt[]>([]);
  /** 游戏表取失败的原因：塞进字段 label —— 不能因为游戏表挂了就把券页打成错误态（同 settings 的权限树） */
  private readonly gamesErr = signal('');

  /** 券统计键值：字段名走 `kvLabel`（`col.<字段名>` 词条），抽屉里不摆 `usage_rate` 这种裸列名 */
  protected readonly info = computed(() => kvOf(this.stats(), kvLabel));

  private readonly paths: Record<string, string> = {
    coupon: M + 'coupon/list',
    vip: M + 'vip/level/list',
  };

  protected readonly heads = computed((): Record<string, string> => {
    if (this.tab() !== 'coupon') return {};
    return {
      name: 'coupon.name',
      type: 'coupon.type',
      value: 'coupon.head.value',
      min_amount: 'coupon.min_amount',
      max_discount: 'coupon.max_discount',
      game_name: 'coupon.game_id',
      total_qty: 'coupon.total_qty',
      used_qty: 'coupon.used_qty',
      user_limit: 'coupon.user_limit',
      start_at: 'coupon.start_at',
      end_at: 'coupon.end_at',
      // 值列改指 status_label（原文案是「状态(0停用/1启用)」= 把数据库编码当标签，已改平）
      status_label: 'coupon.head.status',
    };
  });

  /**
   * 优惠券：CRUD + 启停。status 不在表单里（create 硬编码 1），行内「启用/停用」走 update 的局部
   * PUT {status}（券没有专用 toggle 端点）—— 已有用户领取的券（used_qty>0）会被后端挡下（400），
   * 原样透出到列表级 error 就够了。
   * conditions 不在字段表里：admin 端的 validator 根本不收它（见 COUPON_FIELDS 的注释）。
   */
  protected override crud(): Crud | null {
    if (this.tab() === 'coupon') {
      return {
        noun: 'coupon.noun',
        fields: this.couponFields(),
        statused: true,
        // destroy 是**级联删除**：CouponController::destroy 先删掉全部 game_user_coupon 领取记录
        // （label 只用在删除确认里，是底座现成的那个缝）
        label: (row) =>
          this.i18n.t('coupon.delete_label', { name: row['name'] ?? idOf(row) }),
        ends: {
          create: M + 'coupon/create',
          update: (id) => M + 'coupon/' + id,
          remove: (id) => M + 'coupon/' + id,
        },
      };
    }
    if (this.tab() !== 'vip') return null;
    return {
      noun: 'vip.noun',
      fields: VIP_FIELDS,
      label: (row) => `VIP ${row['level']} ${row['name'] ?? ''}`.trim(),
      ends: {
        create: M + 'vip/level/create',
        update: (id) => M + 'vip/level/' + id,
        remove: (id) => M + 'vip/level/' + id,
      },
    };
  }

  /**
   * 券字段：game_id 的选项来自游戏表（值必须是 hashid，update 里 decodeId 认它）。
   * 选项表里**刻意不含空值项**：空 = 不提交（keepIfEmpty），而空串发给后端是 400「无效的加密ID」
   * ⇒ 「改回全平台」这件事本树表达不了，用「不修改」显示比摆一个必然 400 的选项诚实。
   * 表挂了指名说，否则下拉里只剩「（当前值）」补项，运营会以为券没绑游戏。
   */
  private couponFields(): Field[] {
    const err = this.gamesErr();
    const fields = withOptions(COUPON_FIELDS, 'game_id', this.games());
    if (!err) return fields;
    // f.label 是**词条键**（渲染时才查表）⇒ 拼后缀前先把它译出来，否则界面上会露出键名
    return fields.map((f) =>
      f.name === 'game_id'
        ? { ...f, label: t('coupon.games_failed', { name: t(f.label), error: err }) }
        : f,
    );
  }

  protected setStatus(v: string): void {
    this.status.set(v);
    this.search();
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.close();
    void this.load();
  }

  protected close(): void {
    this.detail.set(null);
    this.stats.set(null);
    this.statsLoading.set(false);
  }

  protected override async fetch(): Promise<Page<Row>> {
    const tab = this.tab();
    const url = this.paths[tab] ?? this.paths['coupon']!;
    // keyword 不再发（券与 VIP 列表都不认它）；status 只在券标签页有值
    const params: Params = { page: this.page(), page_size: this.pageSize };
    if (tab === 'coupon') params['status'] = this.status();
    // 券的 game_id 单选要游戏表 —— 与列表一起取：弹框是**非受控**的（@if 重建 DOM），
    // 打开后补不了 option，选项必须在打开之前就绪。loadGames 自己吞异常，游戏表挂了列表照常。
    const [res] = await Promise.all([this.api.list<Row>(url, params), this.loadGames()]);
    if (tab !== 'coupon') return res;
    const names = new Map(this.games().map((g) => [g.value, g.label]));
    // 两处平铺/归一都是**只给表格与表单看的**，不动原始值：
    // game_name = 游戏名（列表里摆 hashid 谁都读不懂）；game_id 0 → '' （0 是「全平台」，
    // 不归一的话表单会把 '0' 当成「表外的当前值」置顶勾一条，看着像绑了一个叫 0 的游戏）
    return {
      ...res,
      list: res.list.map((r) => {
        const gid = this.gameId(r);
        return {
          ...r,
          game_id: gid,
          game_name: gid ? (names.get(gid) ?? gid) : t('coupon.all_platforms'),
          // 券 status 是 TINYINT 0/1（create 硬编码 1，启停走局部 PUT）⇒ 摊平一列文案；
          // 原值留着 —— 筛选下拉、行内动作的 when() 判据都读它
          status_label: enabledLabel(r['status']),
        };
      }),
    };
  }

  /**
   * 行的 game_id：0 / '0' / null 都是「全平台」⇒ 归一成空串（表单靠空串显示「不修改」），
   * 有值的原样返回（是 hashid，**不是数字** —— 别拿 num() 判它，NaN 会把每个 game_id 都判成空）。
   */
  private gameId(row: Row): string {
    const v = row['game_id'];
    if (v === null || v === undefined || v === '' || v === 0 || v === '0') return '';
    return String(v);
  }

  /**
   * 取游戏表 → 存成选项域。失败**不抛**：游戏表挂了不该连券改名都做不了，
   * 留一句 gamesErr（couponFields() 会把它写进字段 label），列表该出还出。
   */
  private async loadGames(): Promise<void> {
    try {
      const res = await this.api.list<Row>(M + 'game/list', { page: 1, page_size: 200 });
      this.games.set(
        res.list.map((g) => {
          const id = String(g['id'] ?? '');
          return { value: id, label: String(g['name'] ?? id) };
        }),
      );
      this.gamesErr.set('');
    } catch (e) {
      this.games.set([]);
      this.gamesErr.set(errText(e));
    }
  }

  /** 详情：先展示列表行，再拉 coupon/{hashid}/stats 覆盖统计面板 */
  protected async open(row: Row): Promise<void> {
    const id = idOf(row);
    this.detail.set(row);
    this.stats.set(null);
    if (!id) return;
    this.statsLoading.set(true);
    try {
      this.stats.set(await this.api.get<unknown>(M + 'coupon/' + id + '/stats'));
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.statsLoading.set(false);
    }
  }
}
