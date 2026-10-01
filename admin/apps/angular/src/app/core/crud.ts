/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { computed, inject, signal } from '@angular/core';
import { Api, Row } from './api.service';
import { buildPdfTable, downloadExcel, downloadPdf } from './export';
import { ListBase } from './list-base';
import { idOf, json } from './render';
import type { PNode } from './tree';
import { colsOf, errText, num } from './util';
import type { Act } from '../components/table';

/** image：文本框 + 上传按钮（值仍是字符串 URL；存量手输的 URL/图标名照旧可编辑） */
export type FieldType =
  | 'text'
  /** password：与 text 同一个提交形状，只是输入框不可回显（改密码那类字段） */
  | 'password'
  | 'textarea'
  | 'number'
  | 'select'
  | 'switch'
  | 'multi'
  | 'image'
  /** tree：树多选（权限树），值同样是一维 hashid 数组，提交形状与 multi 完全一致 */
  | 'tree'
  /**
   * file：选本地文件（Excel 导入）。**草稿里是文件名（字符串）**，`File` 本体由 ui-form 在提交
   * 那一刻从原生 file 输入读出来、放进同一个键 —— 于是这个键的值是 `File` 而不是字符串，
   * **只能走 multipart**：`payload()` 直接跳过它（见 `norm`），塞进 JSON 会变成 `[object File]`。
   */
  | 'file';

export interface Opt {
  value: string;
  label: string;
}

/** 表单字段描述：写操作底座的唯一「每模块一份」输入 */
export interface Field {
  /** 提交给后端的键名，必须与控制器 validator 的键一致 */
  name: string;
  label: string;
  type: FieldType;
  /** 只在表单上打星；校验真值一律在后端（不前端拦截，免得两处规则漂移） */
  required?: boolean;
  options?: Opt[];
  placeholder?: string;
  /** 独占一整行（长文本/URL） */
  full?: boolean;
  /** 提交时留空 = 跳过该字段（密钥类：列表不回显，空串会清空或被后端守卫吞掉） */
  keepIfEmpty?: boolean;
  /** 仅新建可填（update 的落库白名单里没有它，摆出来只会误导） */
  createOnly?: boolean;
  /**
   * 字段下方的说明（值域/格式提示）。**只提示不校验**：真值一律在服务端。
   * ⚠ 规则文案挂这里，别挂 `placeholder`：审计实测 `.form-grid` 单列净宽 ~207px，
   * 「3-50 位；创建后不可改（update 不读该字段）」这类 27 字的规则在框里被截成
   * 「3-50 位；创建后不可改（update ：」—— 输入框里既没有 `title` 也没有 `aria-label`，
   * 用户看不全；而且一打字 placeholder 就消失。短到放得下的（「最长 50」）留着无妨。
   */
  hint?: string;
  /** 输入上限（落成原生 `maxlength` + 框下 `n/上限` 字数指示）。真值仍在服务端 */
  maxlength?: number;
  /** tree 字段的节点树（运行期注入，与 options 一样随信号刷新） */
  tree?: PNode[];
  /**
   * file 字段的 `<input accept>`（如 `.xlsx,.xls`）。**只是给系统选文件对话框的过滤提示**，
   * 不是校验：用户仍能强行选中别的类型，真值域由服务端判（`ImportController` 认扩展名白名单）。
   * 这里再实现一遍就是第二个真值源，迟早和后端漂开。
   */
  accept?: string;
}

/**
 * 写端点。**缺省即没有该能力**（与 React 树 RowBrowser 同一口径，照搬它省得两树漂移）：
 * update 缺省 ⇒ 行内不出「编辑」，remove 缺省 ⇒ 不出「删除」，create 缺省 ⇒ 页头不出「+ 新建」。
 * 提现订单没有 PUT/DELETE、阶梯限额没有 DELETE —— 硬凑一个端点出来就是两个点了必 404 的按钮。
 */
export interface Ends {
  create?: string;
  update?: (id: string) => string;
  remove?: (id: string) => string;
  /**
   * 有则状态切换走 POST；没有则用 update 传 status。
   * - 字符串：固定端点，id/status 进请求体（/achievement/toggle 那类）
   * - 函数：按行 id 拼路径（/risk/rule/{hashid}/toggle 那类，请求体为空、服务端自己翻转）
   */
  toggle?: string | ((id: string) => string);
}

