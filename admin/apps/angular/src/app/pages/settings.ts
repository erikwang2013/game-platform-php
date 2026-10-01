/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Params, Row } from '../core/api.service';
import { Crud, CrudPage, Field, Opt } from '../core/crud';
import { idOf, json, scalarsOf } from '../core/render';
import { PNode, flatten, toTree } from '../core/tree';
import { errText } from '../core/util';
import { T, t } from '../core/i18n/i18n';
import { Pager, StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';

const S = '/admin/v1/';

/**
 * 字段真值 = ConfigController::store/update 的 validator。
 * group/key 只在 store（update 的白名单里没有，且 group+key 是唯一键）⇒ createOnly；
 * type 只是取值口径提示，后端不枚举收口（未知 type 走 PlatformConfig::get 的 default 分支）,
 * 存量行里的怪值由 ui-form 的 offList() 置顶补一条原样带回。
 * 删除是敏感操作：ConfigController::destroy 走 confirmPassword 守卫 ⇒ deletePassword。
 */
const CONFIG_FIELDS: Field[] = [
  {
    name: 'group',
    label: 'config.group',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'config.group_hint',
  },
  {
    name: 'key',
    label: 'config.key',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'config.key_hint',
  },
  {
    name: 'value',
    label: 'config.value',
    type: 'textarea',
    required: true,
    // store/update 都是 required|string ⇒ 空串会被判 422（Laravel 的 required 拒空串），
    // 所以留空 = 不提交，而不是「清空该值」
    keepIfEmpty: true,
    placeholder: 'config.value_hint',
  },
  {
    name: 'type',
    label: 'config.type',
    type: 'select',
    keepIfEmpty: true,
    // 选项是 PlatformConfig::get 的取值口径（string/int/bool/json）原文，不译
    options: [
      { value: 'string', label: 'string' },
      { value: 'int', label: 'int' },
      { value: 'bool', label: 'bool' },
      { value: 'json', label: 'json' },
    ],
  },
  { name: 'description', label: 'config.description', type: 'text', full: true, placeholder: 'config.description_hint' },
];

/**
 * 字段真值 = RoleController::store/update 的 validator（+ game_admin_role 列宽）。
 * slug 只在 store（update 的落库白名单里没有它）⇒ createOnly。
 * status 不摆进表单：create 默认 1、update 认 in:0,1 —— 状态改走行内「启用/停用」（局部 PUT {status}），
 * 与其它模块同一个入口，不在表单里再摆一个开关。
 * permission_ids 是**数组**字段（type tree）：后端 `decodePermissionIds()` 只认 hashid
 * （非 hashid 直接 400，不会静默落 0），节点树在运行期由权限端点注入，回填直接用行里的
 * `permission_ids`（RoleController::index 现在也回传 hashid 数组）。勾中的是**一维** hashid 数组
 * —— 树只是选择方式，提交形状与 multi 完全一致（见 crud.norm / form-modal.fire 的同一分支）。
 * 删除有 confirmPassword 守卫（destroy 还会 detach 权限与用户）⇒ deletePassword + 文案点名关联。
 */
const ROLE_FIELDS: Field[] = [
  { name: 'name', label: 'role.name', type: 'text', required: true, placeholder: 'role.name_hint' },
  {
    name: 'slug',
    label: 'role.slug',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'role.slug_hint',
  },
  {
    name: 'description',
    label: 'role.description',
    type: 'text',
    full: true,
    placeholder: 'role.description_hint',
  },
  {
    name: 'permission_ids',
    label: 'role.permission_ids_hint',
    type: 'tree',
    full: true,
  },
];

/**
 * 字段真值 = PermissionController::store/update 的 validator（+ game_admin_permission 列宽）。
 * update 只落 name/icon/path/sort ⇒ slug / type / parent_id 都是 createOnly（改父级要防环，后端 update 不收）。
 * type 是 1菜单/2按钮/3接口 三值 ⇒ select（0/1 翻转控件会把它压成「菜单」）。
 * parent_id 收 hashid（decodeParentId()：空 / '0' = 根，其余按 hashid 解，裸数字 400 —— fail-fast
 * 而不是把 UI 手里的 hashid 剁成 0），选项 = 权限树节点（运行期注入），所以「新建子权限」在 UI 里可达；
 * 编辑态的回显由列表的「父级」列承担（update 改不了父级，摆个输入框就是骗人）。
 * 删除有 confirmPassword 守卫，且会级联删子权限 ⇒ deletePassword + 文案点名级联。
 */
const PERMISSION_FIELDS: Field[] = [
  { name: 'name', label: 'permission.name', type: 'text', required: true, placeholder: 'permission.name_hint' },
  {
    name: 'slug',
    label: 'permission.slug',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'permission.slug_hint',
  },
  {
    name: 'type',
    label: 'permission.type',
    type: 'select',
    required: true,
    createOnly: true,
    // 值仍是后端枚举 '1'/'2'/'3'，只有文案可译
    options: [
      { value: '1', label: 'permission.type_menu' },
      { value: '2', label: 'permission.type_button' },
      { value: '3', label: 'permission.type_api' },
    ],
  },
  {
    name: 'parent_id',
    label: 'permission.parent_id',
    type: 'select',
    createOnly: true,
  },
  { name: 'icon', label: 'permission.icon', type: 'text', placeholder: 'permission.icon_hint' },
  {
    name: 'path',
    label: 'permission.path',
    type: 'text',
    full: true,
    placeholder: 'permission.path_hint',
  },
  { name: 'sort', label: 'permission.sort', type: 'number', placeholder: 'permission.sort_hint' },
];

/**
 * 权限树 → 表格行：DFS 平铺，depth 交给 ui-table 的 treeKey（缩进 + 展开箭头都由它画，页面不再
 * 自己拼「└」文本），父节点名解析进 parent_name 列（parent_id 是 hashid，而 update 恰好不收
 * parent_id ——「父级」就只在列表里做只读回显）。
 * 只加不删改：node 的 name/slug/type 原样保留（表单预填直接读它们，动了就是「编辑一次改一次名」）。
 */
function treeRows(tree: PNode[]): Row[] {
  // 占位符也过词条：列表列头与它的取值同源（t() 在调用时读语言，切语言后重取列表即刷新）
  return flatten(tree).map((f) => ({
    ...f.node.row,
    depth: f.depth,
    parent_name: f.parent || t('permission.root'),
  }));
}

/** 运行期把选项/节点树注进常量字段（crud() 是 computed ⇒ 读得到信号，选项随树刷新） */
function patch(fields: Field[], name: string, extra: Partial<Field>): Field[] {
  return fields.map((f) => (f.name === name ? { ...f, ...extra } : f));
}

@Component({
  selector: 'app-settings',
  imports: [StateBlock, StatCard, Table, Pager, Tabs, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'settings.title' | t }}</h1>
      <span class="sub">{{ 'settings.subtitle' | t }}</span>
      <div class="spacer"></div>
      @if (isList()) {
        <input
          class="input"
          [placeholder]="'settings.search_hint' | t"
          [value]="keyword()"
          (input)="keyword.set($any($event.target).value)"
          (keyup.enter)="search()"
        />
        <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
      }
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
      @if (tab() === 'config' || tab() === 'role') {
        <button class="btn" [disabled]="busyExport()" (click)="exportTable()">
          {{ (busyExport() ? 'app.exporting' : 'export.excel') | t }}
        </button>
      }
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ {{ 'app.create' | t }}</button>
      }
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state
      [loading]="loading()"
      [error]="error()"
      [empty]="isList() ? !rows().length : !text() && !scalars().length"
    >
      @if (tab() === 'metrics') {
        <div class="card">
          <div class="card-body">
            <pre class="raw">{{ text() || ('settings.metrics_empty' | t) }}</pre>
          </div>
        </div>
      } @else if (tab() === 'health') {
        @if (scalars().length) {
          <div class="tiles">
            @for (s of scalars(); track s.k) {
              <ui-stat [label]="s.k" [value]="s.v" />
            }
          </div>
        }
        @if (raw(); as d) {
          <details class="raw-box">
            <summary>{{ 'settings.health_raw' | t }}</summary>
            <pre class="raw">{{ pretty(d) }}</pre>
          </details>
        }
      } @else {
        <div class="card">
          <div class="card-body">
            <!-- 权限页签：树形展示（缩进 + 展开箭头），行内动作照旧；其它页签是平表（treeKey 空） -->
            <ui-table
              [rows]="rows()"
              [heads]="heads()"
              [actions]="actions()"
              [treeKey]="tab() === 'permission' ? 'name' : ''"
              (act)="run($event.row, $event.key)"
            />
          </div>
        </div>
      }
    </ui-state>

    <!-- 权限树接口不分页（一次返回整棵树，page/limit 都不看）⇒ 这页不渲染分页器：
         有分页器就会给出一个点进去还是同一批数据的第 2 页 -->
    @if (isList() && rows().length && tab() !== 'permission') {
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
export class Settings extends CrudPage {
  protected readonly tabs = [
    { key: 'config', label: 'config.title' },
    { key: 'role', label: 'role.title' },
    { key: 'permission', label: 'permission.title' },
    { key: 'metrics', label: 'settings.tab.metrics' },
    { key: 'health', label: 'settings.tab.health' },
  ];
  protected readonly tab = signal('config');
  protected readonly raw = signal<unknown>(null);
  protected readonly text = signal('');

  protected readonly scalars = computed(() => scalarsOf(this.raw()));

  private readonly paths: Record<string, string> = {
    config: S + 'config',
    // 角色 / 权限是 Route::resource ⇒ 列表就是 GET /admin/v1/role、/permission（没有 /list）
    role: S + 'role',
    permission: S + 'permission',
  };

  /**
   * 权限树（**嵌套原样**，不摊平）：角色的树多选要它，权限页签的树形展示与两个表单的选项都从它派生
   * （摊平只发生在展示/选项这两个消费端，树的层次信息不再在解析层被拍掉）。
   */
  private readonly tree = signal<PNode[]>([]);
  /** 权限树取失败的原因：塞进字段 label —— 不能因为树挂了就把整个角色页打成错误态 */
  private readonly treeErr = signal('');

  /**
   * 选项 = 权限树节点（值 = 节点 hashid，一律 String：hashid 是字符串）：权限表单的 parent_id 单选用。
   * 缩进用 depth 而不是解析 label 里的空格 —— 表单与列表共用同一份层次。
   */
  private readonly permOptions = computed<Opt[]>(() =>
    flatten(this.tree()).map((f) => ({
      value: f.node.id,
      label: '　'.repeat(f.depth) + f.node.name,
    })),
  );

  /**
   * 表头：留空走自动推列（配置项字段本来就杂）；角色 / 权限用显式列，
   * 顺便把枚举的含义写进表头 —— 值原样显示，不改数据（改了列表就等于改了表单预填）。
   */
  protected readonly heads = computed((): Record<string, string> => {
    if (this.tab() === 'role') {
      return {
        name: 'role.name',
        slug: 'role.slug',
        description: 'role.description',
        status: 'role.head.status',
        users_count: 'role.users_count',
      };
    }
    if (this.tab() === 'permission') {
      return {
        // 头一列就是树列（ui-table 的 treeKey='name'）：层级画在缩进和箭头上，不再占一列文本
        name: 'permission.name',
        parent_name: 'permission.parent_id',
        slug: 'permission.slug',
        type: 'permission.head.type',
        icon: 'permission.icon',
        path: 'permission.path',
        sort: 'permission.sort',
      };
    }
    return {};
  });

  protected isList(): boolean {
    return this.tab() in this.paths;
  }

  /**
   * 导出当前页签对应的**服务端表**。表名真值 = ExportController::getExportColumns 的白名单，
   * 只有这两页对得上（权限树没有可导的表，接口按钮一概不出）。
   */
  protected exportTable(): void {
    void this.exportXlsx(this.tab() === 'config' ? 'system_config' : 'admin_role');
  }

  /**
   * 本批新增角色与权限两个模块的增删改（配置项沿用批次 1）。
   *
   * 端点形状：config 的路由参数名叫 {id} 但值是 hashid（ConfigController 走 decodeId）；
   * role/permission 是 Route::resource，参数名才是 {hashid} —— 三种都靠基类 idOf(row) 取 hashid。
   * 删除：三者都有 confirmPassword 守卫（Config / Role / Permission 的 destroy）⇒ deletePassword。
   * 角色与权限没有专用状态端点，角色的启用/停用走局部 PUT {status}（基类 statused）。
   */
  protected override crud(): Crud | null {
    const tab = this.tab();
    if (tab === 'config') {
      return {
        noun: 'config.noun',
        fields: CONFIG_FIELDS,
        deletePassword: true,
        label: (row) => `${row['group'] ?? ''}.${row['key'] ?? idOf(row)}`,
        ends: {
          create: S + 'config',
          update: (id) => S + 'config/' + id,
          remove: (id) => S + 'config/' + id,
        },
      };
    }
    if (tab === 'role') {
      return {
        noun: 'role.noun',
        fields: this.roleFields(),
        statused: true,
        deletePassword: true,
        // destroy 会 detach 掉权限与用户关联 —— 删之前把这件事说清楚，别让人以为只是删一行
        label: (row) =>
          t('role.delete_label', { name: row['name'] ?? idOf(row) }),
        ends: {
          create: S + 'role',
          update: (id) => S + 'role/' + id,
          remove: (id) => S + 'role/' + id,
        },
      };
    }
    if (tab === 'permission') {
      return {
        noun: 'permission.noun',
        fields: patch(PERMISSION_FIELDS, 'parent_id', { options: this.permOptions() }),
        deletePassword: true,
        // destroy 级联删子权限（PermissionController::destroy）
        label: (row) => t('permission.delete_label', { name: row['name'] ?? idOf(row) }),
        ends: {
          create: S + 'permission',
          update: (id) => S + 'permission/' + id,
          remove: (id) => S + 'permission/' + id,
        },
      };
    }
    return null;
  }

  /**
   * 角色的字段：permission_ids 的**节点树**来自权限端点。树取失败时在 label 上直说 ——
   * 否则框里空空如也，运营会以为「这个角色本来就没权限」。
   * （树为空也丢不了授权：ui-tree-select 把值里不在树中的 hashid 平铺成「树外」勾选项。
   * 它和 ui-form 的 multi() 是同一条规矩：当前值不许被当成「取消勾选」静默发出去。）
   */
  private roleFields(): Field[] {
    const err = this.treeErr();
    const fields = patch(ROLE_FIELDS, 'permission_ids', { tree: this.tree() });
    if (!err) return fields;
    // f.label 是**词条键**（渲染时才查表）⇒ 拼后缀前先把它译出来，否则界面上会露出键名
    return fields.map((f) =>
      f.name === 'permission_ids'
        ? { ...f, label: t('role.tree_failed', { name: t(f.label), error: err }) }
        : f,
    );
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.raw.set(null);
    this.text.set('');
    void this.load();
  }

  protected pretty(v: unknown): string {
    return json(v);
  }

  protected override async fetch(): Promise<Page<Row>> {
    const tab = this.tab();
    if (tab === 'metrics') {
      try {
        this.text.set(await this.api.getText('/metrics'));
      } catch (e) {
        this.error.set(errText(e));
      }
      return { list: [], total: 0, page: 1, limit: this.pageSize };
    }
    if (tab === 'health') {
      try {
        this.raw.set(await this.api.get<unknown>('/health'));
      } catch (e) {
        this.error.set(errText(e));
      }
      return { list: [], total: 0, page: 1, limit: this.pageSize };
    }
    this.raw.set(null);
    const url = this.paths[tab] ?? this.paths['config']!;
    const params: Params = {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    };
    // 角色的权限多选要整棵权限树 —— 列表和树一起取：弹框是**非受控**的（靠 @if 重建 DOM），
    // 节点树必须在打开之前就绪。loadTree 自己吞异常，树挂了列表照常（角色改名不该被树连坐）。
    if (tab === 'role') {
      const [res] = await Promise.all([this.api.list<Row>(url, params), this.loadTree()]);
      return res;
    }
    const res = await this.api.list<Row>(url, params);
    if (tab !== 'permission') return res;
    // 权限端点回的 data 就是整棵嵌套树（无 list/total、不分页）⇒ 原树存下来，
    // 平铺只服务表格展示：层次留在 depth 上，展开/折叠由 ui-table 按它现算。
    // total 恒 0 是实话（这端点没有总数），分页器本来也不在这个页签渲染。
    const tree = toTree(res.list ?? []);
    this.tree.set(tree);
    return { list: treeRows(tree), total: 0, page: 1, limit: this.pageSize };
  }

  /**
   * 取权限树 → 存原树。失败**不抛**：权限树挂了不该连角色改名都做不了，
   * 所以留一句 treeErr（roleFields() 会把它写进字段 label），列表该出还出。
   */
  private async loadTree(): Promise<void> {
    try {
      const res = await this.api.list<Row>(this.paths['permission']!);
      this.tree.set(toTree(res.list ?? []));
      this.treeErr.set('');
    } catch (e) {
      this.tree.set([]);
      this.treeErr.set(errText(e));
    }
  }
}
