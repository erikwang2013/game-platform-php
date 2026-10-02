/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { FormField, FieldOption } from './FormDialog';
import { PermissionTreeView } from './PermissionTree';
import { t, t1 } from '../i18n/Translations';

/**
 * 表单里的**一个字段**（标签 + 说明 + 控件），从 FormDialog.ets 搬来（那份越过 500 行上限）。
 *
 * 单向流入 / 回写回调：值与勾选态由父组件（FormDialog）算好传进来，改动一律经 onSetValue /
 * onTogglePicked / onUpload 回写，本组件自己**不持有表单状态** —— 与 PermissionTreeView
 * （checked 进、onChange 出）同款，故 FormDialog 的重渲染会按 @Prop 把新值推下来。
 *
 * 上传动作留在父组件：上传失败要写进父组件的 serverError（框内那行红字），且上传中要禁用
 * 提交按钮 —— 上传状态属于整个表单，不属于单个字段。
 *
 * `field` 是**普通成员不是 @Prop**：ForEach 的 key 就是 field.name，同一个 key 下这个对象不换，
 * 而父页面会原地改 field.tree（见 FormField.tree 注释），普通成员拿的是同一个对象引用，
 * 反而比 @Prop 的深拷贝更跟得上。
 */
@Component
export struct FormFieldControl {
  /** 语言变更要重渲染本组件（标签/说明/文案都由 t() 出） */
  @StorageLink('app_locale') localeCode: string = 'en';
  /** 本轮字段描述（缺省值只为满足 ArkTS 的成员必须初始化） */
  field: FormField = { name: '', label: '', type: 'text' };
  /** 当前值（父组件的表单值，单向流入） */
  @Prop value: string = '';
  /** multi / tree 的勾选值（父组件按逗号串拆好） */
  @Prop pickedIds: string[] = [];
  /** 提交中：控件全部禁用 */
  @Prop submitting: boolean = false;
  /** 正在上传的字段名（空串 = 没有上传在跑） */
  @Prop uploading: string = '';
  /** 写一个字段的值 */
  onSetValue?: (name: string, value: string) => void;
  /** 勾选/取消 multi 的一项 */
  onTogglePicked?: (field: FormField, value: string, checked: boolean) => void;
  /** 选图上传（父组件持上传状态与错误行） */
  onUpload?: (field: FormField) => void;

  /** multi 候选项（ArkTS 的 ForEach 参数里不写 ?? 兜底，统一走这个方法） */
  private options(): FieldOption[] {
    return this.field.options ?? [];
  }

  private isPicked(value: string): boolean {
    return this.pickedIds.indexOf(value) >= 0;
  }

  private write(value: string): void {
    if (this.onSetValue) {
      this.onSetValue(this.field.name, value);
    }
  }

  private toggle(value: string, checked: boolean): void {
    if (this.onTogglePicked) {
      this.onTogglePicked(this.field, value, checked);
    }
  }

  private upload(): void {
    if (this.onUpload) {
      this.onUpload(this.field);
    }
  }

  private pickOption(index: number): void {
    const source = this.options();
    if (index >= 0 && index < source.length) {
      this.write(source[index].value);
    }
  }

  /** ArkUI 的 SelectOption 只吃显示文本，提交值另存在 FieldOption.value */
  private selectOptions(): SelectOption[] {
    const options: SelectOption[] = [];
    const source = this.options();
    for (let i = 0; i < source.length; i++) {
      options.push({ value: t(source[i].label) });
    }
    return options;
  }

  /** 当前值对应的显示文本；值不在候选里（历史脏数据）就原样回显，别显示空白 */
  private optionLabel(): string {
    const source = this.options();
    for (let i = 0; i < source.length; i++) {
      if (source[i].value === this.value) {
        return t(source[i].label);
      }
    }
    return this.value;
  }

  private optionIndex(): number {
    const source = this.options();
    for (let i = 0; i < source.length; i++) {
      if (source[i].value === this.value) {
        return i;
      }
    }
    // -1 = 不选中（Select 的默认值），值不在值域里时不硬凑一个候选
    return -1;
  }

