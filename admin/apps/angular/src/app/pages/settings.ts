/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Params, Row } from '../core/api.service';
import { Crud, CrudPage, Field, Opt } from '../core/crud';
import { idOf, json, scalarsOf } from '../core/render';
import { errText, num } from '../core/util';
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
  { name: 'group', label: '分组', type: 'text', required: true, createOnly: true, placeholder: '最长 100' },
  { name: 'key', label: '配置键', type: 'text', required: true, createOnly: true, placeholder: '最长 100' },
  {
    name: 'value',
    label: '配置值',
    type: 'textarea',
    required: true,
    // store/update 都是 required|string ⇒ 空串会被判 422（Laravel 的 required 拒空串），
    // 所以留空 = 不提交，而不是「清空该值」
    keepIfEmpty: true,
    placeholder: '不能为空；留空不改（后端 required 拒空串）',
  },
  {
    name: 'type',
    label: '值类型',
    type: 'select',
    keepIfEmpty: true,
    options: [
      { value: 'string', label: 'string' },
      { value: 'int', label: 'int' },
      { value: 'bool', label: 'bool' },
      { value: 'json', label: 'json' },
    ],
  },
  { name: 'description', label: '配置说明', type: 'text', full: true, placeholder: '最长 255' },
];

/**
 * 字段真值 = RoleController::store/update 的 validator（+ game_admin_role 列宽）。
 * slug 只在 store（update 的落库白名单里没有它）⇒ createOnly。
 * status 不摆进表单：create 默认 1、update 认 in:0,1 —— 状态改走行内「启用/停用」（局部 PUT {status}），
 * 与其它模块同一个入口，不在表单里再摆一个开关。
 * permission_ids 是**数组**字段（type multi）：后端 `decodePermissionIds()` 只认 hashid
 * （非 hashid 直接 400，不会静默落 0），选项在运行期由权限树注入，回填直接用行里的
 * `permission_ids`（RoleController::index 现在也回传 hashid 数组）。
 * 删除有 confirmPassword 守卫（destroy 还会 detach 权限与用户）⇒ deletePassword + 文案点名关联。
 */