export interface Crud {
  /** 「新建<名词>」里的模块名：i18n 键（查不到原样显示），由 crud.create/edit 的 {name} 占位符吃进去 */
  noun: string;
  fields: Field[];
  ends: Ends;
  /** 删除二次确认里的对象标识（标题/名称）；只有声明了 ends.remove 才用得上 */
  label?: (row: Row) => string;
  /** 实体带 status 才出「启用/停用」 */
  statused?: boolean;
  /** 删除需二次输入登录密码（后端 confirmPassword 守卫，如系统配置） */
  deletePassword?: boolean;
  /**
   * 编辑走**全量**语义：update 把每个字段都从请求体读一遍（缺失的用后端自己的默认值补齐，
   * 必填的缺失直接 422）。风控规则就是这样（RiskRuleController::fill 对 update 与 create
   * 同一套必填校验，且 status 缺省落 1 ⇒ 局部更新会把停用的规则静默改回启用）。
   * 开了它就不与旧值比对，表单上的字段**全部**发出（switch 也发，等同新建分支）。
   */
  fullEdit?: boolean;
  /** 额外行内动作（如排行榜「刷新缓存」），key 落到页面的 extra() */
  extra?: Act[];
}

/**
 * 字段值规整：switch → 0/1；number 空 → undefined（不提交）；multi → 字符串数组；其余 → 字符串。
 * 对象/数组（JSON 列读回来的 config、benefits）走 json() 与表单预填**同一个序列化器**，
 * 于是「没动过的 JSON 字段」能与旧值判等 ⇒ 不提交，不会把格式化过的 JSON 每编辑一次就回写一遍。
 *
 * multi 是**数组字段**（不是「一格文本里塞 JSON」）：后端收的是数组（permission_ids → sync()），
 * 走 json() 会变成字符串 '["a"]'，服务端拿到的就不是数组了。两侧都排序 ⇒ 判等与勾选顺序无关。
 * tree 与 multi 同一口径：树只是选择方式，提交的仍是一维 hashid 数组。
 */
function norm(f: Field, v: unknown): string | number | string[] | undefined {
  // file 字段不属于 JSON 请求体：`File` 只能靠 multipart 上送（见 components/import-panel.ts）。
  // 这里**跳过**而不是 `String()` —— 后者会发出 `[object File]`，后端会把它当成一个文件名。
  if (f.type === 'file') return undefined;
  if (f.type === 'multi' || f.type === 'tree') {
    return Array.isArray(v) ? v.map(String).sort() : [];
  }
  if (f.type === 'switch') return num(v) ? 1 : 0;
  if (f.type === 'number') return String(v ?? '').trim() === '' ? undefined : Number(v);
  if (v === null || v === undefined) return '';
  return typeof v === 'object' ? json(v) : String(v);
}

/** 值判等：multi 的数组按值比（norm 已把两侧排序），其余照旧 === */
function same(a: unknown, b: unknown): boolean {
  if (Array.isArray(a) && Array.isArray(b)) {
    return a.length === b.length && a.every((x, i) => x === b[i]);
  }
  return a === b;
}

/**
 * 表单值 → 请求体。old 传行数据即「局部更新」：与旧值相同的字段不提交 ——
 * 后端 update 的规则是 sometimes（缺省字段不报错），但传了就会落库，
 * 比如未回显的 api_key 传个空串就把正在用的密钥清空了。新建时 old 传 {}。
 */
export function payload(fields: Field[], old: Row, next: Row): Row {
  // 新建时调用方传的就是 {}（CrudPage::submit；编辑态的行至少带 id）
  const create = !Object.keys(old).length;
  const out: Row = {};
  for (const f of fields) {
    const v = norm(f, next[f.name]);
    if (v === undefined) continue;
    if (v === '' && f.keepIfEmpty) continue;
    // switch 没有「未设置」态：不勾选就是 0，而 create 时旧值 undefined 也归一化成 0，
    // 判等会把「关掉的开关」当成「没改」丢掉 ⇒ 后端会用它自己的默认值
    // （AnnouncementController.php:75 / AchievementController.php:56 默认 1）
    // —— 新建公告/成就时关掉状态，反而建成「已启用」。⇒ create 时 switch 恒发。
    if (same(v, norm(f, old[f.name])) && !(create && f.type === 'switch')) continue;
    out[f.name] = v;
  }
  return out;
}

