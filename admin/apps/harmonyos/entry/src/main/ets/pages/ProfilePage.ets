/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { TokenManager } from '../utils/TokenManager';

@Entry
@Component
struct ProfilePage {
  @State username: string = '管理员';
  @State role: string = '超级管理员';
  @State appVersion: string = '1.0.0';

  aboutToAppear(): void {
    // 可从 AppStorage 获取登录用户信息
    this.username = (AppStorage.get('adminUsername') as string) ?? '管理员';
  }

  logout(): void {
    AlertDialog.show({
      title: '确认退出',
      message: '确定要退出登录吗？',
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: '确定退出',
        fontColor: '#FF4D4F',
        action: () => {
          TokenManager.clearTokens();
          promptAction.showToast({ message: '已退出登录' });
          router.replaceUrl({ url: 'pages/LoginPage' });
        }
      },
      secondaryButton: {
        value: '取消',
        action: () => {}
      }
    });
  }

  build() {
    Column() {
      // 顶栏
      Row() {
        Button({ type: ButtonType.Circle }) {
          Image($rawfile('back.png')).width(24).height(24)
        }
        .width(36).height(36)
        .backgroundColor('#FFFFFF')
        .onClick(() => router.back())

        Text('个人中心')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      Scroll() {
        Column() {
          // 用户信息卡片
          Column() {
            Circle({ width: 64, height: 64 })
              .fill('#E6F0FF')

            Text(this.username)
              .fontSize(18)
              .fontWeight(FontWeight.Medium)
              .margin({ top: 12 })

            Text(this.role)
              .fontSize(13)
              .fontColor('#999999')
              .margin({ top: 4 })
          }
          .width('100%')
          .padding({ top: 32, bottom: 32 })
          .backgroundColor('#FFFFFF')
          .margin({ top: 16 })

          // 菜单列表
          Column() {
            this.buildMenuItem('修改密码', '修改登录密码', () => {
              promptAction.showToast({ message: '功能开发中' });
            });
            this.buildMenuItem('清除缓存', '清除本地缓存数据', () => {
              promptAction.showToast({ message: '缓存已清除' });
            });
            this.buildMenuItem('检查更新', `当前版本 ${this.appVersion}`, () => {
              promptAction.showToast({ message: '已是最新版本' });
            });
            this.buildMenuItem('关于', '开放管理后台 HarmonyOS 客户端', () => {
              promptAction.showToast({ message: 'Copyright (c) 2026 erik' });
            });
          }
          .width('100%')
          .backgroundColor('#FFFFFF')
          .borderRadius(8)
          .margin({ top: 16, left: 16, right: 16 })

          // 退出按钮
          Button('退出登录')
            .width('100%')
            .height(48)
            .fontSize(16)
            .fontColor('#FF4D4F')
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ top: 32, left: 16, right: 16 })
            .onClick(() => {
              this.logout();
            })

          // 版权
          Text('Copyright (c) 2026 erik — https://erik.xyz')
            .fontSize(11)
            .fontColor('#CCCCCC')
            .textAlign(TextAlign.Center)
            .width('100%')
            .margin({ top: 24, bottom: 32 })
        }
        .width('100%')
      }
      .width('100%')
      .layoutWeight(1)
      .backgroundColor('#F5F5F5')
    }
    .width('100%')
    .height('100%')
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

      Image($rawfile('arrow_right.png'))
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
