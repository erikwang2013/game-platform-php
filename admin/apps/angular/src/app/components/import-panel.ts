/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, input, output, signal } from '@angular/core';
import { Api, Row } from '../core/api.service';
import { Field } from '../core/crud';
import { T, t } from '../core/i18n/i18n';
import { errText, num } from '../core/util';
import { FormModal } from './form-modal';
import { Table } from './table';

/** 端点回来的读数：`ImportController::users` 的 data（`errors` 是逐行的「第几行、为什么没进」） */
type Report = { total: number; success: number; failed: number; errors: Row[] };

/**
 * 逐行失败表照**服务端给的行号**显示：那是 Excel 里的真实行号（含表头行的偏移），
 * 前端拿不到也不该重算 —— 重算一次就多一个和后端对不上的口径。
 */
const ERROR_HEADS: Record<string, string> = { row: 'col.row', reason: 'col.reason' };

/**
 * 必填（后端 `$request->file('file')` 拿不到就回「请选择文件」，这里先拦一次省一个来回）；
 * `accept` 只是选文件对话框的过滤提示，真值域由后端判（见 `Field.accept`）。
 */
const FIELDS: Field[] = [
  {
    name: 'file',
    label: 'form.file',
    type: 'file',
    required: true,
    accept: '.xlsx,.xls',
    hint: 'import.hint',
  },
];

/**
 * Excel 批量导入：工具条按钮 → 选文件弹框 → 结果报表。
 *
 * 为什么不做成 CrudPage 里的一个内联分支：导入的**产出是一份读数**（成功几行、哪几行没进），
 * 不是一次 CRUD。读数得留在屏幕上给人抄，而 ui-form 提交成功即关框（表单的常规语义，不宜为它破例），
 * 所以结果另开一个框。整块行为收在这一个文件里，页面只留一个挂载点（`<ui-import />`）。
 * 失败路径不用另写：ui-form 自己把服务端 message 显示在框内且**不关框**（改文件重试）。
 */
@Component({
  selector: 'ui-import',
  imports: [FormModal, Table, T],
  template: `
    <button class="btn" type="button" (click)="open.set(true)">{{ title() | t }}</button>

    <ui-form
      [open]="open()"
      [title]="title()"
      [fields]="fields"
      [error]="error()"
      [saving]="saving()"
      (save)="submit($event)"
      (close)="open.set(false)"
    />

    @if (report(); as r) {
      <div class="backdrop" (click)="report.set(null)"></div>
      <div class="modal" role="dialog" aria-modal="true" [attr.aria-label]="'import.result' | t">
        <header>
          <b>{{ 'import.result' | t }}</b>
          <span class="spacer"></span>
          <button class="btn" type="button" (click)="report.set(null)">{{ 'app.close' | t }}</button>
        </header>
        <div class="modal-body">
          <p>{{ 'import.summary' | t: { total: r.total, success: r.success, failed: r.failed } }}</p>
          <!-- 这个端点不建角色关联，而鉴权是遍历 roles 聚合的 ⇒ 新账号能登录、每个页面都 403。
               不说就等于让运营导完 200 个账号发下去、再挨个收到「登进去什么都没有」。
               一条都没导进去时不提这茬（没账号，警告是噪声）。{roles} 取编辑表单里那个字段的标签，
               与表单单源；两棵树的恢复路径都指向那个入口。 -->
          @if (r.success > 0) {
            <p class="hint">{{ 'import.no_roles' | t: { roles: roles() } }}</p>
          }
          <!-- 全成功就不摆一张空表：没有失败行时那张表只剩一句「暂无数据」，是噪声 -->
          @if (r.errors.length) {
            <ui-table [rows]="r.errors" [heads]="errorHeads" />
          }
        </div>
      </div>
    }
  `,
})
export class ImportPanel {
  private readonly api = inject(Api);

  /** 导入端点（POST，multipart 单文件段 `file`） */
  readonly path = input.required<string>();
  /** 按钮与弹框标题共用的词条键 */
  readonly title = input.required<string>();
  /** 导入成功后的回读钩子（列表要刷出刚导进来那些账号） */
  readonly done = output<void>();

  protected readonly fields = FIELDS;
  protected readonly errorHeads = ERROR_HEADS;
  protected readonly open = signal(false);
  protected readonly error = signal('');
  protected readonly saving = signal(false);
  protected readonly report = signal<Report | null>(null);

  /** `{roles}` 占位符吃的就是编辑表单里那个字段的标签 —— 与表单单源，改标签这里跟着变 */
  protected roles(): string {
    return t('admin.role_ids');
  }

  protected async submit(body: Row): Promise<void> {
    // 文件只能靠 multipart 上去。**Content-Type 一个字都不写**：HttpClient 认得当体是 FormData，
    // 会留空这个头、把 boundary 交给浏览器补 —— 手写一份就等于把 boundary 丢了。
    const form = new FormData();
    for (const [k, v] of Object.entries(body)) {
      // File（Blob 的子类，`FormData` 里取出来的一定是它）走原样上送，**绝不 String()**
      if (v instanceof File) {
        // 没选文件时原生表单里是一个**空文件**（name 为空）：当段发出去只是让服务端收到一个
        // 没名字的文件。不发，交给服务端报「请选择文件」（与没有这个键同一条路径）。
        if (v.name) form.append(k, v);
      } else if (v !== '' && v !== null && v !== undefined) {
        form.append(k, String(v));
      }
    }
    this.saving.set(true);
    this.error.set('');
    try {
      const { data } = await this.api.envelope<Partial<Report>>('POST', this.path(), form);
      const d = data ?? {};
      this.open.set(false);
      // 端点可能不回全 errors/total（后端改口径时不至于把报表框炸成白屏）：缺项当 0/空表
      this.report.set({
        total: num(d.total),
        success: num(d.success),
        failed: num(d.failed),
        errors: Array.isArray(d.errors) ? d.errors : [],
      });
      this.done.emit();
    } catch (e) {
      // 服务端原因（422「请上传 Excel 文件」/ 列缺失 / 逐行失败）原样留在框里，框不关
      this.error.set(errText(e));
    } finally {
      this.saving.set(false);
    }
  }
}