/**
 * 列表页「增删改 + 状态变更」底座：字段描述 + 端点描述 → 行内动作 / 表单弹框 / 提交。
 * 页面只需实现 crud()（当前标签页的模块描述；无写操作返回 null）与 fetch()。
 */
export abstract class CrudPage extends ListBase<Row> {
  protected readonly api = inject(Api);

  /** 当前标签页的写操作描述；无写操作的标签页返回 null（行尾就不出「操作」列） */
  protected abstract crud(): Crud | null;

  readonly formOpen = signal(false);
  readonly formTitle = signal('');
  /** 编辑预填行；null = 新建 */
  readonly formValue = signal<Row | null>(null);
  /** 表单框内的错误（服务端 message 原样透出）；与列表级 error 分开 */
  readonly formError = signal('');
  readonly saving = signal(false);

  /** 当前标签页是否可写（模板用：无写操作的标签页不显示「+ 新建」） */
  readonly writable = computed(() => !!this.crud()?.ends.create);

  protected readonly actions = computed<Act[]>(() => {
    const c = this.crud();
    if (!c) return [];
    const acts: Act[] = [];
    if (c.ends.update) acts.push({ key: 'edit', label: 'app.edit' });
    if (c.ends.remove) acts.push({ key: 'delete', label: 'app.delete', danger: true });
    // 启用/停用两条路：专用 POST toggle 端点，或走 update 的局部 PUT {status}；两条都没有就不出按钮
    if (c.statused && (c.ends.toggle || c.ends.update))
      acts.push({ key: 'toggle', label: 'crud.toggle' });
    acts.push(...(c.extra ?? []));
    return acts;
  });

  /** 编辑时剔掉 createOnly（update 落不了库的字段不该出现在编辑框里） */
  protected readonly formFields = computed<Field[]>(() => {
    const fields = this.crud()?.fields ?? [];
    return this.formValue() ? fields.filter((f) => !f.createOnly) : fields;
  });

  protected openCreate(): void {
    const c = this.crud();
    if (!c) return;
    this.formValue.set(null);
    this.formTitle.set(this.i18n.t('crud.create', { name: this.i18n.t(c.noun) }));
    this.formError.set('');
    this.formOpen.set(true);
  }

  protected closeForm(): void {
    this.formOpen.set(false);
    this.formValue.set(null);
    this.formError.set('');
  }

  protected async submit(values: Row): Promise<void> {
    const c = this.crud();
    if (!c) return;
    const editing = this.formValue();
    const id = editing ? idOf(editing) : '';
    if (editing && !id) return;
    const url = editing ? c.ends.update?.(id) : c.ends.create;
    if (!url) return;
    // fullEdit 的模块（风控规则）update 也是全量语义：传 {} 即走 payload 的「新建」分支 ——
    // 不与旧值比对、switch 恒发，正是那边要的（缺 status 后端会把规则改回启用）
    const body = payload(this.formFields(), c.fullEdit ? {} : (editing ?? {}), values);
    // 局部更新下「一个字段都没改」是合法的空操作，不必打扰后端
    if (editing && !Object.keys(body).length) {
      this.closeForm();
      return;
    }
    this.saving.set(true);
    this.formError.set('');
    try {
      await this.api.request(editing ? 'PUT' : 'POST', url, body);
      this.closeForm();
      await this.load();
    } catch (e) {
      // 422/其它：服务端 message 留在框里，框不关，用户改完可重试
      this.formError.set(errText(e));
    } finally {
      this.saving.set(false);
    }
  }

