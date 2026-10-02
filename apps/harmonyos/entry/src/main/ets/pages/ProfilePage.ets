/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * ⚠ **已知缺口，只留注记、本批不造功能**：本树**没有实名认证（KYC）页面**。
 * 缺口是真的且用户无从自救：`service/app/api/v1/controller/WithdrawController.php:353`
 * 的 `withdrawLevel()` 只在 `identityStatus === 'approved'` 时回 `'verified'`，而本树
 * 没有任何入口能提交实名 ⇒ 用户**永远停在 default 档**，提现的单笔上下限/日/月额度/费率
 * （`WithdrawLimit::getByLevel()`，见 `applyLocked()` :123）全按最低档算，界面上也看不出为什么。
 * 两棵 web 树与 flutter 树都有 `/identity` 页可自助，本树是四棵里唯一缺的。
 * 补它＝新页面（身份表单 + 证件上传 + 状态轮询）+ 13 张表词条，属于新功能，不在此批。
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { TokenManager } from '../utils/TokenManager';
import { UserProfile } from '../model/DataModels';
import { UserService } from '../service/DataService';
import { LoadingView, ErrorView, BottomNav, CopyrightBar } from '../common/Components';
import { t } from '../i18n/Translations';
import { LocaleController, SUPPORTED_LOCALES, AppLocale } from '../i18n/LocaleController';

@Entry
@Component
struct ProfilePage {
  @StorageLink('app_locale') localeCode: string = 'zh';
  @State profile: UserProfile | null = null;
  @State nickname: string = '';
  @State isLoading: boolean = true;
  @State saving: boolean = false;
  @State errorMsg: string = '';
  @State deleteOpen: boolean = false;
  @State deletePassword: string = '';
  @State deleteConfirm: string = '';
  @State deleting: boolean = false;
  @State deleteError: string = '';

  aboutToAppear(): void {
    this.loadProfile();
  }

  async loadProfile(): Promise<void> {
    this.isLoading = true;
    this.errorMsg = '';
    try {
      this.profile = await UserService.profile();
      this.nickname = this.profile.nickname ?? '';
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : t('common.load_failed');
    } finally {
      this.isLoading = false;
    }
  }

