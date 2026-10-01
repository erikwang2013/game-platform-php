/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import promptAction from '@ohos.promptAction';
import { FormValues, FormPayload, PermissionNode } from '../model/DataModels';
import { pickImage } from './ImagePicker';
import { UploadService } from '../service/UploadService';
import { PermissionTreeView } from './PermissionTree';
import { t, t1 } from '../i18n/Translations';

/**
 * 字段类型 —— 与控件一一对应：text/number/password→TextInput（password 掩码显示），
 * textarea→TextArea，select→Select，switch→Toggle，multi→多选（Checkbox 列表，提交数组），
 * image→文本框 + 上传按钮 + 预览（值仍是**绝对地址字符串**，故 changed()/payload() 与 text 同路），
 * tree→权限树多选（父子联动 + 三态勾选，值与 multi 同法：逗号串进、hashid 数组出）
 */
export type FieldType = 'text' | 'number' | 'password' | 'textarea' | 'select' | 'switch' | 'multi' | 'image' | 'tree';

/** select / multi 选项：label 给人看，value 是提交值（后端枚举原文或 hashid，不接受显示名） */
export interface FieldOption {
  label: string;
  value: string;
}

/**
 * 字段描述 —— 每个模块一份，新建/编辑共用（对外只此一处描述，不给每个模块写一个表单）。
 * 值域（in:... / max / required）以控制器 validator 与数据库列为准，不要照抄前端旧代码。
 */
export interface FormField {
  /** 提交字段名，必须与后端 request->input() 的键一致 */
  name: string;
  label: string;
  type: FieldType;
  required?: boolean;
  placeholder?: string;
  /** type=select / multi 时的候选项 */
  options?: FieldOption[];
  /**
   * type=tree 时的**整棵树**（候选不摊平：父子联动要层级，摊平就没了）。
   * 与 options 同一手法：字段对象先建壳，prepareForm 拿到树之后原地改这个数组。
   */
  tree?: PermissionNode[];
  /**
   * 字段下方的小字说明（可多行，用 \n）。给「值域会变长」的字段用：
   * 风控规则的 config 键白名单随 type 变、还有闭区间与布尔/金额/比率三类值域，
   * 塞进 placeholder 会随输入消失且在 TextArea 里被裁掉 —— 说明要一直在场。
   */
  hint?: string;
  /** 编辑态不提交该字段（如 game.slug：后端 update 的 only() 白名单里没有它，传了也会被丢掉） */
  disabled?: boolean;
}

/**
 * 通用二次确认 —— 危险动作（删除/驳回/关闭）共用。文案必须带对象标识（调用方给的 message）。
 * 返回 Promise：确认 = true，取消或弹框本身失败 = false。
 * 形态取自 FormDialog 的提交回调：需要「先问一句再发」的动作（驳回、关闭）可以直接 await 它。
 */
export function confirmAction(title: string, message: string, okText: string, okColor: string): Promise<boolean> {
  return promptAction.showDialog({
    title: title,
    message: message,
    buttons: [
      { text: t('common.cancel'), color: '#999999' },
      { text: okText, color: okColor }
    ]
  }).then((result: promptAction.ShowDialogSuccessResponse): boolean => result.index === 1)
    .catch((): boolean => false);
}

/** 删除前二次确认；what 是对象标识（名称/标题），文案必须带上它（不要笼统「确认删除？」） */
export function confirmDelete(what: string, onConfirm: () => void): void {
  confirmAction(t('common.confirm_delete_title'), t1('common.confirm_delete_message', 'what', what),
    t('common.delete'), '#FF4D4F').then((ok: boolean) => {
    if (ok) {
      onConfirm();
    }
  });
}

