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
        err = msg.length > 0 ? msg : '注销失败';
      }

      if (err.length === 0) {
        // 成功不以「请求发出去了」为准：回读确认账号真的取不到
        let gone: boolean = false;
        try {
          gone = await UserService.accountGone();
        } catch (e) {
          err = '注销结果无法确认，请重新登录后再核实';
        }
        if (err.length === 0 && !gone) {
          err = '注销请求已提交，但账号资料仍可读取，请刷新后确认';
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
    promptAction.showToast({ message: '账号已注销' });
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

            Column() {
              Text('注销账号')
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .fontColor('#FF4D4F')
                .width('100%')

              Text('注销后该账号无法再登录，个人资料会被匿名化。账号内余额需先自行提现清零，否则服务端会拒绝注销。')
                .fontSize(12)
                .fontColor('#999999')
                .width('100%')
                .margin({ top: 6 })

              if (this.deleteOpen) {
                TextInput({ placeholder: '请输入当前密码', text: this.deletePassword })
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

                TextInput({ placeholder: '输入 yes 确认注销', text: this.deleteConfirm })
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
                  Button(this.deleting ? '注销中...' : '确认注销')
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

                  Button('取消')
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
                Button('注销账号')
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
