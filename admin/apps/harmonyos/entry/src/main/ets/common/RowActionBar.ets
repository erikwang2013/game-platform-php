/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { t } from '../i18n/Translations';

/**
 * 列表行尾动作：编辑 / 启用·停用 / 额外动作 / 删除（二次确认）。
 * statusText 为空串 = 该实体没有 status 字段（或状态多于两种、切换按钮表达不了），不渲染切换按钮。
 *
 * 从 FormDialog.ets 搬来（那份 701 行，越过本仓 500 行上限）：本组件与表单无任何耦合
 * ——不吃 FormDialog 的状态、不调它的方法，只是恰好挨着住。搬走时 FormDialog 原地
 * `export { RowActionBar } from './RowActionBar';`，故 GameManagePage / ResourceViews
 * 的 import 一行未改。
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
