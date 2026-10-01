/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field, Opt } from '../core/crud';
import { T, t } from '../core/i18n/i18n';
import { idOf } from '../core/render';
import { errText } from '../core/util';
import { Pager, StateBlock } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';

const U = '/admin/v1/';

/**
 * **管理端账号**（game_admin_user），不是 C 端的平台用户 —— 后者在 pages/users.ts，
 * 端点 `/admin/v1/platform/user/*`，两棵树必须同时存在且互不串门。
 *
 * 字段真值 = UserController::store/update 的 validator（+ game_admin_user 列宽）：
 *  - username 只在 store 的落库白名单里（update 压根不读它）⇒ createOnly
 *  - password 同理：改密码走行内「重置密码」（要同时带 admin_password 二次确认），
 *    与「新建时设一个初始密码」不是一回事 ⇒ createOnly
 *  - status 不摆进表单：create 缺省落 1、update 认 in:0,1 —— 状态改走行内「启用/停用」，
 *    与 settings.ts 的角色模块同一个入口，不在表单里再摆一个开关
 *  - role_ids 新建时随表单发（JSON 数组），编辑态交给行内「分配角色」⇒ createOnly
 *  - phone/email 列表回的是**脱敏值**（UserController::index 把 138****8888 / a***@x.com 发出来）：
 *    原样不动 ⇒ 与旧值判等相等 ⇒ 不提交，脱敏串不会被回写；要改就得整体重填
 */
const ADMIN_FIELDS: Field[] = [
  {
    name: 'username',
    label: 'admin.username',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'admin.username_hint',
  },
  {
    name: 'password',
    label: 'admin.password',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'admin.password_hint',
  },
  {
    name: 'real_name',
    label: 'admin.real_name',
    type: 'text',
    required: true,
    placeholder: 'admin.real_name_hint',
  },
  {
    name: 'phone',
    label: 'admin.phone',
    type: 'text',
    hint: 'admin.masked_hint',
  },
  {
    name: 'email',
    label: 'admin.email',
    type: 'text',
    hint: 'admin.masked_hint',
  },
  { name: 'role_ids', label: 'admin.role_ids', type: 'multi', createOnly: true, full: true },
];

/**
 * 重置密码：**两个字段名不同**，别混 ——
 * `password` 是新密码（8-32 位含大小写字母+数字，服务端另有一道行内校验），
 * `admin_password` 是**当前操作者自己的**密码，走 BaseController::confirmPassword 二次确认。
 * 两者同名的话「改别人密码」就成了不需要任何人确认的操作。
 */
const RESET_FIELDS: Field[] = [
  {
    name: 'password',
    label: 'admin.new_password',
    type: 'text',
    required: true,
    placeholder: 'admin.password_hint',
  },
  {
    name: 'admin_password',
    label: 'admin.admin_password',
    type: 'text',
    required: true,
    hint: 'admin.admin_password_hint',
  },
];

/** 分配角色：选项在运行期由 /admin/v1/role 注入（清空 = 收回全部角色，后端 sync([])） */
const GRANT_FIELDS: Field[] = [
  { name: 'role_ids', label: 'admin.role_ids', type: 'multi', full: true },
];

