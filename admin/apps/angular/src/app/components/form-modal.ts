/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, input, output, signal } from '@angular/core';
import { Row } from '../core/api.service';
import { Field } from '../core/crud';
import { T, t } from '../core/i18n/i18n';
import { json } from '../core/render';
import { ImageUpload } from '../core/upload';
import { errText, num } from '../core/util';
import { TreeSelect } from './tree-select';

/**
 * 通用表单弹框：字段描述驱动，新建/编辑共用一个（value 非空即预填）。
 * 提交由页面负责（页面才认识端点），失败时页面把服务端 message 从 error 灌回来，框不关。
 *
 * 输入框是**非受控**的（不绑草稿信号）：靠 @if (open()) 每次打开重建 DOM 保证预填干净，
 * 提交时读原生 FormData —— 省掉一套「草稿 vs 预填」的同步状态。
 */
@Component({
  selector: 'ui-form',
  imports: [TreeSelect, T],
  template: `
    @if (open()) {
      <div class="backdrop" (click)="close.emit()"></div>
      <div class="modal" role="dialog" aria-modal="true" [attr.aria-label]="title() | t">
        <header>
          <b>{{ title() | t }}</b>
          <span class="spacer"></span>
          <button class="btn" type="button" (click)="close.emit()">{{ 'app.close' | t }}</button>
        </header>
        <form class="modal-body" (submit)="fire($event)">
          @if (error()) {
            <div class="alert">{{ error() }}</div>
          }
          <div class="form-grid">
            @for (f of fields(); track f.name) {
              <div
                [class.full]="
                  f.full || f.type === 'textarea' || f.type === 'image' || f.type === 'file'
                "
              >
                <label>
                  {{ f.label | t }}
                  @if (f.required) {
                    <i class="req">*</i>
                  }
                </label>
                @switch (f.type) {
                  @case ('textarea') {
                    <textarea
                      class="input"
                      [attr.name]="f.name"
                      [placeholder]="(f.placeholder || '') | t"
                      [value]="text(f.name)"
                    ></textarea>
                  }
                  @case ('select') {
                    <!-- 不能用 select[value]：它的绑定早于 @for 生成的 option，预选会被吞掉、
                         静默落成第一个选项（编辑一次就把 type 改掉）。逐项 [selected] 才可靠。 -->
                    <select class="input" [attr.name]="f.name">
                      <!-- 存量行里可能有选项表里没有的枚举值（如公告 type=payment）：
                           置顶补一条并保持原样，免得显示成「请选择」被手滑改掉 -->
                      @if (offList(f); as v) {
                        <option [value]="v" [selected]="true">
                          {{ 'form.current_value' | t: { value: v } }}
                        </option>
                      }
                      <option value="" [selected]="text(f.name) === ''">
                        {{ (f.keepIfEmpty ? 'form.keep_if_empty' : 'form.select_placeholder') | t }}
                      </option>
                      @for (o of f.options || []; track o.value) {
                        <option [value]="o.value" [selected]="o.value === text(f.name)">
                          {{ o.label | t }}
                        </option>
                      }
                    </select>
                  }
                  @case ('multi') {
                    <!-- 多选：原生 select[multiple]，逐项 [selected]（同单选 —— [value] 绑定会被
                         @for 生成的 option 吞掉）。未勾选的项不进 FormData ⇒ fire() 用 getAll 收数组，
                         所以这不是「一格文本里塞 JSON」而是真正的数组字段。 -->
                    <select class="input" [attr.name]="f.name" multiple size="8">
                      @for (o of multi(f); track o.value) {
                        <option [value]="o.value" [selected]="o.on">{{ o.label | t }}</option>
                      }
                    </select>
                  }
                  @case ('tree') {
                    <!-- 树多选（权限树）：值与 multi 同为**一维 hashid 数组**，只是选择方式带层级。
                         提交照样读原生表单 —— 组件里的 checkbox 带 name/value，fire() 与 multi 同一分支。
                         选项由页面在打开前注入（f.tree），这里不做取数。 -->
                    <ui-tree-select [name]="f.name" [nodes]="f.tree ?? []" [value]="picked(f.name)" />
                  }
                  @case ('switch') {
                    <label class="switch">
                      <input type="checkbox" [attr.name]="f.name" [checked]="on(f.name)" />
                      <span>{{ (on(f.name) ? 'app.enabled' : 'app.disabled') | t }}</span>
                    </label>
                  }
                  @case ('image') {
                    <!-- 文本框保留：存量手输的 URL / 图标名（分类图标那列就是图标名）要能继续编辑。
                         上传只做「把绝对 URL 写回这个框」—— 提交仍读原生表单值，payload() 的
                         「编辑态只发改动」比对口径不变（值就是字符串）。 -->
                    <div class="img-row">
                      <input
                        #box
                        class="input"
                        type="text"
                        [attr.name]="f.name"
                        [placeholder]="(f.placeholder || '') | t"
                        [value]="text(f.name)"
                        (input)="preview(f.name, $any($event.target).value)"
                      />
                      <button
                        type="button"
                        class="btn"
                        [disabled]="!!uploading()"
                        (click)="picker.click()"
                      >
                        {{ (uploading() === f.name ? 'form.uploading' : 'app.upload') | t }}
                      </button>
                      <input
                        #picker
                        type="file"
                        hidden
                        [accept]="accept"
                        (change)="pick(f, $event, box)"
                      />
                    </div>
                    @if (errOf(f.name); as msg) {
                      <small class="hint err">{{ msg }}</small>
                    }
                    @if (src(f.name); as url) {
                      <img class="thumb" [src]="url" alt="" />
                    }
                  }
                  @case ('file') {
                    <!-- 本地文件（Excel 导入）：文件名摆在**只读**文本框里 —— 手输一个名字是拿不到
                         文件的，提交时服务端只会收到一段文本。选择器隐藏、由按钮代点（同 image 字段）。
                         ⚠ 这个 file 输入**带 name**（值是 File 本体，fire() 从原生表单读它）；
                         image 那个不带（它上传完只把 URL 回写进文本框），别照抄成一样的。 -->
                    <div class="img-row">
                      <input
                        #box
                        class="input"
                        type="text"
                        readonly
                        [placeholder]="'form.no_file_chosen' | t"
                      />
                      <button type="button" class="btn" (click)="picker.click()">
                        {{ 'form.choose_file' | t }}
                      </button>
                      <input
                        #picker
                        type="file"
                        hidden
                        [attr.name]="f.name"
                        [accept]="f.accept"
                        (change)="choose($event, box)"
                      />
                    </div>
                  }
                  @case ('number') {
                    <input
                      class="input"
                      type="number"
                      [attr.name]="f.name"
                      [placeholder]="(f.placeholder || '') | t"
                      [value]="text(f.name)"
                    />
                  }
                  @case ('password') {
                    <!-- 密码框不给 [value] 预填：预填一个密码进 DOM 等于把它写在了页面上 -->
                    <input
                      class="input"
                      type="password"
                      autocomplete="new-password"
                      [attr.name]="f.name"
                      [placeholder]="(f.placeholder || '') | t"
                    />
                  }
                  @default {
                    <input
                      class="input"
                      type="text"
                      [attr.name]="f.name"
                      [placeholder]="(f.placeholder || '') | t"
                      [value]="text(f.name)"
                    />
                  }
                }
                @if (f.hint) {
                  <small class="hint">{{ f.hint | t }}</small>
                }
              </div>
            }
          </div>
          <button class="btn btn-primary btn-block" type="submit" [disabled]="saving()">
            {{ (saving() ? 'form.submitting' : 'form.submit') | t }}
          </button>
        </form>
      </div>
    }
  `,
})
export class FormModal {
  readonly open = input(false);
  /** 标题：i18n 键或字面量（页面按当前模块拼好；查不到原样显示） */
  readonly title = input('app.create');
  readonly fields = input.required<Field[]>();
  /** 编辑预填行；null/undefined = 新建 */
  readonly value = input<Row | null>(null);
  readonly error = input('');
  readonly saving = input(false);