/**
 * 通用表单弹框 —— 新建/编辑共用，字段描述驱动。
 *
 * 父页面用 formOpen 控制挂载（挂载时才读 initial，故每次开框都是新快照）：
 *
 *   if (this.formOpen) {
 *     FormDialog({
 *       title: this.formEdit ? '编辑游戏' : '新建游戏',
 *       fields: this.formEdit ? EDIT_FIELDS : CREATE_FIELDS,
 *       initial: this.formInitial,
 *       onSubmit: (body: FormPayload) => this.submitForm(body),
 *       onDone: () => { this.formOpen = false; this.loadData(true); },
 *       onCancel: () => { this.formOpen = false; }
 *     })
 *   }
 *
 * onSubmit 抛错 = 服务端拒绝（422/404…）：错误原文显示在框内且**不关框**，用户改完可重试；
 * 成功由父页面在 onDone 里关框 + 刷新列表（ArkUI 的弹框不能像 Flutter 的 Navigator.pop 那样自关）。
 *
 * multi / tree 字段的取值编码：表单里始终存**逗号连接的字符串**（FormValues 只认 string），
 * 提交时才 split 成 string[]（后端 permission_ids 收数组）。这样 changed()/setValue()/seed()
 * 这些按字符串写的公共逻辑一行都不用改；hashid 的字母表不含逗号，故分隔符不会歧义。
 * tree 与 multi 的差别只在控件（树形勾选 vs 平铺勾选），编解码完全同路。
 *
 * image 字段：值就是**绝对地址字符串**（与 text 同一种值），上传成功后 setValue 回填，
 * 于是 changed()/payload()/partial 比较一行都不用改。上传失败原文显示在框内且不清空已填地址 ——
 * 失败时最不该做的就是让用户重贴一遍链接。
 *
 * select 字段的历史值兜底（与 flutter 兄弟树 widgets/crud.dart 同款）：
 * 行里已有的值可能不在候选值域内（值域收紧过/历史脏数据），此时**不提交该字段**——
 * 局部更新下不发即保持库中原值；发出去会被 update 的 `in:` 规则 422 掉整次更新，
 * 用户连改个标题都改不动。显示侧照旧原样回显（optionLabel），用户改选值域内的项后才提交。
 */
@Component
export struct FormDialog {
  /** 语言变更要重渲染本组件：build 里的 t() 读静态 currentCode，ArkUI 不观测它。 */
  @StorageLink('app_locale') localeCode: string = 'en';
  /** 弹框标题（如「新建游戏」/「编辑游戏」） */
  title: string = '';
  /** 字段描述 */
  fields: FormField[] = [];
  /** 编辑态预填值；新建态给缺省值。键必须是字段描述里出现过的 */
  initial: FormValues = {};
  /** 编辑态置 true = 局部更新（与 initial 相同的字段不发）；新建态必须 false，否则缺省值会被漏发 */
  partial: boolean = false;
  /** 提交回调：成功即返回，失败抛错（错误原文进框内） */
  onSubmit?: (body: FormPayload) => Promise<void>;
  /** 提交成功：父页面关框 + 刷新列表 */
  onDone?: () => void;
  onCancel?: () => void;

  /** 各字段当前值：一律 string（HTTP 表单语义），见 seed() */
  @State values: FormValues = {};
  /** 本地必填校验提示 */
  @State localError: string = '';
  /** 服务端失败原文（上传失败也走这里，同样是服务端原样给的话） */
  @State serverError: string = '';
  @State submitting: boolean = false;
  /** 正在上传的字段名（空串 = 没有上传在跑）；按字段记而不是布尔，多图字段各管各的按钮 */
  @State uploading: string = '';

  aboutToAppear(): void {
    this.seed();
  }

  /**
   * 先把字段描述里每个键都建出来（switch 缺省 '0'），再用 initial 覆盖。
   * 键先建齐，setValue 才能一次复制搞定重渲染，不必赌「后加的键会不会被观测」。
   */
  private seed(): void {
    const seeded: FormValues = {};
    for (let i = 0; i < this.fields.length; i++) {
      const field = this.fields[i];
      seeded[field.name] = field.type === 'switch' ? '0' : '';
    }
    const initKeys = Object.keys(this.initial);
    for (let i = 0; i < initKeys.length; i++) {
      const key = initKeys[i];
      seeded[key] = this.initial[key];
    }
    this.values = seeded;
  }

  private current(field: FormField): string {
    return this.values[field.name] ?? '';
  }

  /**
   * 写一个字段：整对象重建而不是 this.values[name] = v。
   * 新对象身份必然触发重渲染（Select/Toggle 的回显靠它），代价是每次按键复制 ≤12 个键。
   */
  private setValue(name: string, value: string): void {
    const next: FormValues = {};
    const keys = Object.keys(this.values);
    for (let i = 0; i < keys.length; i++) {
      next[keys[i]] = this.values[keys[i]];
    }
    next[name] = value;
    this.values = next;
    this.localError = '';
  }

