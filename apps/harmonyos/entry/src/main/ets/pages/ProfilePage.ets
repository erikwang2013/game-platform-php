/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { TokenManager } from '../utils/TokenManager';
import { UserProfile } from '../model/DataModels';
import { UserService } from '../service/DataService';
import { LoadingView, ErrorView, BottomNav, CopyrightBar } from '../common/Components';

@Entry
@Component
struct ProfilePage {
  @State profile: UserProfile | null = null;
  @State nickname: string = '';
  @State isLoading: boolean = true;
  @State saving: boolean = false;
  @State errorMsg: string = '';

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
      this.errorMsg = msg.length > 0 ? msg : '加载失败';
    } finally {
      this.isLoading = false;
    }
  }

  async saveNickname(): Promise<void> {
    const name = this.nickname.trim();
    if (!name) {
      promptAction.showToast({ message: '请输入昵称' });
      return;
    }
    this.saving = true;
    try {
      this.profile = await UserService.updateProfile({ nickname: name });
      this.nickname = this.profile.nickname ?? name;
      promptAction.showToast({ message: '已保存' });
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: msg.length > 0 ? msg : '保存失败' });
    } finally {
      this.saving = false;
    }
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
        Text('个人中心')
          .fontSize(20)
          .fontWeight(FontWeight.Bold)
      }
      .width('100%')
      .padding({ left: 16, right: 16, top: 16, bottom: 8 })

      if (this.isLoading) {
        LoadingView({ message: '加载资料中...' })
      } else if (this.errorMsg) {
        ErrorView({ message: this.errorMsg })
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
              this.infoRow('用户名', this.profile?.username ?? '')
              this.infoRow('邮箱', this.profile?.email ?? '')
              this.infoRow('手机', this.profile?.phone ?? '')
              this.infoRow('国家', this.profile?.country ?? '')
              this.infoRow('语言', this.profile?.language ?? '')
              this.infoRow('注册时间', this.profile?.created_at ?? '')
              this.infoRow('最近登录', this.profile?.last_login_at ?? '')
            }
            .width('100%')
            .padding({ left: 16, right: 16, top: 8, bottom: 8 })
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            Column() {
              Text('修改昵称')
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              TextInput({ placeholder: '请输入昵称', text: this.nickname })
                .height(44)
                .fontSize(14)
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .onChange((v: string) => {
                  this.nickname = v;
                })

              Button(this.saving ? '保存中...' : '保存')
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

            Button('退出登录')
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