  /**
   * ⚠ 输出名不能叫 `submit`：它与 <form> 的原生 submit 事件同名，而原生事件会冒泡到本组件宿主
   * <ui-form> 上 —— 页面里 `(submit)="submit($event)"` 会**被调用两次**（先 output 的正确值、
   * 后原生 SubmitEvent），后者覆盖前者，发出去的请求体永远是被当成 Row 解析的事件对象
   * （实测载荷 `{"name":"","type":"submit","status":0}`）。改名与原生事件解耦，别改回去。
   */
  readonly save = output<Row>();
  readonly close = output<void>();

  private readonly uploads = inject(ImageUpload);

  /** file 选择框的值域提示；真白名单在后端 groups.image.resource_extensions（jpg/jpeg/png/gif/webp） */
  protected readonly accept = 'image/jpeg,image/png,image/gif,image/webp';

  /** 正在上传的字段名（空 = 没有在传）。同一时刻只允许一个：其余上传按钮一并禁用 */
  protected readonly uploading = signal('');
  /** 上传失败的字段与文案（服务端 error 原文），就近挂在该字段下方 */
  private readonly upErr = signal<{ field: string; text: string } | null>(null);
  /** 上传结果/刚手输值的预览覆盖层 —— 文本框是非受控的，信号只为缩略图服务 */
  private readonly urls = signal<Record<string, string>>({});