const ROLE_FIELDS: Field[] = [
  { name: 'name', label: '角色名称', type: 'text', required: true, placeholder: '最长 50' },
  {
    name: 'slug',
    label: '角色标识',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: '最长 50',
  },
  { name: 'description', label: '角色描述', type: 'text', full: true, placeholder: '最长 255' },
  {
    name: 'permission_ids',
    label: '权限（按住 Ctrl/⌘ 多选；清空 = 收回全部权限）',
    type: 'multi',
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
  { name: 'name', label: '权限名称', type: 'text', required: true, placeholder: '最长 50' },
  {
    name: 'slug',
    label: '权限标识',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: '最长 100',
  },
  {
    name: 'type',
    label: '权限类型',
    type: 'select',
    required: true,
    createOnly: true,
    options: [
      { value: '1', label: '菜单' },
      { value: '2', label: '按钮' },
      { value: '3', label: '接口' },
    ],
  },
  {
    name: 'parent_id',
    label: '父级（不选 = 建在根上）',
    type: 'select',
    createOnly: true,
  },
  { name: 'icon', label: '图标', type: 'text', placeholder: '最长 50' },
  { name: 'path', label: '前端路由路径', type: 'text', full: true, placeholder: '最长 255' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '数字越小越靠前' },
];

/**
 * 权限树是嵌套结构（node.children），表格只认平铺行 ⇒ 摊平：层级画在 tree 列里、
 * 缩进深度留在 depth 上（表单选项也用这一份，两处的层次长得一样），
 * 父节点名解析进 parent_name 列（parent_id 是 hashid，而 update 恰好不收 parent_id ——
 * 「父级」就只在列表里做只读回显）。
 * 只加不删改：node 的 name/slug/type 原样保留（表单预填直接读它们，动了就是「编辑一次改一次名」）。
 */
function flattenTree(nodes: Row[], depth = 0, parent = ''): Row[] {
  const out: Row[] = [];
  for (const n of nodes) {
    const children = Array.isArray(n['children']) ? (n['children'] as Row[]) : [];
    out.push({
      ...n,
      tree: depth ? '　'.repeat(depth - 1) + '└ ' : '根',
      depth,
      parent_name: depth ? parent : '（根）',
    });
    if (children.length) out.push(...flattenTree(children, depth + 1, String(n['name'] ?? '')));
  }
  return out;
}

/** 运行期把选项注进常量字段（crud() 是 computed ⇒ 读得到信号，选项随树刷新） */
function withOptions(fields: Field[], name: string, options: Opt[]): Field[] {
  return fields.map((f) => (f.name === name ? { ...f, options } : f));
}

@Component({
  selector: 'app-settings',
  imports: [StateBlock, StatCard, Table, Pager, Tabs, FormModal],
  template: `
    <div class="page-head">
      <h1>系统设置</h1>
      <span class="sub">配置 / 角色 / 权限 / 指标 / 健康</span>
      <div class="spacer"></div>
      @if (isList()) {
        <input
          class="input"
          placeholder="键名 / 名称 / ID"
          [value]="keyword()"
          (input)="keyword.set($any($event.target).value)"
          (keyup.enter)="search()"
        />
        <button class="btn" (click)="search()">查询</button>
      }
      <button class="btn" (click)="load()">刷新</button>
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ 新建</button>
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
            <pre class="raw">{{ text() || '暂无指标' }}</pre>
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
            <summary>健康检查原始响应</summary>
            <pre class="raw">{{ pretty(d) }}</pre>
          </details>
        }
      } @else {
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
    { key: 'config', label: '系统配置' },
    { key: 'role', label: '角色' },
    { key: 'permission', label: '权限' },
    { key: 'metrics', label: '监控指标' },
    { key: 'health', label: '健康检查' },
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
   * 权限树（摊平后的节点）：既是权限标签页的列表，也是两个表单的选项来源
   * （角色的 permission_ids 多选、权限的 parent_id 单选）。
   */
  private readonly nodes = signal<Row[]>([]);
  /** 权限树取失败的原因：塞进字段 label —— 不能因为树挂了就把整个角色页打成错误态 */
  private readonly treeErr = signal('');

  /**
   * 选项 = 权限树节点（值 = 节点 hashid，一律 String：hashid 是字符串）。
   * 缩进用 depth 而不是解析 label 里的空格 —— 两个表单与列表共用同一份层次。
   */
  private readonly permOptions = computed<Opt[]>(() =>
    this.nodes().map((n) => ({
      value: String(n['id'] ?? ''),
      label: '　'.repeat(num(n['depth'])) + String(n['name'] ?? ''),
    })),
  );

  /**
   * 表头：留空走自动推列（配置项字段本来就杂）；角色 / 权限用显式列，
   * 顺便把枚举的含义写进表头 —— 值原样显示，不改数据（改了列表就等于改了表单预填）。
   */
  protected readonly heads = computed((): Record<string, string> => {
    if (this.tab() === 'role') {
      return {
        name: '角色名称',
        slug: '角色标识',
        description: '角色描述',
        status: '状态(0停用/1启用)',
        users_count: '关联用户数',
      };
    }
    if (this.tab() === 'permission') {
      return {
        tree: '层级',
        name: '名称',
        parent_name: '父级',
        slug: '权限标识',
        type: '类型(1菜单/2按钮/3接口)',
        icon: '图标',
        path: '路径',
        sort: '排序',
      };
    }
    return {};
  });

  protected isList(): boolean {
    return this.tab() in this.paths;
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
        noun: '配置项',
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
        noun: '角色',
        fields: this.roleFields(),
        statused: true,
        deletePassword: true,
        // destroy 会 detach 掉权限与用户关联 —— 删之前把这件事说清楚，别让人以为只是删一行
        label: (row) => `${row['name'] ?? idOf(row)}（并解除其权限与用户关联）`,
        ends: {
          create: S + 'role',
          update: (id) => S + 'role/' + id,
          remove: (id) => S + 'role/' + id,
        },
      };
    }
    if (tab === 'permission') {
      return {
        noun: '权限',
        fields: withOptions(PERMISSION_FIELDS, 'parent_id', this.permOptions()),
        deletePassword: true,
        // destroy 级联删子权限（PermissionController::destroy）
        label: (row) => `${row['name'] ?? idOf(row)}（连同其全部子权限）`,
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
   * 角色的字段：permission_ids 的选项来自权限树。树取失败时在 label 上直说 ——
   * 否则多选里只剩「（当前值）」补项，运营会以为「这个角色本来就没权限」。
   * （选项为空也丢不了授权：ui-form 的 multi() 把当前值补成勾选项，见那条注释。）
   */
  private roleFields(): Field[] {
    const err = this.treeErr();
    const fields = withOptions(ROLE_FIELDS, 'permission_ids', this.permOptions());
    if (!err) return fields;
    return fields.map((f) =>
      f.name === 'permission_ids' ? { ...f, label: `${f.label} —— 权限树加载失败：${err}` } : f,
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
    // 角色的权限多选要整棵权限树 —— 列表和树一起取：弹框是**非受控**的（靠 @if 重建 DOM，
    // 打开后不重渲染 option），选项必须在打开之前就绪，补不了。loadTree 自己吞异常，树挂了列表照常。
    if (tab === 'role') {
      const [res] = await Promise.all([this.api.list<Row>(url, params), this.loadTree()]);
      return res;
    }
    const res = await this.api.list<Row>(url, params);
    // 权限接口返回的是整棵嵌套树 —— 不摊平的话表格只显示根节点，子权限既看不见也改不了
    if (tab !== 'permission') return res;
    const list = flattenTree(res.list ?? []);
    this.nodes.set(list);
    return { ...res, list, total: list.length, page: 1, limit: this.pageSize };
  }

  /**
   * 取权限树 → 摊平 → 存成选项域。失败**不抛**：权限树挂了不该连角色改名都做不了，
   * 所以留一句 treeErr（roleFields() 会把它写进字段 label），列表该出还出。
   */
  private async loadTree(): Promise<void> {
    try {
      const res = await this.api.list<Row>(this.paths['permission']!);
      this.nodes.set(flattenTree(res.list ?? []));
      this.treeErr.set('');
    } catch (e) {
      this.nodes.set([]);
      this.treeErr.set(errText(e));
    }
  }
}