  /** 表单 → 请求体：只放字段描述里有的字段，按类型换算（switch→0/1，number→整型） */
  private payload(): FormPayload {
    const body: FormPayload = {};
    for (let i = 0; i < this.fields.length; i++) {
      const field = this.fields[i];
      if (field.disabled) {
        continue;
      }
      if (field.type === 'select' && !this.inOptionDomain(field, this.current(field))) {
        continue;
      }
      if (this.partial && !this.changed(field)) {
        continue;
      }
      const raw = this.current(field);
      if (field.type === 'switch') {
        body[field.name] = raw === '1' ? 1 : 0;
      } else if (field.type === 'multi' || field.type === 'tree') {
        // 全不选 = 空数组（「取消全部授权」是合法意图，必须发出去而不是跳过）
        body[field.name] = this.picked(field);
      } else if (field.type === 'number') {
        body[field.name] = this.toNumber(raw);
      } else {
        body[field.name] = raw;
      }
    }
    return body;
  }

  /** 与初值是否有差异（仅编辑态问）；initial 没这个键时当「改过」，宁可多发不漏发 */
  private changed(field: FormField): boolean {
    return (this.initial[field.name] ?? '') !== this.current(field);
  }

  /** select 当前值是否落在候选值域内；不在（含空串）⇒ payload 跳过该字段，见类头注释 */
  private inOptionDomain(field: FormField, value: string): boolean {
    const source = field.options ?? [];
    for (let i = 0; i < source.length; i++) {
      if (source[i].value === value) {
        return true;
      }
    }
    return false;
  }