  async saveNickname(): Promise<void> {
    const name = this.nickname.trim();
    if (!name) {
      promptAction.showToast({ message: t('profile.nickname_placeholder') });
      return;
    }
    this.saving = true;
    try {
      this.profile = await UserService.updateProfile({ nickname: name });
      this.nickname = this.profile.nickname ?? name;
      promptAction.showToast({ message: t('profile.saved') });
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: msg.length > 0 ? msg : t('profile.save_failed') });
    } finally {
      this.saving = false;
    }
  }

  logout(): void {
    AlertDialog.show({
      title: t('profile.logout_confirm_title'),
      message: t('profile.logout_confirm_message'),
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: t('profile.logout_confirm_ok'),
        fontColor: '#FF4D4F',
        action: () => {
          TokenManager.clearTokens();
          promptAction.showToast({ message: t('profile.logged_out') });
          router.replaceUrl({ url: 'pages/LoginPage' });
        }
      },
      secondaryButton: {
        value: t('common.cancel'),
        action: () => {}
      }
    });
  }

  /** 收起时清掉残留的密码/确认字，不在内存里留着 */
  closeDelete(): void {
    this.deletePassword = '';
    this.deleteConfirm = '';
    this.deleteError = '';
    this.deleteOpen = false;
  }

  async deleteAccount(): Promise<void> {
    this.deleting = true;
    this.deleteError = '';
    // 中间结论留在局部变量里，状态变量只写一次（@State 每写一次都会触发 UI 渲染）
    let err: string = '';
    try {
      try {
        await UserService.deleteAccount(this.deletePassword, this.deleteConfirm);
      } catch (e) {
        const msg: string = (e as Error).message;
        if (msg.indexOf('UNAUTHORIZED') >= 0) {
          router.replaceUrl({ url: 'pages/LoginPage' });
          return;
        }
        // 服务端拒绝原因原样展示（如「请先提现所有余额后再注销账号」），不吞成「操作失败」
        err = msg.length > 0 ? msg : t('profile.delete_failed');
      }

      if (err.length === 0) {
        // 成功不以「请求发出去了」为准：回读确认账号真的取不到
        let gone: boolean = false;
        try {
          gone = await UserService.accountGone();
        } catch (e) {
          err = t('profile.delete_unverified');
        }
        if (err.length === 0 && !gone) {
          err = t('profile.delete_pending');
        }
      }
    } finally {
      this.deleting = false;
    }

    this.deleteError = err;
    if (err.length > 0) {
      return;
    }

    this.deletePassword = '';
    this.deleteConfirm = '';
    TokenManager.clearTokens();
    promptAction.showToast({ message: t('profile.deleted') });
    router.replaceUrl({ url: 'pages/LoginPage' });
  }

  @Builder
  infoRow(label: string, value: string) {
    Row() {
      Text(label)
        .fontSize(13)
        .fontColor('#999999')
        .width(80)
      Text(value || '-')
        .fontSize(14)
        .fontColor('#333333')
        .layoutWeight(1)
    }
    .width('100%')
    .padding({ top: 8, bottom: 8 })
  }

  build() {
    Column() {
      Row() {
        Text(t('profile.title'))
          .fontSize(20)
          .fontWeight(FontWeight.Bold)
      }
      .width('100%')
      .padding({ left: 16, right: 16, top: 16, bottom: 8 })

      if (this.isLoading) {
        LoadingView({ message: t('profile.loading') })
      } else if (this.errorMsg) {
        ErrorView({ message: this.errorMsg, onRetry: () => { this.loadProfile(); } })
      } else {
        Scroll() {
          Column() {
            Column() {
              Column() {
                Text((this.profile?.nickname || this.profile?.username || '?').substring(0, 1))
                  .fontSize(28)
                  .fontColor('#1677FF')
                  .fontWeight(FontWeight.Bold)
              }
              .width(64)
              .height(64)
              .borderRadius(32)
              .backgroundColor('#E6F0FF')
              .justifyContent(FlexAlign.Center)

              Text(this.profile?.nickname || this.profile?.username || '')
                .fontSize(18)
                .fontWeight(FontWeight.Medium)
                .margin({ top: 12 })

              Text(`@${this.profile?.username || ''}`)
                .fontSize(13)
                .fontColor('#999999')
                .margin({ top: 4 })
            }
            .width('100%')
            .padding({ top: 24, bottom: 24 })
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 8 })

            Column() {
              this.infoRow(t('profile.field_username'), this.profile?.username ?? '')
              this.infoRow(t('profile.field_email'), this.profile?.email ?? '')
              this.infoRow(t('profile.field_phone'), this.profile?.phone ?? '')
              this.infoRow(t('profile.field_country'), this.profile?.country ?? '')
              this.infoRow(t('profile.field_language'), this.profile?.language ?? '')
              this.infoRow(t('profile.field_created_at'), this.profile?.created_at ?? '')
              this.infoRow(t('profile.field_last_login'), this.profile?.last_login_at ?? '')
            }
            .width('100%')
            .padding({ left: 16, right: 16, top: 8, bottom: 8 })
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            Column() {
              Text(t('profile.edit_nickname'))
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              TextInput({ placeholder: t('profile.nickname_placeholder'), text: this.nickname })
                .height(44)
                .fontSize(14)
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .onChange((v: string) => {
                  this.nickname = v;
                })

              Button(this.saving ? t('profile.saving') : t('common.save'))
                .width('100%')
                .height(44)
                .fontSize(15)
                .fontColor('#FFFFFF')
                .backgroundColor(this.saving ? '#99BBFF' : '#1677FF')
                .borderRadius(8)
                .margin({ top: 12 })
                .enabled(!this.saving)
                .onClick(() => {
                  this.saveNickname();
                })
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            // 语言：13 种**平铺**（不做「中/英互切」开关），当前语言打点。
            // 用母语名而不是译名 —— 语言菜单要在「用户还看不懂当前界面语言」时也能选对。
            // 位置与参照树 `admin/apps/harmonyos` 一致：昵称卡之后、退出按钮之前。
            Column() {
              Text(t('profile.ui_language'))
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
                  // 只落值 + 落盘，不手动刷新：本页订阅了 @StorageLink('app_locale')
                  // ⚠ 刷新时机本仓无运行时证据（无设备/模拟器），见 LocaleController 顶部说明
                  LocaleController.change(item.code);
                })
              }, (item: AppLocale) => item.code)
            }
            .width('100%')
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ top: 12, left: 16, right: 16 })

            Button(t('profile.logout'))
              .width('100%')
              .height(48)
              .fontSize(16)
              .fontColor('#FF4D4F')
              .backgroundColor('#FFFFFF')
              .borderRadius(8)
              .margin({ top: 24, left: 16, right: 16 })
              .onClick(() => {
                this.logout();
              })

            Column() {
              Text(t('profile.delete_account'))
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .fontColor('#FF4D4F')
                .width('100%')

              Text(t('profile.delete_warning'))
                .fontSize(12)
                .fontColor('#999999')
                .width('100%')
                .margin({ top: 6 })

              if (this.deleteOpen) {
                TextInput({ placeholder: t('profile.delete_password_placeholder'), text: this.deletePassword })
                  .type(InputType.Password)
                  .height(44)
                  .fontSize(14)
                  .backgroundColor('#F5F5F5')
                  .borderRadius(8)
                  .margin({ top: 12 })
                  .enabled(!this.deleting)
                  .onChange((v: string) => {
                    this.deletePassword = v;
                    this.deleteError = '';
                  })

                TextInput({ placeholder: t('profile.delete_confirm_placeholder'), text: this.deleteConfirm })
                  .height(44)
                  .fontSize(14)
                  .backgroundColor('#F5F5F5')
                  .borderRadius(8)
                  .margin({ top: 8 })
                  .enabled(!this.deleting)
                  .onChange((v: string) => {
                    this.deleteConfirm = v;
                    this.deleteError = '';
                  })

                if (this.deleteError.length > 0) {
                  Text(this.deleteError)
                    .fontSize(13)
                    .fontColor('#FF4D4F')
                    .width('100%')
                    .margin({ top: 8 })
                }

                Row() {
                  Button(this.deleting ? t('profile.deleting') : t('profile.delete_confirm_ok'))
                    .layoutWeight(1)
                    .height(40)
                    .fontSize(14)
                    .fontColor('#FFFFFF')
                    .backgroundColor(this.deleting ? '#FFB3B3' : '#FF4D4F')
                    .borderRadius(8)
                    .enabled(!this.deleting)
                    .onClick(() => {
                      this.deleteAccount();
                    })

                  Button(t('common.cancel'))
                    .layoutWeight(1)
                    .height(40)
                    .fontSize(14)
                    .fontColor('#666666')
                    .backgroundColor('#F5F5F5')
                    .borderRadius(8)
                    .margin({ left: 8 })
                    .enabled(!this.deleting)
                    .onClick(() => {
                      this.closeDelete();
                    })
                }
                .width('100%')
                .margin({ top: 12 })
              } else {
                Button(t('profile.delete_account'))
                  .width('100%')
                  .height(40)
                  .fontSize(14)
                  .fontColor('#FF4D4F')
                  .backgroundColor('#FFF1F0')
                  .borderRadius(8)
                  .margin({ top: 12 })
                  .onClick(() => {
                    this.deleteError = '';
                    this.deleteOpen = true;
                  })
              }
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            CopyrightBar()
              .margin({ top: 16, bottom: 16 })
          }
          .width('100%')
        }
        .layoutWeight(1)
      }

      BottomNav({ current: 'profile' })
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}