  /** image 专用：缩略图取值（上传结果或手输值优先，否则预填值） */
  protected src(name: string): string {
    const v = this.urls()[name] ?? this.text(name);
    // 只认 URL 形态：分类 icon 列存量是图标名，塞进 <img src> 只会打一串 404
    return /^(https?:)?\/\/|^\//.test(v) ? v : '';
  }

  /** image 专用：手输也即时更新缩略图（非受控文本框没有别的可绑处） */
  protected preview(name: string, value: string): void {
    this.urls.update((u) => ({ ...u, [name]: value }));
  }

  /** image 专用：该字段行下的上传错误文案 */
  protected errOf(name: string): string {
    const e = this.upErr();
    return e?.field === name ? e.text : '';
  }

  /**
   * 选图 → 上传 → 把绝对 URL 写回**原生输入框**（非受控：提交读的就是它）+ 刷新缩略图。
   * 失败原样显示服务端文案，框不关：用户可重选，也可手输 URL 兜底。
   */
  protected async pick(f: Field, ev: Event, box: HTMLInputElement): Promise<void> {
    const input = ev.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = ''; // 先清空，否则连选同一个文件不会再触发 change
    if (!file) return;
    this.uploading.set(f.name);
    this.upErr.set(null);
    try {
      const url = await this.uploads.image(file);
      box.value = url;
      this.preview(f.name, url);
    } catch (e) {
      this.upErr.set({ field: f.name, text: errText(e) });
    } finally {
      this.uploading.set('');
    }
  }

  /**
   * file 字段专用：只把**文件名**写回只读框；`File` 本体**留在 input 里**（`fire()` 从原生表单读它）。
   *
   * ⚠ 刻意**不**清 `input.value`（上面 `pick()` 必须清）：清空会把 `files` 一起清掉，
   * 提交时就没有文件可发了。代价是「再选一次同一个文件」不触发 change —— 无害：
   * 框里显示的与待提交的仍然是那一个文件。
   */
  protected choose(ev: Event, box: HTMLInputElement): void {
    const file = (ev.target as HTMLInputElement).files?.[0];
    if (file) box.value = file.name;
  }

  /** 预填文本：JSON 列（config/benefits）读回来是数组/对象，要与 payload() 用同一个
   *  序列化器（render.json）才判得等 —— 否则每编辑一次都会把 JSON 原样回写一遍。 */
  protected text(name: string): string {
    const v = this.value()?.[name];
    if (v === null || v === undefined) return '';
    return typeof v === 'object' ? json(v) : String(v);
  }

  protected on(name: string): boolean {
    return num(this.value()?.[name]) === 1;
  }

  /** select 专用：行里的值不在选项表里时返回它（用于置顶补一条），否则空串 */
  protected offList(f: Field): string {
    const v = this.text(f.name);
    return v && !(f.options ?? []).some((o) => o.value === v) ? v : '';
  }

  /** multi 专用：当前已选的 hashid（数组字段；单值/空都归一成数组） */
  protected picked(name: string): string[] {
    const v = this.value()?.[name];
    if (Array.isArray(v)) return v.map(String);
    return v === null || v === undefined || v === '' ? [] : [String(v)];
  }

  /**
   * multi 专用：选项 = 声明表 + 「当前值里不在声明表中的」置顶补项。
   * 与单选 offList() 同款理由：行的值不在选项表里（权限树取失败、权限被删、后端换了口径）时，
   * 若按「没勾选」渲染，一保存就把这些值当成「取消勾选」发出去 —— 授权会被静默清空。
   */
  protected multi(f: Field): { value: string; label: string; on: boolean }[] {
    const cur = this.picked(f.name);
    const opts = f.options ?? [];
    return [
      ...cur
        .filter((v) => !opts.some((o) => o.value === v))
        .map((v) => ({ value: v, label: t('form.current_value', { value: v }), on: true })),
      ...opts.map((o) => ({ value: o.value, label: o.label, on: cur.includes(o.value) })),
    ];
  }

  /** 提交：读原生表单值，switch 缺省即 0（复选框未勾选不进 FormData）、multi/tree 收全部勾选项 */
  protected fire(ev: Event): void {
    ev.preventDefault();
    const fd = new FormData(ev.target as HTMLFormElement);
    const out: Row = {};
    for (const f of this.fields()) {
      out[f.name] =
        f.type === 'switch'
          ? fd.has(f.name)
            ? 1
            : 0
          : f.type === 'multi' || f.type === 'tree'
            ? fd.getAll(f.name).map(String)
            : f.type === 'file'
              // file：**原样交出 `File` 本体**，绝不能 String() —— `[object File]` 会被当成文件名
              ? (fd.get(f.name) ?? '')
              : String(fd.get(f.name) ?? '');
    }
    this.save.emit(out);
  }
}
