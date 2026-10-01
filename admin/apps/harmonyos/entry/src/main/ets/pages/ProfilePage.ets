/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { TokenManager } from '../utils/TokenManager';
import { t, t1 } from '../i18n/Translations';
import { LocaleController, SUPPORTED_LOCALES, AppLocale } from '../i18n/LocaleController';

@Entry
@Component
struct ProfilePage {
  /**
   * 语言短码的**被观测**副本 —— 语言切换要重渲染整个页面就靠它。
   * 值与 `LOCALE_STORAGE_KEY`（`i18n/LocaleController.ets`）必须同名；
   * 装饰器参数写字符串字面量而不是 import 常量，ArkUI 的装饰器参数要求编译期字面量。
   */
  @StorageLink('app_locale') localeCode: string = 'en';

  /**
   * 登录名；取不到时**在 build 里**回落 `t('app.admin')`，不在这里写死译文 ——
   * 写死的话这个 @State 只求值一次，切语言后不会跟着变。
   */
  @State username: string = '';
  @State appVersion: string = '1.0.0';

  aboutToAppear(): void {
    // 可从 AppStorage 获取登录用户信息
    this.username = (AppStorage.get('adminUsername') as string) ?? '';
  }

  logout(): void {
    AlertDialog.show({
      title: t('app.logout_confirm_title'),
      message: t('app.logout_confirm_message'),
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: t('app.logout_confirm_ok'),
        fontColor: '#FF4D4F',
        action: () => {
          TokenManager.clearTokens();
          promptAction.showToast({ message: t('app.logged_out') });
          router.replaceUrl({ url: 'pages/LoginPage' });
        }
      },
      secondaryButton: {
        value: t('common.cancel'),
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

        Text(t('profile.title'))
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

            Text(this.username.length > 0 ? this.username : t('app.admin'))
              .fontSize(18)
              .fontWeight(FontWeight.Medium)
              .margin({ top: 12 })

            Text(t('app.super_admin'))
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
            this.buildMenuItem(t('profile.game_manage'), t('profile.game_manage_desc'), () => {
              router.pushUrl({ url: 'pages/GameManagePage' });
            });
            this.buildMenuItem(t('profile.announcement_manage'), t('profile.announcement_manage_desc'), () => {
              router.pushUrl({ url: 'pages/AnnouncementManagePage' });
            });
            // 后台账号本身的管理（≠「模块管理」里的「平台用户」：那是 C 端玩家）
            this.buildMenuItem(t('profile.admin_users'), t('profile.admin_users_desc'), () => {
              router.pushUrl({ url: 'pages/UserListPage' });
            });
            // 配置驱动的 18 个模块挂在索引页，不在这里铺平（否则这段菜单要两屏）
            this.buildMenuItem(t('profile.modules'), t('profile.modules_desc'), () => {
              router.pushUrl({ url: 'pages/ModuleIndexPage' });
            });
            // 风控的四块不是配置驱动的形状（行无 id / 行标识不是 id / 资金动作 / 三态 + 全局动作）
            this.buildMenuItem(t('profile.risk_center'), t('profile.risk_center_desc'), () => {
              router.pushUrl({ url: 'pages/RiskIndexPage' });
            });
            // 行动作型的三页不是 ResourceModule，进不了索引页，直接挂这里
            this.buildMenuItem(t('profile.identity_review'), t('profile.identity_review_desc'), () => {
              router.pushUrl({ url: 'pages/IdentityReviewPage' });
            });
            this.buildMenuItem(t('profile.tickets'), t('profile.tickets_desc'), () => {
              router.pushUrl({ url: 'pages/TicketManagePage' });
            });
            // 资金动作（审核 / 打款 / 同步）也要二次确认，同样不是 CRUD
            this.buildMenuItem(t('profile.withdraw_manage'), t('profile.withdraw_manage_desc'), () => {
              router.pushUrl({ url: 'pages/WithdrawManagePage' });
            });
            this.buildMenuItem(t('profile.change_password'), t('profile.change_password_desc'), () => {
              promptAction.showToast({ message: t('profile.feature_in_development') });
            });
            this.buildMenuItem(t('profile.clear_cache'), t('profile.clear_cache_desc'), () => {
              promptAction.showToast({ message: t('profile.cache_cleared') });
            });
            this.buildMenuItem(t('profile.check_update'),
              t1('profile.current_version', 'version', this.appVersion), () => {
                promptAction.showToast({ message: t('profile.up_to_date') });
              });
            this.buildMenuItem(t('profile.about'), t('profile.about_desc'), () => {
              promptAction.showToast({ message: 'Copyright (c) 2026 erik' });
            });
          }
          .width('100%')
          .backgroundColor('#FFFFFF')
          .borderRadius(8)
          .margin({ top: 16, left: 16, right: 16 })

          // 语言：13 种**平铺**（不做「中/英互切」开关），当前语言打点。
          // 用母语名而不是译名 —— 语言菜单要在「用户还看不懂当前界面语言」时也能选对。
          // 放在菜单之后：13 行会把上面的导航挤到屏外，而导航是这页的主职能、切语言是低频操作。
          Column() {
            Text(t('profile.language'))
              .fontSize(13)
              .fontColor('#999999')
              .width('100%')
              .padding({ left: 16, top: 12, bottom: 4 })

            ForEach(SUPPORTED_LOCALES, (item: AppLocale) => {
              Row() {
                Text(item.nativeName)
                  .fontSize(15)
                  .fontColor(item.code === this.localeCode ? '#1677FF' : '#333333')
                  .fontWeight(item.code === this.localeCode ? FontWeight.Medium : FontWeight.Normal)
                  .layoutWeight(1)

                if (item.code === this.localeCode) {
                  Text('✓')
                    .fontSize(16)
                    .fontColor('#1677FF')
                }
              }
              .width('100%')
              .height(40)
              .padding({ left: 16, right: 16 })
              .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
              .onClick(() => {
                // 只落值 + 落盘：界面由 @StorageLink 驱动重渲染，这里不需要手动刷新
                LocaleController.change(item.code);
              })
            }, (item: AppLocale) => item.code)
          }
          .width('100%')
          .backgroundColor('#FFFFFF')
          .borderRadius(8)
          .margin({ top: 16, left: 16, right: 16 })

          // 退出按钮
          Button(t('app.logout'))
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
