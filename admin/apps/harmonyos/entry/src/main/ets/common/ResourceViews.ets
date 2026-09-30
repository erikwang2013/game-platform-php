/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 资源页共用的视图件：列表行 / 游戏选择条 / 分页条（批次 2），
 * 以及「要带参数的行内动作」弹框（批次 5，见 ExtraActionDialog）。
 * 单独成文件是为了让 ResourceManagePage 只留页面骨架（列表/弹框/加载态的状态机）。
 * 行内动作按钮本身仍在 FormDialog.ets 的 RowActionBar（批次 1 的底座）。
 */

import { ResourceRow, FormValues, FormPayload } from '../model/DataModels';
import { RowActionBar, FormDialog, FormField } from './FormDialog';
import { ResultDialog } from './Components';

/** 空预填值（FormDialog 会拷一份再改，共享一个常量安全） */
const NO_INITIAL: FormValues = {};

/**
 * 「行内动作要带参数」的两步弹框（批次 5）：先 FormDialog 收参数，成功后换 ResultDialog 展示回执。
 *
 * 风控规则的沙箱试算（要 user_id/check_type/context）、风控事件的处置（decision/note）、
 * 反作弊事件的审核（status/note）、以及四个独立页的设备封禁 / IP 信誉写入 / 解冻 / 团伙确认 ——
 * 这七八个动作的形状完全一样：**行 id + 一张小表单 → 若干行回执**，差别只在字段与打到哪个端点。
 * 页面把这两样传进来，动作的提交流程与结果展示就在这里收口（页面不重复写第二遍）。
 *
 * 失败（服务端 422 等）时错误原文留在参数框内且**不换结果框** —— 用户改完参数可以直接重试。
 */
@Component
export struct ExtraActionDialog {
  /** 参数框标题，同时是结果框标题的前缀（如「试算」→「试算结果」） */
  title: string = '';
  fields: FormField[] = [];
  /** 参数框预填值（团伙确认要从候选带 type/fingerprint 过来，故不能只给空） */
  initial: FormValues = NO_INITIAL;
  /** 行 id，原样作为 submit 的第一个参数（不需要行上下文时给空串） */
  actionId: string = '';
  /** 提交：成功返回要给运营看的若干行（结果框的内容），失败抛错 */
  submit?: (id: string, body: FormPayload) => Promise<string[]>;
  /** 关闭（取消 / 结果框点关闭都走它）；页面在这里关框并决定要不要刷新列表 */
  onClose?: () => void;

  @State resultOpen: boolean = false;
  @State resultLines: string[] = [];

  private close(): void {
    if (this.onClose) {
      this.onClose();
    }
  }

  private async run(body: FormPayload): Promise<void> {
    if (this.submit === undefined) {
      this.close();
      return;
    }
    this.resultLines = await this.submit(this.actionId, body);
    this.resultOpen = true;
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      if (this.resultOpen) {
        ResultDialog({
          title: `${this.title}结果`,
          lines: this.resultLines,
          onClose: () => {
            this.close();
          }
        })
      } else {
        FormDialog({
          title: this.title,
          fields: this.fields,
          initial: this.initial,
          onSubmit: (body: FormPayload) => this.run(body),
          // 参数已提交成功 ⇒ 关参数框，结果框由上面的 resultOpen 顶上（不调 onClose：那会连结果一起关掉）
          onDone: () => {},
          onCancel: () => {
            this.close();
          }
        })
      }
    }
    .width('100%')
    .height('100%')
  }
}

/**
 * 一行资源：主标题 + 状态徽标 + 摘要 + 行内动作（编辑/启用停用/额外动作/删除）。
 * 一切文案都由父页面算好传进来 —— 本组件不认识任何模块。
 */
