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
          Image($r('app.media.ic_back')).width(24).height(24)
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
            // 管理入口：本树此前没有模块菜单，从个人中心挂两个资源管理页
            this.buildMenuItem('游戏管理', '新建 / 编辑 / 删除与上架下架', () => {
              router.pushUrl({ url: 'pages/GameManagePage' });
            });
            this.buildMenuItem('公告管理', '发布 / 编辑 / 删除与上下架', () => {
              router.pushUrl({ url: 'pages/AnnouncementManagePage' });
            });
            // 配置驱动的 18 个模块挂在索引页，不在这里铺平（否则这段菜单要两屏）
            this.buildMenuItem('模块管理', '游戏分类/区服/活动/成就/VIP/排行榜/国家/系统配置/平台用户/角色/权限/支付方式/优惠券/CDN/提现限额/风控规则/风控事件/反作弊事件', () => {
              router.pushUrl({ url: 'pages/ModuleIndexPage' });
            });
            // 风控的四块不是配置驱动的形状（行无 id / 行标识不是 id / 资金动作 / 三态 + 全局动作）
            this.buildMenuItem('风控中心', '异常用户冻结解冻 / 设备指纹 / IP 信誉 / 关联团伙', () => {
              router.pushUrl({ url: 'pages/RiskIndexPage' });
            });
            // 行动作型的三页不是 ResourceModule，进不了索引页，直接挂这里
            this.buildMenuItem('身份审核', 'KYC 认证记录的通过 / 驳回', () => {
              router.pushUrl({ url: 'pages/IdentityReviewPage' });
            });
            this.buildMenuItem('工单管理', '工单的回复 / 指派 / 关闭', () => {
              router.pushUrl({ url: 'pages/TicketManagePage' });
            });
            // 资金动作（审核 / 打款 / 同步）也要二次确认，同样不是 CRUD
            this.buildMenuItem('提现管理', '提现开关 / 订单审核 / 打款 / 同步', () => {
              router.pushUrl({ url: 'pages/WithdrawManagePage' });
            });
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