  /** 数字字段：空串或非数字回退 0（InputType.Number 下非数字几乎不可达，兜底防 NaN 进 JSON） */
  private toNumber(raw: string): number {
    const trimmed = raw.trim();
    if (trimmed === '') {
      return 0;
    }
    const parsed = Number(trimmed);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  /** multi 候选项（ArkTS 的 ForEach 参数里不写 ?? 兜底，统一走这个方法） */
  private optionList(field: FormField): FieldOption[] {
    return field.options ?? [];
  }

  /** multi / tree 当前勾选值（逗号分隔字符串 → 数组） */
  private picked(field: FormField): string[] {
    const raw = this.current(field);
    return raw === '' ? [] : raw.split(',');
  }

  private isPicked(field: FormField, value: string): boolean {
    return this.picked(field).indexOf(value) >= 0;
  }

  /** 勾选/取消一项：重建数组后回写逗号串（保持「值一定是我写进去的那份」） */
  private togglePicked(field: FormField, value: string, checked: boolean): void {
    const current = this.picked(field);
    const next: string[] = [];
    for (let i = 0; i < current.length; i++) {
      if (current[i] !== value) {
        next.push(current[i]);
      }
    }
    if (checked) {
      next.push(value);
    }
    this.setValue(field.name, next.join(','));
  }

  /**
   * 选图 → 上传 → 回填地址。上传中该字段的输入与按钮一并禁用；
   * 任一步失败都把原文显示在框内（服务端 error 是给人看的话），**不动用户已填的地址**。
   * 用户取消选择不是错误，静默返回。
   */
  private async pickAndUpload(field: FormField): Promise<void> {
    if (this.submitting || this.uploading.length > 0) {
      return;
    }
    this.uploading = field.name;
    this.localError = '';
    this.serverError = '';
    try {
      const picked = await pickImage();
      if (picked === null) {
        return;
      }
      const url = await UploadService.uploadImage(picked.bytes, picked.fileName);
      this.setValue(field.name, url);
    } catch (e) {
      this.serverError = e instanceof Error && e.message.length > 0 ? e.message : `${e}`;
    } finally {
      this.uploading = '';
    }
  }

  /** 必填校验就地拦下：空表单不该换一条服务端 message 回来（switch 不受约束） */
  private missingRequired(): string {
    for (let i = 0; i < this.fields.length; i++) {
      const field = this.fields[i];
      if (field.required && field.type !== 'switch' && this.current(field).trim() === '') {
        // 字段名先经 t()：模块表存的是键时它查表，存的仍是中文时原样返回（键名即自身）
        return t1('common.required_field', 'name', t(field.label));
      }
    }
    return '';
  }

  private async submit(): Promise<void> {
    // 上传中的地址还是旧的：这时候提交等于把旧地址当"用户改过的新值"发出去，直接拦住
    if (this.submitting || this.uploading.length > 0) {
      return;
    }
    const missing = this.missingRequired();
    if (missing !== '') {
      this.localError = missing;
      return;
    }
    this.localError = '';
    this.serverError = '';
    this.submitting = true;
    try {
      if (this.onSubmit) {
        await this.onSubmit(this.payload());
      }
      // 先复位再回调：onDone 会卸载本组件，之后不该再写状态
      this.submitting = false;
      if (this.onDone) {
        this.onDone();
      }
    } catch (e) {
      this.submitting = false;
      this.serverError = e instanceof Error && e.message.length > 0 ? e.message : `${e}`;
    }
  }

  private cancel(): void {
    if (this.onCancel) {
      this.onCancel();
    }
  }

  /** ArkUI 的 SelectOption 只吃显示文本，提交值另存在 FieldOption.value */
  private selectOptions(field: FormField): SelectOption[] {
    const options: SelectOption[] = [];
    const source = field.options ?? [];
    for (let i = 0; i < source.length; i++) {
      options.push({ value: t(source[i].label) });
    }
    return options;
  }

  /** 当前值对应的显示文本；值不在候选里（历史脏数据）就原样回显，别显示空白 */
  private optionLabel(field: FormField): string {
    const source = field.options ?? [];
    const current = this.current(field);
    for (let i = 0; i < source.length; i++) {
      if (source[i].value === current) {
        return t(source[i].label);
      }
    }
    return current;
  }

  private optionIndex(field: FormField): number {
    const source = field.options ?? [];
    const current = this.current(field);
    for (let i = 0; i < source.length; i++) {
      if (source[i].value === current) {
        return i;
      }
    }
    // -1 = 不选中（Select 的默认值），值不在值域里时不硬凑一个候选
    return -1;
  }

  private pickOption(field: FormField, index: number): void {
    const source = field.options ?? [];
    if (index >= 0 && index < source.length) {
      this.setValue(field.name, source[index].value);
    }
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      // 遮罩与卡片是 Stack 兄弟节点：卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      Column()
        .width('100%')
        .height('100%')
        .backgroundColor('#00000066')
        .onClick(() => {
          this.cancel();
        })

      Column() {
        Text(this.title)
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .margin({ bottom: 12 })

        if (this.localError.length > 0 || this.serverError.length > 0) {
          Text(this.localError.length > 0 ? this.localError : this.serverError)
            .fontSize(13)
            .fontColor('#FF4D4F')
            .width('100%')
            .margin({ bottom: 8 })
        }

        Scroll() {
          Column() {
            ForEach(this.fields, (field: FormField) => {
              // 每轮一个根组件（Column）—— ForEach 每轮只能生成一个
              Column() {
                Row() {
                  Text(t(field.label))
                    .fontSize(14)
                    .fontColor('#333333')

                  if (field.required) {
                    Text('*')
                      .fontSize(14)
                      .fontColor('#FF4D4F')
                      .margin({ left: 2 })
                  }

                  // 多选没勾选态能一眼看全时，给个计数（列表可能几十项，滚下去看不出来选了几个）
                  if (field.type === 'multi' || field.type === 'tree') {
                    Text(t1('common.selected_count', 'count', `${this.picked(field).length}`))
                      .fontSize(12)
                      .fontColor('#999999')
                      .margin({ left: 6 })
                  }
                }
                .width('100%')
                .margin({ bottom: 6 })

                // 说明放在标签与控件之间：控件一旦有内容，placeholder 就没了，而值域说明要一直在场
                if (field.hint !== undefined && field.hint.length > 0) {
                  Text(t(field.hint))
                    .fontSize(11)
                    .fontColor('#999999')
                    .width('100%')
                    .margin({ bottom: 6 })
                }

                if (field.type === 'switch') {
                  Toggle({ type: ToggleType.Switch, isOn: this.current(field) === '1' })
                    .enabled(!this.submitting)
                    .onChange((isOn: boolean) => {
                      this.setValue(field.name, isOn ? '1' : '0');
                    })
                } else if (field.type === 'select') {
                  Select(this.selectOptions(field))
                    .value(this.optionLabel(field))
                    .selected(this.optionIndex(field))
                    .width('100%')
                    .enabled(!this.submitting)
                    .onSelect((index: number) => {
                      this.pickOption(field, index);
                    })
                } else if (field.type === 'multi') {
                  // 勾选态靠 setValue 重建的 values 对象驱动（select 同款）。
                  // 点击只挂在 Checkbox 与文案上，行本身不挂 onClick —— 挂了两处会各切一次、净效果为零。
                  Column() {
                    ForEach(this.optionList(field), (option: FieldOption) => {
                      Row() {
                        Checkbox()
                          .select(this.isPicked(field, option.value))
                          .width(18)
                          .height(18)
                          .enabled(!this.submitting)
                          .onChange((checked: boolean) => {
                            this.togglePicked(field, option.value, checked);
                          })

                        Text(t(option.label))
                          .fontSize(13)
                          .fontColor('#333333')
                          .layoutWeight(1)
                          .margin({ left: 6 })
                          .onClick(() => {
                            this.togglePicked(field, option.value, !this.isPicked(field, option.value));
                          })
                      }
                      .width('100%')
                      .margin({ bottom: 2 })
                    }, (option: FieldOption) => `${field.name}:${option.value}`)
                  }
                  .width('100%')
                } else if (field.type === 'tree') {
                  // 勾选态单向流入（@Prop = 表单值），变化经 onChange 回写成逗号串。
                  // 展开态在树组件内（纯展示），表单重渲染不影响提交值。
                  PermissionTreeView({
                    nodes: field.tree ?? [],
                    checked: this.picked(field),
                    disabled: this.submitting,
                    onChange: (ids: string[]) => {
                      this.setValue(field.name, ids.join(','));
                    }
                  })
                } else if (field.type === 'image') {
                  // 上传不改控件形态：文本框仍是唯一的真值入口（可直接贴外链），上传只是往里填地址
                  Column() {
                    Row() {
                      TextInput({ placeholder: field.placeholder !== undefined ? t(field.placeholder) : '', text: this.current(field) })
                        .height(40)
                        .fontSize(14)
                        .layoutWeight(1)
                        .enabled(!this.submitting && this.uploading.length === 0)
                        .onChange((value: string) => {
                          this.setValue(field.name, value);
                        })

                      Button(this.uploading === field.name ? t('common.uploading') : t('common.upload'))
                        .fontSize(13)
                        .fontColor('#FFFFFF')
                        .backgroundColor(this.uploading === field.name ? '#99BBFF' : '#1677FF')
                        .borderRadius(4)
                        .height(40)
                        .width(64)
                        .margin({ left: 8 })
                        .enabled(!this.submitting && this.uploading.length === 0)
                        .onClick(() => {
                          this.pickAndUpload(field);
                        })
                    }
                    .width('100%')

                    // 预览：display 路由公开，Image 直接吃这个绝对地址（`<img>` 那种场景带不了 Authorization 头）
                    if (this.current(field).length > 0) {
                      Image(this.current(field))
                        .width('100%')
                        .height(120)
                        .objectFit(ImageFit.Contain)
                        .backgroundColor('#FAFAFA')
                        .borderRadius(4)
                        .margin({ top: 6 })
                    }
                  }
                  .width('100%')
                } else if (field.type === 'textarea') {
                  TextArea({ placeholder: field.placeholder !== undefined ? t(field.placeholder) : '', text: this.current(field) })
                    .width('100%')
                    .height(80)
                    .fontSize(14)
                    .enabled(!this.submitting)
                    .onChange((value: string) => {
                      this.setValue(field.name, value);
                    })
                } else {
                  TextInput({ placeholder: field.placeholder !== undefined ? t(field.placeholder) : '', text: this.current(field) })
                    .width('100%')
                    .height(40)
                    .fontSize(14)
                    // number 走数字键盘，password 走掩码，其余 Normal
                    .type(field.type === 'number' ? InputType.Number
                      : (field.type === 'password' ? InputType.Password : InputType.Normal))
                    .enabled(!this.submitting)
                    .onChange((value: string) => {
                      this.setValue(field.name, value);
                    })
                }
              }
              .width('100%')
              .alignItems(HorizontalAlign.Start)
              .margin({ bottom: 12 })
            }, (field: FormField) => field.name)
          }
          .width('100%')
        }
        .width('100%')
        // 字段多时框内滚动，别把卡片撑出屏幕
        .constraintSize({ maxHeight: 420 })

        Row() {
          Button(t('common.cancel'))
            .fontSize(14)
            .fontColor('#666666')
            .backgroundColor('#F5F5F5')
            .layoutWeight(1)
            .enabled(!this.submitting)
            .onClick(() => {
              this.cancel();
            })

          Button(this.submitting ? t('common.submitting') : t('common.confirm'))
            .fontSize(14)
            .fontColor('#FFFFFF')
            .backgroundColor(this.submitting ? '#99BBFF' : '#1677FF')
            .layoutWeight(1)
            .margin({ left: 10 })
            .enabled(!this.submitting)
            .onClick(() => {
              this.submit();
            })
        }
        .width('100%')
        .margin({ top: 14 })
      }
      .width(340)
      .padding(16)
      .backgroundColor(Color.White)
      .borderRadius(12)
    }
    .width('100%')
    .height('100%')
  }
}

