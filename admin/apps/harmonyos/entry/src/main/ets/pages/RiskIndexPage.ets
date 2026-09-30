/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';

/**
 * 风控中心的入口页 —— 四个「行为不齐整」的风控模块各自独立成页，这里只做导航。
 *
 * 为什么这四块不进「模块管理」的配置驱动注册表：那套契约只表达
 * 「表单 CRUD + 一个无参数的（或带一张小表单的）行内动作」，而这四块分别是
 *   · 设备指纹：行内就有拉黑/解封（列表已回完整 fp_hash），但**同一行按 blocked 二选一出按钮**，
 *     与契约里「固定一个行内动作」的形状不同；IP 信誉：列表只回掩码而端点要**原 IP** ⇒
 *     只能页面级手填（PII 取舍，没放开）；
 *   · 异常用户：行标识是 user_id，且冻结/解冻是**资金动作**（解冻要一个金额入参 + 二次确认）；
 *   · 团伙：状态是**三态**（0/1/2，二态翻转控件会把 2 静默压成 0），检测又是无行上下文的全局动作。
 * 把它们塞进契约要给共用页加四个开关，反而比四页独立更贵（与 WithdrawManagePage 同一判断）。
 *
 * 另外三个风控模块（风控规则 / 风控事件 / 反作弊事件）形状齐整，已在注册表里，
 * 从「模块管理」进 —— 此处不重复列一遍。
 */
@Entry
@Component
struct RiskIndexPage {
  build() {
    Column() {
      // 顶栏
      Row() {
        Button({ type: ButtonType.Circle }) {
          Image($r('app.media.ic_back')).width(24).height(24)
        }
        .width(36).height(36)
        .backgroundColor('#FFFFFF')
        .onClick(() => router.back())

        Text('风控中心')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      Column() {
        this.buildMenuItem('异常用户', '冻结 / 解冻 / 风控时间线（资金动作需二次确认）', () => {
          router.pushUrl({ url: 'pages/RiskUserPage' });
        });
        this.buildMenuItem('设备指纹', '设备列表与行内拉黑 / 解封（真阻断，30 天自动解封）', () => {
          router.pushUrl({ url: 'pages/RiskDevicePage' });
        });
        this.buildMenuItem('IP 信誉', '拉黑 / 加白 / 申诉放行 / 重查（要原文 IP）', () => {
          router.pushUrl({ url: 'pages/RiskIpPage' });
        });
        this.buildMenuItem('关联团伙', '聚类检测候选、人工确认、三态状态与成员名单', () => {
          router.pushUrl({ url: 'pages/RiskClusterPage' });
        });
        this.buildMenuItem('模块管理', '风控规则 / 风控事件 / 反作弊事件三个模块在这里', () => {
          router.pushUrl({ url: 'pages/ModuleIndexPage' });
        });
      }
      .width('100%')
      .backgroundColor('#FFFFFF')
      .borderRadius(8)
      .margin({ top: 16, left: 16, right: 16 })
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }

  @Builder
  buildMenuItem(title: string, subtitle: string, onClick: () => void) {
    Row() {
      Column() {
        Text(title)
          .fontSize(15)
          .fontColor('#333333')

        Text(subtitle)
          .fontSize(12)
          .fontColor('#999999')
          .margin({ top: 2 })
      }
      .alignItems(HorizontalAlign.Start)
      .layoutWeight(1)

      Image($r('app.media.ic_arrow_right'))
        .width(16)
        .height(16)
        .fillColor('#CCCCCC')
    }
    .width('100%')
    .height(64)
    .padding({ left: 16, right: 16 })
    .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
    .onClick(onClick)
  }
}
