/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, input, output } from '@angular/core';
import { Row } from '../core/api.service';
import { Field } from '../core/crud';
import { json } from '../core/render';
import { num } from '../core/util';

/**
 * 通用表单弹框：字段描述驱动，新建/编辑共用一个（value 非空即预填）。
 * 提交由页面负责（页面才认识端点），失败时页面把服务端 message 从 error 灌回来，框不关。
 *
 * 输入框是**非受控**的（不绑草稿信号）：靠 @if (open()) 每次打开重建 DOM 保证预填干净，
 * 提交时读原生 FormData —— 省掉一套「草稿 vs 预填」的同步状态。
 */
@Component({
  selector: 'ui-form',
  template: `
    @if (open()) {
      <div class="backdrop" (click)="close.emit()"></div>
      <div class="modal" role="dialog" aria-modal="true" [attr.aria-label]="title()">
        <header>
          <b>{{ title() }}</b>
          <span class="spacer"></span>
          <button class="btn" type="button" (click)="close.emit()">关闭</button>
        </header>
        <form class="modal-body" (submit)="fire($event)">
          @if (error()) {
            <div class="alert">{{ error() }}</div>
          }
          <div class="form-grid">
            @for (f of fields(); track f.name) {
              <div [class.full]="f.full || f.type === 'textarea'">
                <label>
                  {{ f.label }}
                  @if (f.required) {
                    <i class="req">*</i>
                  }
                </label>
                @switch (f.type) {
                  @case ('textarea') {
                    <textarea
                      class="input"
                      [attr.name]="f.name"
                      [placeholder]="f.placeholder || ''"
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
                        <option [value]="v" [selected]="true">{{ v }}（当前值）</option>
                      }
                      <option value="" [selected]="text(f.name) === ''">
                        {{ f.keepIfEmpty ? '不修改' : '请选择' }}
                      </option>
                      @for (o of f.options || []; track o.value) {
                        <option [value]="o.value" [selected]="o.value === text(f.name)">
                          {{ o.label }}
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
                        <option [value]="o.value" [selected]="o.on">{{ o.label }}</option>
                      }
                    </select>
                  }
                  @case ('switch') {
                    <label class="switch">
                      <input type="checkbox" [attr.name]="f.name" [checked]="on(f.name)" />
                      <span>{{ on(f.name) ? '启用' : '停用' }}</span>
                    </label>
                  }
                  @case ('number') {
                    <input
                      class="input"
                      type="number"
                      [attr.name]="f.name"
                      [placeholder]="f.placeholder || ''"
                      [value]="text(f.name)"
                    />
                  }
                  @default {
                    <input
                      class="input"
                      type="text"
                      [attr.name]="f.name"
                      [placeholder]="f.placeholder || ''"
                      [value]="text(f.name)"
                    />
                  }
                }
                @if (f.hint) {
                  <small class="hint">{{ f.hint }}</small>
                }
              </div>
            }
          </div>
          <button class="btn btn-primary btn-block" type="submit" [disabled]="saving()">
            {{ saving() ? '提交中…' : '提交' }}
          </button>
        </form>
      </div>
    }
  `,
})
export class FormModal {
  readonly open = input(false);
  readonly title = input('新建');
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
        .map((v) => ({ value: v, label: `${v}（当前值）`, on: true })),
      ...opts.map((o) => ({ value: o.value, label: o.label, on: cur.includes(o.value) })),
    ];
  }

  /** 提交：读原生表单值，switch 缺省即 0（复选框未勾选不进 FormData）、multi 收全部勾选项 */
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
          : f.type === 'multi'
            ? fd.getAll(f.name).map(String)
            : String(fd.get(f.name) ?? '');
    }
    this.save.emit(out);
  }
}