  build() {
    Column() {
      Row() {
        Text(t(this.field.label))
          .fontSize(14)
          .fontColor('#333333')

        if (this.field.required) {
          Text('*')
            .fontSize(14)
            .fontColor('#FF4D4F')
            .margin({ left: 2 })
        }

        // 多选没勾选态能一眼看全时，给个计数（列表可能几十项，滚下去看不出来选了几个）
        if (this.field.type === 'multi' || this.field.type === 'tree') {
          Text(t1('common.selected_count', 'count', `${this.pickedIds.length}`))
            .fontSize(12)
            .fontColor('#999999')
            .margin({ left: 6 })
        }
      }
      .width('100%')
      .margin({ bottom: 6 })

      // 说明放在标签与控件之间：控件一旦有内容，placeholder 就没了，而值域说明要一直在场
      if (this.field.hint !== undefined && this.field.hint.length > 0) {
        Text(t(this.field.hint))
          .fontSize(11)
          .fontColor('#999999')
          .width('100%')
          .margin({ bottom: 6 })
      }

      if (this.field.type === 'switch') {
        Toggle({ type: ToggleType.Switch, isOn: this.value === '1' })
          .enabled(!this.submitting)
          .onChange((isOn: boolean) => {
            this.write(isOn ? '1' : '0');
          })
      } else if (this.field.type === 'select') {
        Select(this.selectOptions())
          .value(this.optionLabel())
          .selected(this.optionIndex())
          .width('100%')
          .enabled(!this.submitting)
          .onSelect((index: number) => {
            this.pickOption(index);
          })
      } else if (this.field.type === 'multi') {
        // 勾选态靠 setValue 重建的 values 对象驱动（select 同款）。
        // 点击只挂在 Checkbox 与文案上，行本身不挂 onClick —— 挂了两处会各切一次、净效果为零。
        Column() {
          ForEach(this.options(), (option: FieldOption) => {
            Row() {
              Checkbox()
                .select(this.isPicked(option.value))
                .width(18)
                .height(18)
                .enabled(!this.submitting)
                .onChange((checked: boolean) => {
                  this.toggle(option.value, checked);
                })

              Text(t(option.label))
                .fontSize(13)
                .fontColor('#333333')
                .layoutWeight(1)
                .margin({ left: 6 })
                .onClick(() => {
                  this.toggle(option.value, !this.isPicked(option.value));
                })
            }
            .width('100%')
            .margin({ bottom: 2 })
          }, (option: FieldOption) => `${this.field.name}:${option.value}`)
        }
        .width('100%')
      } else if (this.field.type === 'tree') {
        // 勾选态单向流入（@Prop = 表单值），变化经 onChange 回写成逗号串。
        // 展开态在树组件内（纯展示），表单重渲染不影响提交值。
        PermissionTreeView({
          nodes: this.field.tree ?? [],
          checked: this.pickedIds,
          disabled: this.submitting,
          onChange: (ids: string[]) => {
            this.write(ids.join(','));
          }
        })
      } else if (this.field.type === 'image') {
        // 上传不改控件形态：文本框仍是唯一的真值入口（可直接贴外链），上传只是往里填地址
        Column() {
          Row() {
            TextInput({ placeholder: this.field.placeholder !== undefined ? t(this.field.placeholder) : '', text: this.value })
              .height(40)
              .fontSize(14)
              .layoutWeight(1)
              .enabled(!this.submitting && this.uploading.length === 0)
              .onChange((value: string) => {
                this.write(value);
              })

            Button(this.uploading === this.field.name ? t('common.uploading') : t('common.upload'))
              .fontSize(13)
              .fontColor('#FFFFFF')
              .backgroundColor(this.uploading === this.field.name ? '#99BBFF' : '#1677FF')
              .borderRadius(4)
              .height(40)
              .width(64)
              .margin({ left: 8 })
              .enabled(!this.submitting && this.uploading.length === 0)
              .onClick(() => {
                this.upload();
              })
          }
          .width('100%')

          // 预览：display 路由公开，Image 直接吃这个绝对地址（`<img>` 那种场景带不了 Authorization 头）
          if (this.value.length > 0) {
            Image(this.value)
              .width('100%')
              .height(120)
              .objectFit(ImageFit.Contain)
              .backgroundColor('#FAFAFA')
              .borderRadius(4)
              .margin({ top: 6 })
          }
        }
        .width('100%')
      } else if (this.field.type === 'textarea') {
        TextArea({ placeholder: this.field.placeholder !== undefined ? t(this.field.placeholder) : '', text: this.value })
          .width('100%')
          .height(80)
          .fontSize(14)
          .enabled(!this.submitting)
          .onChange((value: string) => {
            this.write(value);
          })
      } else {
        TextInput({ placeholder: this.field.placeholder !== undefined ? t(this.field.placeholder) : '', text: this.value })
          .width('100%')
          .height(40)
          .fontSize(14)
          // number 走数字键盘，password 走掩码，其余 Normal
          .type(this.field.type === 'number' ? InputType.Number
            : (this.field.type === 'password' ? InputType.Password : InputType.Normal))
          .enabled(!this.submitting)
          .onChange((value: string) => {
            this.write(value);
          })
      }
    }
    .width('100%')
    .alignItems(HorizontalAlign.Start)
    .margin({ bottom: 12 })
  }
}