/**
 * 列表行尾动作：编辑 / 启用·停用 / 额外动作 / 删除（二次确认）。
 * statusText 为空串 = 该实体没有 status 字段（或状态多于两种、切换按钮表达不了），不渲染切换按钮。
 */
@Component
export struct RowActionBar {
  /** 语言变更要重渲染本组件（按钮文案由 t() 出） */
  @StorageLink('app_locale') localeCode: string = 'en';
  /** 「启用」/「停用」按钮文案（按当前状态给目标动作） */
  @Prop statusText: string = '';
  /** 额外动作文案（空串 = 不渲染），如排行榜「刷新缓存」 */
  @Prop extraText: string = '';
  /** 本行操作进行中：禁用全部按钮，防重复提交 */
  @Prop busy: boolean = false;
  /** 有没有更新端点（缺省 true）；false = 不渲染「编辑」（风控事件/反作弊事件是只读流水） */
  @Prop canEdit: boolean = true;
  /** 有没有删除端点（缺省 true）；false = 不渲染删除按钮 */
  @Prop canDelete: boolean = true;
  onEdit?: () => void;
  onDelete?: () => void;
  onToggle?: () => void;
  onExtra?: () => void;

  build() {
    Row() {
      if (this.canEdit) {
        Button(t('common.edit'))
          .fontSize(12)
          .fontColor('#1677FF')
          .backgroundColor('#E6F0FF')
          .borderRadius(4)
          .height(28)
          .padding({ left: 10, right: 10 })
          .enabled(!this.busy)
          .onClick(() => {
            if (this.onEdit) {
              this.onEdit();
            }
          })
      }

      if (this.statusText.length > 0) {
        Button(this.statusText)
          .fontSize(12)
          .fontColor('#52C41A')
          .backgroundColor('#E6FFF0')
          .borderRadius(4)
          .height(28)
          .padding({ left: 10, right: 10 })
          .margin({ left: 6 })
          .enabled(!this.busy)
          .onClick(() => {
            if (this.onToggle) {
              this.onToggle();
            }
          })
      }

      if (this.extraText.length > 0) {
        Button(this.extraText)
          .fontSize(12)
          .fontColor('#FA8C16')
          .backgroundColor('#FFF2E6')
          .borderRadius(4)
          .height(28)
          .padding({ left: 10, right: 10 })
          .margin({ left: 6 })
          .enabled(!this.busy)
          .onClick(() => {
            if (this.onExtra) {
              this.onExtra();
            }
          })
      }

      if (this.canDelete) {
        Button(t('common.delete'))
          .fontSize(12)
          .fontColor('#FF4D4F')
          .backgroundColor('#FFE6E6')
          .borderRadius(4)
          .height(28)
          .padding({ left: 10, right: 10 })
          .margin({ left: 6 })
          .enabled(!this.busy)
          .onClick(() => {
            if (this.onDelete) {
              this.onDelete();
            }
          })
      }
    }
  }
}