  /**
   * 行内动作：编辑（弹框预填）/ 删除（二次确认，可要求密码）/ 启用·停用（就地切换）/
   * crud().extra 声明的额外动作（落到 extra()）。失败一律进列表级 error，不吞。
   */
  protected async run(row: Row, key: string): Promise<void> {
    const c = this.crud();
    const id = idOf(row);
    if (!c || !id) return;
    if (key === 'edit') {
      if (!c.ends.update) return;
      this.formValue.set(row);
      this.formTitle.set(this.i18n.t('crud.edit', { name: this.i18n.t(c.noun) }));
      this.formError.set('');
      this.formOpen.set(true);
      return;
    }
    this.error.set('');
    try {
      if (key === 'delete') {
        const remove = c.ends.remove;
        if (!remove) return;
        // 与 users.ts:178 同款原生 confirm（不再搭第二套弹框），文案带对象标识
        const name = c.label?.(row) ?? idOf(row);
        if (!confirm(this.i18n.t('crud.delete_confirm', { name }))) return;
        // 后端 confirmPassword 守卫（ConfigController::destroy）要求密码随请求带上。
        // ⚠ 密码框点「取消」返回 null ⇒ 必须**中止**，一个请求都不发：写成 `?? ''` 会把「取消」
        // 变成一次空密码请求 —— 界面刚说了取消、紧接着又弹一条 422，操作者无从判断删没删
        // （实测载荷 `DELETE /admin/v1/user/A2 {"password":""}`）。与 react 树的 deleteWithPassword
        // （pages/modules.ts）同一语义。
        // 走 body 而不是 query —— OperationLog 的敏感字段过滤（admin/app/middleware/OperationLog.php:63）
        // 按字段名抹掉 password，塞在 URL 里反而会原样落进操作日志。
        let body: Row | undefined;
        if (c.deletePassword) {
          const password = prompt(this.i18n.t('crud.delete_password_prompt'));
          if (password === null) return;
          body = { password };
        }
        await this.api.request('DELETE', remove(id), body);
      } else if (key === 'toggle') {
        const status = num(row['status']) ? 0 : 1;
        const toggle = c.ends.toggle;
        // 函数形态的端点是「服务端自己翻转」：路径带 hashid，不发请求体（发了它也只看路径）
        if (typeof toggle === 'function') await this.api.post(toggle(id));
        else if (toggle) await this.api.post(toggle, { id, status });
        else if (c.ends.update) await this.api.request('PUT', c.ends.update(id), { status });
        else return;
      } else if (c.extra?.some((a) => a.key === key)) {
        await this.extra(row, key);
      } else {
        return;
      }
      await this.load();
    } catch (e) {
      this.error.set(errText(e));
    }
  }

  /** crud().extra 声明的动作落点；页面按需重写（默认无动作，等价于旧行为） */
  protected async extra(_row: Row, _key: string): Promise<void> {}

  // ---------- 导出下载（两个端点都**不经过 rows/loading**，导出中不打断表格） ----------

  /** 导出中（_pdf / _xlsx 共用：一个页面上两个按钮同时按不动，比各发一个信号诚实） */
  readonly busyExport = signal(false);

  /**
   * 导出**本页** PDF：列与行都由调用方给 —— 传屏幕上那张表的 heads/rows，
   * 别传 this.rows()：有的页面表格渲染的是派生行（管理员页要把 role_ids 翻成角色名）。
   * 失败进列表级 error（与 users.ts 的 /export/users 同一条路），成功不弹提示（浏览器自己的下载条就是回执）。
   */
  protected async exportPdf(
    titleKey: string,
    heads: Record<string, string>,
    rows: Row[],
  ): Promise<void> {
    this.busyExport.set(true);
    this.error.set('');
    try {
      const body = buildPdfTable(this.i18n.t(titleKey), colsOf(heads, rows), rows);
      await downloadPdf(this.api, body);
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.busyExport.set(false);
    }
  }

  /**
   * 导出**整张服务端表**为 Excel（表名要在 ExportController 的白名单里，见 core/export.ts）。
   * 它不认屏幕上的筛选、也不是当前这一页 ⇒ 先二次确认并把范围说清（与 react 树 logs.tsx 同款）。
   * 名字取当前标签页的 crud().noun —— 与「新建<名词>」用的是同一个词，不再多造一套名词。
   */
  protected async exportXlsx(table: string): Promise<void> {
    const name = this.i18n.t(this.crud()?.noun ?? table);
    if (!confirm(this.i18n.t('export.table_confirm', { name }))) return;
    this.busyExport.set(true);
    this.error.set('');
    try {
      await downloadExcel(this.api, table);
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.busyExport.set(false);
    }
  }
}