@Component({
  selector: 'app-admins',
  imports: [StateBlock, Table, Pager, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'admin.title' | t }}</h1>
      <span class="sub">{{ 'admin.subtitle' | t }}</span>
      <div class="spacer"></div>
      <input
        class="input"
        [placeholder]="'admin.search_hint' | t"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
      <button class="btn btn-primary" (click)="openCreate()">+ {{ 'app.create' | t }}</button>
    </div>

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      <div class="card">
        <div class="card-body">
          <ui-table
            [rows]="view()"
            [heads]="heads"
            [actions]="actions()"
            (act)="run($event.row, $event.key)"
          />
        </div>
      </div>
    </ui-state>

    @if (rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <!-- 新建 / 编辑：用底座 CrudPage 那一个弹框 -->
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

    <!-- 重置密码 / 分配角色：端点同址（PUT /user/{hashid}）但字段集与二次确认方式都不同，
         硬塞进编辑表单等于让「改个姓名」的框里常驻两个密码框 ⇒ 自己拿一套弹框状态（Support 同款） -->
    <ui-form
      [open]="actOpen()"
      [title]="actTitle()"
      [fields]="actFields()"
      [value]="actRow()"
      [error]="actError()"
      [saving]="actSaving()"
      (save)="submitAct($event)"
      (close)="closeAct()"
    />
  `,
})
export class Admins extends CrudPage {
  /** 列表端点就是 GET /admin/v1/user（Route::resource，没有 /user/list） */
  private readonly path = U + 'user';

  protected readonly heads: Record<string, string> = {
    username: 'admin.username',
    real_name: 'admin.real_name',
    phone: 'admin.phone_masked',
    email: 'admin.email_masked',
    status: 'admin.head.status',
    role_names: 'admin.role_ids',
    last_login_at: 'admin.head.last_login',
  };

  /** 角色候选（GET /admin/v1/role）：给「分配角色」的选项，顺带把列表里的 role_ids 翻成名字 */
  private readonly roles = signal<Row[]>([]);
  private readonly roleErr = signal('');

  private readonly roleOpts = computed<Opt[]>(() =>
    this.roles().map((r) => ({
      value: idOf(r),
      label: String(r['name'] ?? '') || idOf(r),
    })),
  );

  private readonly roleNames = computed<Record<string, string>>(() =>
    Object.fromEntries(this.roleOpts().map((o) => [o.value, o.label])),
  );

  /**
   * 列表行 + 角色名回显：`role_ids` 是 hashid 数组，直接铺出去就是一串看不懂的串。
   * 名字取不到（角色列表没取回、角色已被删）就退回 hashid —— 空着会让人以为「这个管理员没有角色」。
   */
  protected readonly view = computed(() =>
    this.rows().map((r) => {
      const ids = Array.isArray(r['role_ids']) ? (r['role_ids'] as unknown[]).map(String) : [];
      const names = this.roleNames();
      return { ...r, role_names: ids.map((id) => names[id] ?? id).join(t('admin.role_join')) };
    }),
  );

  // ---------- 重置密码 / 分配角色（动作型弹框） ----------
  protected readonly actOpen = signal(false);
  protected readonly actTitle = signal('');
  protected readonly actFields = signal<Field[]>(RESET_FIELDS);
  protected readonly actRow = signal<Row | null>(null);
  protected readonly actError = signal('');
  protected readonly actSaving = signal(false);
  private actKey = '';

  /**
   * 写端点真值（config/route.php:78-80 Route::resource('/user')）：
   * 新建 POST /user、改/删 PUT|DELETE /user/{hashid}。
   * 删除是敏感操作 —— UserController::destroy 走 confirmPassword ⇒ deletePassword（body 带 password）。
   * 启用/停用没有专用端点，走局部 PUT {status}（基类 statused + ends.update）。
   */
  protected override crud(): Crud | null {
    return {
      noun: 'admin.noun',
      fields: ADMIN_FIELDS,
      statused: true,
      deletePassword: true,
      label: (row) => this.who(row),
      // 这两个键只负责在行尾**画出按钮**：真正的处理在 run() 里（它们是开弹框，不是服务端动作，
      // 走基类的 extra 分支会在开框前多打一次列表 GET，表格白白闪一下）
      extra: [
        { key: 'grant', label: 'admin.grant' },
        { key: 'reset', label: 'admin.reset' },
      ],
      ends: {
        create: this.path,
        update: (id) => this.path + '/' + id,
        remove: (id) => this.path + '/' + id,
      },
    };
  }

  protected override async fetch(): Promise<Page<Row>> {
    // 角色列表与列表一起取：弹框是**非受控**的（靠 @if 重建 DOM），选项必须在打开之前就绪；
    // loadRoles 自己吞异常，角色端点挂了不该把整个管理员列表打成错误态
    const [res] = await Promise.all([
      this.api.list<Row>(this.path, {
        page: this.page(),
        page_size: this.pageSize,
        keyword: this.keyword(),
      }),
      this.loadRoles(),
    ]);
    return res;
  }

  private async loadRoles(): Promise<void> {
    try {
      const res = await this.api.list<Row>(U + 'role', { page: 1, page_size: 200 });
      this.roles.set(res.list ?? []);
      this.roleErr.set('');
    } catch (e) {
      this.roles.set([]);
      this.roleErr.set(errText(e));
    }
  }

  /** 确认文案 / 弹框标题里的对象标识：用户名，退回真实姓名，再退回 hashid */
  protected who(row: Row): string {
    return String(row['username'] ?? '') || String(row['real_name'] ?? '') || idOf(row);
  }

  /**
   * 当前登录管理员本人。
   *
   * 两串 hashid 出自**同一个连接**：登录回包是 `Container::get('hashids')->encode($user->id)`
   * （AuthController.php:129），列表行是 BaseController::encodeIds → common\HashidsService::encode
   * → 同一个 `Container::get('hashids')`（同一份 config/hashids.php 的 salt）。所以字符串相等
   * 就是「这一行是我自己」，不需要再解回裸 id。
   */
  protected isSelf(row: Row): boolean {
    const me = this.api.user()?.id ?? '';
    return me !== '' && idOf(row) === me;
  }

  /**
   * 行内动作分流：
   *  - 「分配角色 / 重置密码」是开弹框（不是服务端动作）⇒ 就地处理，不走基类的 extra 分支
   *    （那条路会在开框前 `await this.load()`，多打一次列表 GET 且表格闪一下）
   *  - **自我保护**：停用/删除当前登录的自己一概挡在发请求之前 —— 把自己停用等于当场自锁，
   *    把自己删掉连补救入口都没有（后端不拦这条：UserController 只校验被操作对象存不存在）
   */
  protected override async run(row: Row, key: string): Promise<void> {
    if (key === 'grant' || key === 'reset') {
      this.openAct(row, key);
      return;
    }
    if (this.isSelf(row) && (key === 'delete' || key === 'toggle')) {
      this.error.set(this.i18n.t('admin.self_guard'));
      return;
    }
    return super.run(row, key);
  }

  protected openAct(row: Row, key: string): void {
    this.actRow.set(row);
    this.actKey = key;
    this.actFields.set(key === 'grant' ? this.grantFields() : RESET_FIELDS);
    this.actTitle.set(
      this.i18n.t('admin.act_title', {
        action: this.i18n.t(key === 'grant' ? 'admin.grant' : 'admin.reset'),
        name: this.who(row),
      }),
    );
    this.actError.set('');
    this.actOpen.set(true);
  }

  protected closeAct(): void {
    this.actOpen.set(false);
    this.actRow.set(null);
    this.actError.set('');
    this.actKey = '';
  }

  /**
   * 角色多选的字段：选项来自 /admin/v1/role。取失败时在 label 上直说 —— 否则框里空空如也，
   * 运营会以为「这个管理员本来就没有角色」，而 ui-form 的 multi() 会把行里已有的 hashid
   * 平铺成「（当前值）」勾选项，**不会**当成「取消勾选」静默发出去。
   */
  private grantFields(): Field[] {
    const err = this.roleErr();
    const base = GRANT_FIELDS[0]!;
    // base.label 是**词条键**（渲染时才查表）⇒ 拼后缀前先把它译出来
    return [
      err
        ? { ...base, label: this.i18n.t('admin.roles_failed', { name: this.i18n.t(base.label), error: err }) }
        : { ...base, options: this.roleOpts() },
    ];
  }

  /**
   * 提交重置密码 / 分配角色 —— 两条都打 PUT /admin/v1/user/{hashid}，字段集不同。
   *
   * ⚠ role_ids **恒发**，空数组也发：`role_ids: []` 的语义是「收回全部角色」，而后端判的是
   * `$request->has('role_ids')`。urlencoded 表达不了空数组（整个键会消失），一旦那里的键没了，
   * 后端直接跳过 sync，**清空角色静默失效**（界面报成功、角色一个没少）。本树的 Api 走的是
   * Angular HttpClient 的 JSON 分支（普通对象体 ⇒ serializeBody 走 JSON.stringify），
   * 所以这里是「保证键在」而不是「换个编码」。
   */
  protected async submitAct(values: Row): Promise<void> {
    const row = this.actRow();
    const id = row ? idOf(row) : '';
    if (!id || !this.actKey) return;
    let body: Row;
    if (this.actKey === 'grant') {
      const picked = values['role_ids'];
      body = { role_ids: Array.isArray(picked) ? picked.map(String) : [] };
    } else {
      body = {
        password: String(values['password'] ?? ''),
        admin_password: String(values['admin_password'] ?? ''),
      };
    }
    this.actSaving.set(true);
    this.actError.set('');
    try {
      await this.api.request('PUT', this.path + '/' + id, body);
      this.closeAct();
      await this.load();
    } catch (e) {
      // 服务端原因（'Password verification failed' / '密码需 8-32 位…'）原样留在框里，框不关
      this.actError.set(errText(e));
    } finally {
      this.actSaving.set(false);
    }
  }
}
