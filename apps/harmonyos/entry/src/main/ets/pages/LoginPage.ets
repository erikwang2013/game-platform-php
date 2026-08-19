/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { TokenManager } from '../utils/TokenManager';
import { AuthService } from '../service/DataService';
import { CopyrightBar } from '../common/Components';

@Entry
@Component
struct LoginPage {
  @State username: string = '';
  @State password: string = '';
  @State isLoading: boolean = false;
  @State isRegister: boolean = false;
  @State showPassword: boolean = false;
  @State loginError: string = '';

  async submit(): Promise<void> {
    const trimmedUsername = this.username.trim();
    if (!trimmedUsername) {
      this.loginError = '请输入用户名';
      return;
    }
    if (!this.password) {
      this.loginError = '请输入密码';
      return;
    }
    if (this.password.length < 6) {
      this.loginError = '密码至少 6 位';
      return;
    }

    this.isLoading = true;
    this.loginError = '';

    try {
      const data = this.isRegister
        ? await AuthService.register(trimmedUsername, this.password)
        : await AuthService.login(trimmedUsername, this.password);
      await TokenManager.saveTokens(data.access_token, data.refresh_token);
      TokenManager.saveUsername(data.user?.username ?? trimmedUsername);
      router.replaceUrl({ url: 'pages/GameHallPage' });
    } catch (e) {
      this.loginError = `${e}` || '网络错误，请检查连接';
    } finally {
      this.isLoading = false;
    }
  }

  build() {
    Column() {
      Scroll() {
        Column() {
          Column() {
            Text('游戏平台')
              .fontSize(24)
              .fontWeight(FontWeight.Bold)
              .fontColor('#1677FF')

            Text(this.isRegister ? '创建账号，开始游戏' : '欢迎回来')
              .fontSize(13)
              .fontColor('#999999')
              .margin({ top: 6 })
          }
          .width('100%')
          .padding({ top: 56, bottom: 32 })
          .justifyContent(FlexAlign.Center)

          Column() {
            TextInput({ placeholder: '请输入用户名', text: this.username })
              .height(48)
              .fontSize(15)
              .backgroundColor('#F5F5F5')
              .borderRadius(8)
              .padding({ left: 16, right: 16 })
              .onChange((v: string) => {
                this.username = v;
              })

            Row() {
              TextInput({ placeholder: '请输入密码', text: this.password })
                .height(48)
                .fontSize(15)
                .type(this.showPassword ? InputType.Normal : InputType.Password)
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .padding({ left: 16, right: 16 })
                .layoutWeight(1)
                .onChange((v: string) => {
                  this.password = v;
                })

              Button(this.showPassword ? '隐藏' : '显示')
                .fontSize(12)
                .fontColor('#1677FF')
                .backgroundColor(Color.Transparent)
                .width(56)
                .onClick(() => {
                  this.showPassword = !this.showPassword;
                })
            }
            .width('100%')
            .margin({ top: 14 })

            if (this.loginError) {
              Text(this.loginError)
                .fontSize(13)
                .fontColor('#FF4D4F')
                .width('100%')
                .textAlign(TextAlign.Center)
                .padding({ top: 12 })
            }

            Button(this.isRegister ? '注 册' : '登 录')
              .width('100%')
              .height(48)
              .fontSize(16)
              .fontColor('#FFFFFF')
              .backgroundColor(this.isLoading ? '#99BBFF' : '#1677FF')
              .borderRadius(8)
              .margin({ top: 20 })
              .enabled(!this.isLoading)
              .onClick(() => {
                this.submit();
              })
          }
          .width('100%')
          .padding({ left: 28, right: 28 })

          Row() {
            Text(this.isRegister ? '已有账号？' : '还没有账号？')
              .fontSize(13)
              .fontColor('#999999')
            Button(this.isRegister ? '去登录' : '立即注册')
              .fontSize(13)
              .fontColor('#1677FF')
              .backgroundColor(Color.Transparent)
              .onClick(() => {
                this.isRegister = !this.isRegister;
                this.loginError = '';
              })
          }
          .justifyContent(FlexAlign.Center)
          .margin({ top: 16 })

          CopyrightBar()
            .margin({ top: 24 })
        }
        .width('100%')
      }
      .width('100%')
      .layoutWeight(1)
      .scrollable(ScrollDirection.Vertical)
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#FFFFFF')
  }
}
