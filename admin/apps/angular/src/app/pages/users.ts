/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { T, t } from '../core/i18n/i18n';
import { idOf, kvLabel, kvOf } from '../core/render';
import { dash, errText, num } from '../core/util';
import { Drawer, Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';
import {
  amountText,
  amountTone,
  TX_COLS,
  txLabel,
  WALLET_STATS,
} from './wallet-fields';

const U = '/admin/v1/';

/**
 * 字段真值 = PlatformUserController::update 的 validator —— **只收 nickname 与 status**
 * （:112-113），其余键一律不看。
 *  - status 不摆进表单：本模块的 0/1 语义是「封禁 / 解封」而非「启用 / 停用」，
 *    抽屉里那两个按钮（status()）语义更准且带回读校验；表单再摆一个 switch 就是两套入口。
 *  - ends.create 是空的：平台用户没有创建端点（只能 C 端注册），本页不渲染「+ 新建」，
 *    openCreate() 不可达 ⇒ 该值不会被提交。
 */
const USER_FIELDS: Field[] = [
  {
    name: 'nickname',
    label: 'user.nickname',
    type: 'text',
    full: true,
    placeholder: 'user.nickname_hint',
  },
];

@Component({
  selector: 'app-users',
  imports: [StateBlock, Table, Pager, Tabs, Drawer, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'user.title' | t }}</h1>
      <span class="sub">{{ 'user.subtitle' | t }}</span>
      <div class="spacer"></div>
      <input
        class="input"
        [placeholder]="'user.search_hint' | t"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
      <!-- 只为「用户列表」导出：实名审核那一页是另一个 ID 空间的数据集，在这里导出会导错东西 -->
      @if (tab() === 'list') {
        <button class="btn" [disabled]="exporting()" (click)="exportUsers()">
          {{ (exporting() ? 'app.exporting' : 'user.export_excel') | t }}
        </button>
      }
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      <div class="card">
        <div class="card-body">
          <ui-table
            [rows]="rows()"
            [clickable]="true"
            [actions]="actions()"
            (pick)="open($event)"
            (act)="run($event.row, $event.key)"
          />
        </div>
      </div>
    </ui-state>

    @if (rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <ui-drawer
      [open]="detail() !== null"
      [title]="(tab() === 'identity' ? 'identity.title' : 'user.detail') | t"
      (close)="closeDetail()"
    >
      @if (detail(); as d) {
        <!-- 钱包：只有平台用户有钱包（实名记录是另一个 ID 空间，没有 user_id 可查） -->
        @if (tab() === 'list') {
          <section class="wallet">
            <div class="wallet-head">{{ 'wallet.title' | t }}</div>
            @if (wallet(); as w) {
              <div class="wallet-cards">
                @for (s of WALLET_STATS; track s.key) {
                  <div class="wcard">
                    <span class="wl">{{ s.label | t }}</span>
                    <strong class="wv mono">{{ dash(w[s.key]) }}</strong>
                  </div>
                }
              </div>
            } @else {
              <!-- 后端在用户没有钱包行时整个 wallet 键都不回（PlatformUserController::detail 的
                   if ($user->wallet)）⇒「没有钱包」与「钱包里都是 0」必须分得开，
                   不能摆四个「—」让人以为是加载失败。⚠ 模板字面量里不能出现反引号 -->
              <p class="wallet-missing">{{ 'wallet.missing' | t }}</p>
            }
          </section>

          <section class="wallet">
            <div class="wallet-head">{{ 'wallet.transactions' | t }}</div>
            <ui-state [loading]="txLoading()" [error]="txError()" [empty]="!txs().length">
              <div class="table-wrap tx-wrap">
                <table class="data tx-table">
                  <thead>
                    <tr>
                      @for (c of TX_COLS; track c.key) {
                        <th>{{ c.label | t }}</th>
                      }
                    </tr>
                  </thead>
                  <tbody>
                    @for (r of txs(); track r['id']) {
                      <tr>
                        <td>{{ txLabel(r['type']) }}</td>
                        <!-- 正负分色：只看字符串首字符（amountTone），金额全程不 parseFloat -->
                        <td
                          class="num"
                          [class.pos]="amountTone(r['amount']) === 'pos'"
                          [class.neg]="amountTone(r['amount']) === 'neg'"
                        >
                          {{ amountText(r['amount']) }}
                        </td>
                        <td class="num">{{ dash(r['balance_after']) }}</td>
                        <td>{{ dash(r['remark']) }}</td>
                        <td>{{ dash(r['created_at']) }}</td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            </ui-state>
            @if (txTotal() > txPageSize || txPage() > 1) {
              <ui-pager
                [page]="txPage()"
                [pages]="txPages"
                [total]="txTotal()"
                (jump)="goTx($event)"
              />
            }
          </section>
        }

        <dl class="kv">
          @for (p of info(); track p.label) {
            <dt>{{ p.label }}</dt>
            <dd>{{ p.value }}</dd>
          } @empty {
            <dt>{{ 'user.empty_tip' | t }}</dt>
            <dd>{{ 'user.empty_note' | t }}</dd>
          }
        </dl>
        <div class="row-actions">
          @if (tab() === 'identity') {
            <!-- 通过 / 驳回：PUT /admin/v1/identity/review（IdentityController::review，CAS 抢单） -->
            <button class="btn" (click)="review(d, 'approve')">{{ 'identity.approve' | t }}</button>
            <button class="btn danger" (click)="review(d, 'reject')">
              {{ 'identity.reject' | t }}
            </button>
          } @else {
            <!-- 账号注销在行内「删除」（同走 destroy()）；这里只放状态，语义是封禁/解封 -->
            <button class="btn" (click)="status(d, 'normal')">{{ 'user.unban' | t }}</button>
            <button class="btn danger" (click)="status(d, 'banned')">{{ 'user.ban' | t }}</button>
          }
        </div>
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
export class Users extends CrudPage {
  protected readonly tabs = [
    { key: 'list', label: 'user.tab_list' },
    { key: 'identity', label: 'identity.title' },
  ];
  protected readonly tab = signal('list');
  protected readonly detail = signal<Row | null>(null);
  /** 导出中（按钮禁用 + 文案切换）。导出走 Api.download，不经过 rows/loading，不打断列表 */
  protected readonly exporting = signal(false);

  /** 详情键值：字段名走 `kvLabel`（`col.<字段名>` 词条）—— 抽屉里摆 `last_login_ip` 这种
   *  裸列名，旁边标题却是「用户详情」，运营读不懂 */
  protected readonly info = computed(() => kvOf(this.detail(), kvLabel));

  // ---------- 钱包（只读；不许长出任何改余额的控件） ----------

  /** 模板作用域只认类成员，模块级 import 不可见 */
  protected readonly WALLET_STATS = WALLET_STATS;
  protected readonly TX_COLS = TX_COLS;
  protected readonly txLabel = txLabel;
  protected readonly amountTone = amountTone;
  protected readonly amountText = amountText;
  protected readonly dash = dash;

  /**
   * 钱包行 = 详情回包里的 `data.wallet`；**没有钱包行时整个键都不回**
   * （`if ($user->wallet)`）⇒ 回 null 而不是空对象，界面才能把「没有钱包」与
   * 「钱包里都是 0」分开说。
   */
  protected readonly wallet = computed<Row | null>(() => {
    const w = (this.detail() ?? {})['wallet'];
    return w && typeof w === 'object' && !Array.isArray(w) ? (w as Row) : null;
  });

  protected readonly txs = signal<Row[]>([]);
  protected readonly txLoading = signal(false);
  protected readonly txError = signal('');
  protected readonly txPage = signal(1);
  protected readonly txTotal = signal(0);
  /** 每页 20 与后端默认值同值（契约里的 per_page 默认），页数按它算才对得上 */
  protected readonly txPageSize = 20;

  protected get txPages(): number {
    return Math.max(1, Math.ceil(this.txTotal() / this.txPageSize));
  }

  /**
   * 流水：`GET /admin/v1/platform/user/{hashid}/transactions?page=&per_page=`。
   *
   * 走 `api.list`（本树列表的统一取数口）：它把 `items`/`total`/`page` 三种包装键都认下来，
   * 并按契约把 `per_page` 别名一起发出去（后端读的就是它）。**取不到就显示错误**，
   * 不静默留空 —— 流水空着与「加载失败」在界面上是同一种样子，那是最容易骗过自己的地方。
   */
  protected async loadTx(): Promise<void> {
    const id = idOf(this.detail() ?? {});
    if (!id) return;
    this.txLoading.set(true);
    this.txError.set('');
    try {
      const res = await this.api.list<Row>(U + 'platform/user/' + id + '/transactions', {
        page: this.txPage(),
        page_size: this.txPageSize,
      });
      this.txs.set(res.list ?? []);
      this.txTotal.set(res.total ?? 0);
    } catch (e) {
      this.txs.set([]);
      this.txTotal.set(0);
      this.txError.set(errText(e));
    } finally {
      this.txLoading.set(false);
    }
  }

  /** 翻页：页码夹在 [1, 末页]，同一页不重复取数（与基类的 go() 同口径） */
  protected goTx(p: number): void {
    const next = Math.min(Math.max(1, p), this.txPages);
    if (next === this.txPage()) return;
    this.txPage.set(next);
    void this.loadTx();
  }

  /** 关抽屉：连同流水一起清掉。留着上一笔的流水、下次开别人时先闪一遍别人的记录，是实打实的错 */
  protected closeDetail(): void {
    this.detail.set(null);
    this.txs.set([]);
    this.txTotal.set(0);
    this.txPage.set(1);
    this.txError.set('');
  }

  /**
   * 导出用户 Excel —— POST /export/users（ExportController::exportUsers，回 .xlsx 附件）。
   *
   * 不带条件：该端点只认 `status`，**不认列表页的搜索词**，一次最多 10000 条。
   * 本页没有状态筛选，硬塞一个屏幕上不存在的 status 只会导出看不见的数据 —— 所以按钮
   * 写「导出用户」而不是「导出当前筛选」，导的就是全量。
   */
  protected async exportUsers(): Promise<void> {
    this.exporting.set(true);
    this.error.set('');
    try {
      await this.api.download('POST', U + 'export/users');
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.exporting.set(false);
    }
  }

  /** 只有平台用户标签页可写；实名审核是动作型（通过/驳回），动作在那条记录的抽屉里 */
  protected override crud(): Crud | null {
    if (this.tab() !== 'list') return null;
    return {
      noun: 'user.noun',
      fields: USER_FIELDS,
      label: (row) => this.who(row),
      ends: {
        create: '',
        update: (id) => U + 'platform/user/' + id,
        remove: (id) => U + 'platform/user/' + id,
      },
    };
  }

  /**
   * 确认文案里的对象标识：昵称 / 用户名，都没有才退回 hashid。
   * 用户列表把 username 放在行顶层；实名记录的列表行嵌在 user.username 里
   * （IdentityController::list 的 `$data['user'] = ['id','username']`）—— 两种形状都要认，
   * 否则驳回确认文案只剩一个 hashid，等于没告诉人驳回的是谁。
   */
  protected who(row: Row): string {
    const u = (row['user'] ?? {}) as Row;
    return (
      String(row['nickname'] ?? '') ||
      String(row['username'] ?? '') ||
      String(u['username'] ?? '') ||
      idOf(row)
    );
  }

  /**
   * 行内动作分流：基类的「删除」落到本页 destroy()，编辑走基类。
   *
   * 为什么不直接用基类的 delete 实现：本页这两个动作带回读校验（请求成功 ≠ 生效），且被
   * users.spec.ts 逐字钉住了端点串，而基类默认实现是「请求发出去了就算成功」。
   * 基类只借入口、实现在本页 —— 不是并排两套。
   * （状态不在基类入口里：crud() 没开 statused，基类的 0/1 翻转会把「封禁/解封」压成「启用/停用」。）
   */
  protected override async run(row: Row, key: string): Promise<void> {
    if (key === 'delete') return this.destroy(row);
    return super.run(row, key);
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.closeDetail();
    void this.load();
  }

  protected override fetch(): Promise<Page<Row>> {
    const url = this.tab() === 'identity' ? U + 'identity/list' : U + 'platform/user/list';
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }

  /**
   * 详情：先展示列表行，再拉 platform/user/{hashid} 覆盖。
   * 只有「用户列表」能拉：实名记录的 id 是另一个 ID 空间，拿它去请求 /platform/user/{hashid}
   * 会解出**另一个用户**（或 404）—— 端点和 ID 必须成对，这里不猜。
   */
  protected async open(row: Row): Promise<void> {
    this.detail.set(row);
    if (this.tab() !== 'list') return;
    const id = idOf(row);
    if (!id) return;
    // 开新记录 = 上一次的流水作废：页码归 1，先清空再取（不清的话新记录会先闪一遍上一个人的流水）
    this.txs.set([]);
    this.txTotal.set(0);
    this.txPage.set(1);
    this.txError.set('');
    try {
      const d = await this.api.get<unknown>(U + 'platform/user/' + id);
      if (d && typeof d === 'object' && !Array.isArray(d)) this.detail.set(d as Row);
    } catch {
      // 详情取不到就展示列表行本身，不阻塞抽屉
    }
    // 钱包与流水都依赖同一个 hashid（列表行也有 id）⇒ 详情失败也照取，不跟着一起哑掉
    void this.loadTx();
  }

  /**
   * 实名审核（通过 / 驳回）—— PUT /admin/v1/identity/review，入参 {id, action, note}，
   * action 只认 approve|reject（IdentityController::review 的 validator），
   * note 是 game_user_identity.review_note VARCHAR(500)，**驳回通知正文会把 note 原样带给用户**。
   *
   * 驳回是不可逆的（后端 CAS：status 必须还是 pending，翻过就 422），所以先二次确认；
   * prompt 取消（null）与空备注（''）必须分开 —— 混为一谈会让「手滑点了取消」变成一次真驳回。
   */
  protected async review(row: Row, action: 'approve' | 'reject'): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    let note = '';
    if (action === 'reject') {
      if (!confirm(t('identity.reject_confirm_target', { name: this.who(row) }))) return;
      const input = prompt(t('identity.note_hint'));
      if (input === null) return;
      note = input.trim();
    }
    this.error.set('');
    try {
      await this.api.request('PUT', U + 'identity/review', { id, action, note });
      this.closeDetail();
      await this.load();
    } catch (e) {
      this.error.set(errText(e));
    }
  }

  /**
   * 平台用户封禁/解封 —— 走 PUT /platform/user/{hashid}（PlatformUserController::update，
   * 写的是平台 user 表，status 收 int 0/1）。
   *
   * 原先走 POST /user/batch/status 是错的：那是【管理员】端点（认 AdminUser，Apidoc 标注
   * "批量启用或禁用管理员用户"），我们却把平台用户的 hashid 递进去。且它当时把 status 前置转型
   * —— `(int)'banned'` 与 `(int)'normal'` 都等于 0，封禁与解封一起落成 0 = 禁用；
   * 真问题不是"落成相反的状态"，而是【不该静默解释非法输入】
   * （admin/docs/API.md:754 明写非 0/1 的 status 应当 422），加上 count 报的是请求条数
   * 而非受影响行数，于是界面显示成功、实际可能一行都没改。
   * 后端两点已修：UserController::batchStatus 改先原样比白名单再转 int（口径见
   * UserBatchAffectedRowsTest）。
   */
  protected async status(row: Row, value: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    this.error.set('');
    const want = value === 'banned' ? 0 : 1;
    try {
      await this.api.request('PUT', U + 'platform/user/' + id, { status: want });
    } catch (e) {
      this.error.set(errText(e));
      return;
    }
    this.closeDetail();
    await this.load();
    // 成功以回读到的真实状态为准，不以"请求发出去了"为准
    const after = this.rows().find((r) => idOf(r) === id);
    if (!after) {
      this.error.set(t('user.status_gone'));
    } else if (num(after['status']) !== want) {
      this.error.set(t('user.status_stale', { status: num(after['status']) }));
    }
  }

  /**
   * 平台用户注销 —— 走 DELETE /platform/user/{hashid}（PlatformUserController::destroy）。
   *
   * 原先走 POST /user/batch/destroy 是错的：那是【管理员】端点（UserController::batchDestroy
   * 删的是 AdminUser 表），我们却把平台用户的 hashid 递进去 —— 与上面 status 同一类错，
   * 而且后果更重：一旦两族 hashid 解出同一个数值，这个「注销平台用户」会去删一个管理员账号。
   *
   * 后端拒绝有非零余额的用户（安全要求），拒绝原因在信封 message 里 —— 原样透出，
   * 不吞成「操作失败」，否则运营只会看到「失败」而不知道该先清余额。
   */
  protected async destroy(row: Row): Promise<void> {
    const id = idOf(row);
    // 删前必须能看清是谁：确认文案带昵称/用户名，不能只有一个「该账号」
    if (!id || !confirm(t('user.destroy_confirm', { name: this.who(row) }))) return;
    this.error.set('');
    try {
      await this.api.request('DELETE', U + 'platform/user/' + id);
    } catch (e) {
      this.error.set(errText(e));
      return;
    }
    this.closeDetail();
    await this.load();
    // 成功以回读到的真实列表为准，不以「请求发出去了」为准
    if (this.rows().some((r) => idOf(r) === id)) {
      this.error.set(t('user.destroy_stale'));
    }
  }
}