@Component
export struct ResourceRowItem {
  @Prop title: string = '';
  @Prop subtitle: string = '';
  /** 状态徽标文案；空串 = 不渲染徽标 */
  @Prop badge: string = '';
  /** 徽标配色：true = 绿，false = 红 */
  @Prop badgeOk: boolean = true;
  /** 行内切换按钮文案；空串 = 本模块没有二态状态，不渲染切换按钮 */
  @Prop statusText: string = '';
  /** 额外动作文案；空串 = 不渲染 */
  @Prop extraText: string = '';
  /** 本行操作进行中：禁用本行按钮 */
  @Prop busy: boolean = false;
  /** 有没有更新端点（缺省 true），透传给 RowActionBar */
  @Prop canEdit: boolean = true;
  /** 有没有删除端点（缺省 true），透传给 RowActionBar */
  @Prop canDelete: boolean = true;
  onEdit?: () => void;
  onToggle?: () => void;
  onExtra?: () => void;
  onDelete?: () => void;

  build() {
    Column() {
      Row() {
        Text(this.title)
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        if (this.badge.length > 0) {
          Text(this.badge)
            .fontSize(10)
            .fontColor(this.badgeOk ? '#52C41A' : '#FF4D4F')
            .backgroundColor(this.badgeOk ? '#E6FFF0' : '#FFE6E6')
            .borderRadius(2)
            .padding({ left: 6, right: 6, top: 2, bottom: 2 })
        }
      }
      .width('100%')

      Row() {
        Text(this.subtitle)
          .fontSize(12)
          .fontColor('#999999')
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .layoutWeight(1)

        RowActionBar({
          statusText: this.statusText,
          extraText: this.extraText,
          busy: this.busy,
          canEdit: this.canEdit,
          canDelete: this.canDelete,
          onEdit: () => {
            if (this.onEdit) {
              this.onEdit();
            }
          },
          onToggle: () => {
            if (this.onToggle) {
              this.onToggle();
            }
          },
          onExtra: () => {
            if (this.onExtra) {
              this.onExtra();
            }
          },
          onDelete: () => {
            if (this.onDelete) {
              this.onDelete();
            }
          }
        })
      }
      .width('100%')
      .margin({ top: 8 })
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 10, bottom: 10 })
    .alignItems(HorizontalAlign.Start)
    .backgroundColor('#FFFFFF')
    .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
  }
}

/**
 * 「所属游戏」选择条：区服这类列表与新建都挂在某个游戏下的模块用。
 * 选项文案由父页面给（组件不认识游戏模型）。
 */
@Component
export struct GamePickerBar {
  @Prop options: SelectOption[] = [];
  /** 当前选中下标，-1 = 未选 */
  @Prop selected: number = -1;
  onPick?: (index: number) => void;

  build() {
    Row() {
      Text('所属游戏')
        .fontSize(14)
        .fontColor('#666666')

      Select(this.options)
        .value(this.selected >= 0 && this.selected < this.options.length ? this.options[this.selected].value : '请选择')
        .selected(this.selected)
        .layoutWeight(1)
        .margin({ left: 12 })
        .onSelect((index: number) => {
          if (this.onPick) {
            this.onPick(index);
          }
        })
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 8, bottom: 8 })
    .backgroundColor('#FFFFFF')
  }
}

/** 分页条：上一页 / 当前页 / 下一页；只有一页时父页面不挂它 */
@Component
export struct PagerBar {
  @Prop page: number = 1;
  @Prop pageCount: number = 1;
  onPrev?: () => void;
  onNext?: () => void;

  build() {
    Row() {
      Button('上一页')
        .fontSize(13)
        .fontColor(this.page > 1 ? '#1677FF' : '#CCCCCC')
        .backgroundColor(Color.Transparent)
        .enabled(this.page > 1)
        .onClick(() => {
          if (this.onPrev) {
            this.onPrev();
          }
        })

      Text(`${this.page} / ${this.pageCount}`)
        .fontSize(13)
        .fontColor('#666666')

      Button('下一页')
        .fontSize(13)
        .fontColor(this.page < this.pageCount ? '#1677FF' : '#CCCCCC')
        .backgroundColor(Color.Transparent)
        .enabled(this.page < this.pageCount)
        .onClick(() => {
          if (this.onNext) {
            this.onNext();
          }
        })
    }
    .width('100%')
    .height(48)
    .justifyContent(FlexAlign.Center)
    .backgroundColor('#FFFFFF')
    .border({ width: { top: 0.5 }, color: '#EEEEEE' })
  }
}
