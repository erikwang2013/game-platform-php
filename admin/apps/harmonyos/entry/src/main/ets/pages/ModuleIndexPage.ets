/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { ResourceModule } from '../common/ResourceSchema';
import { allModules } from '../common/ResourceModules';

/**
 * 「模块管理」索引页 —— 配置驱动那 18 个模块的入口
 * （内容 4 + 配置 4 + 用户与权限 3 + 资金 4 + 风控 3）。
 *
 * 本树此前没有模块菜单，个人中心是唯一的 hub；18 个模块全挂个人中心会把那个列表撑成好几屏，
 * 故单开一页：个人中心挂一条「模块管理」，这里是模块清单。
 * 清单直接来自 ResourceModules 的注册表，不在这里重抄一遍标题与顺序
 * （新增模块只改注册表，漏挂入口是不可能的）。
 * 行动作型的三个模块（身份审核 / 工单 / 提现）不在注册表里（它们不是 ResourceModule），
 * 由个人中心直接挂。
 */
@Entry
@Component
struct ModuleIndexPage {
  @State modules: ResourceModule[] = [];

  aboutToAppear(): void {
    this.modules = allModules();
  }

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

        Text('模块管理')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      List() {
        ForEach(this.modules, (module: ResourceModule) => {
          ListItem() {
            Row() {
              Column() {
                Text(module.title)
                  .fontSize(15)
                  .fontColor('#333333')

                Text(module.desc)
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
            .backgroundColor('#FFFFFF')
            .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
            .onClick(() => {
              router.pushUrl({ url: 'pages/ResourceManagePage', params: { module: module.key } });
            })
          }
        }, (module: ResourceModule) => module.key)
      }
      .width('100%')
      .layoutWeight(1)
      .margin({ top: 16 })
      .edgeEffect(EdgeEffect.Spring)
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}
