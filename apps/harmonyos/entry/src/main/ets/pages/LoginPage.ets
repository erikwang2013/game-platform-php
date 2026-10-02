/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { TokenManager } from '../utils/TokenManager';
import { AuthService } from '../service/DataService';
import { CopyrightBar } from '../common/Components';
import { CaptchaDialog } from '../common/CaptchaDialog';
import { CaptchaClick, LoginResponse } from '../model/DataModels';
import { t } from '../i18n/Translations';

@Entry
@Component
struct LoginPage {
  @StorageLink('app_locale') localeCode: string = 'zh';
  @State username: string = '';
  @State password: string = '';
  @State isLoading: boolean = false;
  @State isRegister: boolean = false;
  @State showPassword: boolean = false;
  @State loginError: string = '';
  /** 验证码弹框开关：登录与注册共用 */
  @State captchaOpen: boolean = false;
  /** 2FA 第二步的登录票据。**非空 = 密码已验过、账号开了 2FA，此刻只差一个码**（空串＝还在第一步） */
  @State pending2fa: string = '';
  @State code2fa: string = '';

  /**
   * 本地必填校验 → 开验证码框。
   * 验证码由服务端强制（登录/注册同款），校验不过就不该消耗一次取图
   */
  submit(): void {
    const trimmedUsername = this.username.trim();
    if (!trimmedUsername) {
      this.loginError = t('login.username_placeholder');
      return;
    }
    if (!this.password) {
      this.loginError = t('login.password_placeholder');
      return;
    }
    if (this.isRegister) {
      if (
        this.password.length < 8 ||
        this.password.length > 32 ||
        !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/.test(this.password)
      ) {
        this.loginError = t('login.password_format_register');
        return;
      }
    } else if (this.password.length < 6) {
      this.loginError = t('login.password_min_login');
      return;
    }

    this.loginError = '';
    this.captchaOpen = true;
  }

  /** 验证码确认后真正调接口：captcha_key + clicks 与原字段同级 */
  async doSubmit(captchaKey: string, clicks: CaptchaClick[]): Promise<void> {
    const trimmedUsername = this.username.trim();

    this.isLoading = true;
    this.loginError = '';

    try {
      if (this.isRegister) {
        const data = await AuthService.register(trimmedUsername, this.password, captchaKey, clicks);
        await this.finishLogin(data, trimmedUsername);
        return;
      }
      const data = await AuthService.login(trimmedUsername, this.password, captchaKey, clicks);
      // 开了 2FA 的账号：服务端**不下发令牌对**，只回 require_2fa + pending_2fa_token。
      // 票据留到第二步，界面切到输码表（原本这一步直接抛错＝这类账号永远登不进来）
      if (data.require_2fa === true) {
        this.pending2fa = data.pending_2fa_token ?? '';
        this.code2fa = '';
        return;
      }
      await this.finishLogin(data, trimmedUsername);
    } catch (e) {
      this.loginError = `${e}` || t('login.network_error');
    } finally {
      this.isLoading = false;
      // 成败都关框：成功已 replaceUrl 离开本页；失败即作废本次挑战，
      // 服务端 message 显示在原错误位，下次开框重取新图
      this.captchaOpen = false;
    }
  }

  /**
   * 2FA 第二步：票据 + 动态码（6 位 TOTP 或 10 位备份码）换令牌对。
   *
   * 这一步**不走验证码**：服务端 `TwoFactorController::verify` 只校票据与码，
   * 另有逐用户 5 次失败锁 15 分钟；再叠一层图形验证码只会让输错码的人多过一道闸。
   * 失败（码错 / 票据 10 分钟过期）留在本步，由用户决定重输还是返回第一步。
   */
  async submit2fa(): Promise<void> {
    const code = this.code2fa.trim();
    if (this.isLoading || code.length === 0 || this.pending2fa.length === 0) {
      return;
    }

    this.isLoading = true;
    this.loginError = '';
    try {
      const data = await AuthService.verify2fa(this.pending2fa, code);
      await this.finishLogin(data, this.username.trim());
    } catch (e) {
      this.loginError = `${e}` || t('login.network_error');
    } finally {
      this.isLoading = false;
    }
  }

  /** 回第一步：作废票据（重输密码会重新走一次验证码 + 登录） */
  backToLogin(): void {
    this.pending2fa = '';
    this.code2fa = '';
    this.loginError = '';
  }

  /**
   * 存令牌并进主页面 —— **没有令牌一律当失败，绝不"登录成功"**。
   *
   * 这道兜底是必须的：`saveTokens(undefined, undefined)` 会让 AppStorage 里存进空串、
   * 页面跳进大厅，之后每个请求都 401（看起来像"登录后又掉线"）。
   * 2FA 那条路服务端**刻意**不下发令牌对，任何漏判都会落到这里。
   */
  private async finishLogin(data: LoginResponse, fallbackName: string): Promise<void> {
    if (!data.access_token || !data.refresh_token) {
      this.loginError = t('login.network_error');
      return;
    }
    await TokenManager.saveTokens(data.access_token, data.refresh_token);
    TokenManager.saveUsername(data.user?.username ?? fallbackName);
    router.replaceUrl({ url: 'pages/GameHallPage' });
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      // 验证码弹框：遮罩与卡片是 Stack 里的兄弟节点，
      // 卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      if (this.captchaOpen) {
        CaptchaDialog({
          submitting: this.isLoading,
          onCancel: () => {
            this.captchaOpen = false;
          },
          onConfirm: (key: string, clicks: CaptchaClick[]) => {
            this.doSubmit(key, clicks);
          }
        })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#FFFFFF')
  }

  /** 第一步：用户名 + 密码（注册同款） */
  @Builder
  credentialForm() {
    TextInput({ placeholder: t('login.username_placeholder'), text: this.username })
      .height(48)
      .fontSize(15)
      .backgroundColor('#F5F5F5')
      .borderRadius(8)
      .padding({ left: 16, right: 16 })
      .onChange((v: string) => {
        this.username = v;
      })

    Row() {
      TextInput({ placeholder: t('login.password_placeholder'), text: this.password })
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

      Button(this.showPassword ? t('login.hide_password') : t('login.show_password'))
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

    Button(this.isRegister ? t('login.register_button') : t('login.login_button'))
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

  /**
   * 第二步：账号已开 2FA，输一个动态码。
   *
   * ⚠ `maxLength(10)` 不是随手写的：**备份码是 10 位**（服务端 `enable()` 一次发 8 个，
   * 丢验证器的人只能靠它）。写死 6 会让备份码被输入框自己截断 ⇒ 必然校验失败。
   * 同理不加数字键盘限制 —— 备份码是大小写字母 + 数字。
   */
  @Builder
  tfaForm() {
    Text(t('login.require_2fa'))
      .fontSize(13)
      .fontColor('#999999')
      .width('100%')
      .textAlign(TextAlign.Center)

    TextInput({ placeholder: t('login.tfa_code_placeholder'), text: this.code2fa })
      .height(48)
      .fontSize(15)
      .maxLength(10)
      .backgroundColor('#F5F5F5')
      .borderRadius(8)
      .padding({ left: 16, right: 16 })
      .margin({ top: 14 })
      .onChange((v: string) => {
        this.code2fa = v;
      })

    if (this.loginError) {
      Text(this.loginError)
        .fontSize(13)
        .fontColor('#FF4D4F')
        .width('100%')
        .textAlign(TextAlign.Center)
        .padding({ top: 12 })
    }

    Button(t('login.login_button'))
      .width('100%')
      .height(48)
      .fontSize(16)
      .fontColor('#FFFFFF')
      .backgroundColor(this.isLoading ? '#99BBFF' : '#1677FF')
      .borderRadius(8)
      .margin({ top: 20 })
      .enabled(!this.isLoading && this.code2fa.trim().length > 0)
      .onClick(() => {
        this.submit2fa();
      })

    Button(t('login.tfa_back'))
      .width('100%')
      .fontSize(13)
      .fontColor('#1677FF')
      .backgroundColor(Color.Transparent)
      .margin({ top: 4 })
      .enabled(!this.isLoading)
      .onClick(() => {
        this.backToLogin();
      })
  }

  @Builder
  mainView() {
    Column() {
      Scroll() {
        Column() {
          Column() {
            Text(t('app.name'))
              .fontSize(24)
              .fontWeight(FontWeight.Bold)
              .fontColor('#1677FF')

            Text(this.isRegister ? t('login.subtitle_register') : t('login.subtitle_login'))
              .fontSize(13)
              .fontColor('#999999')
              .margin({ top: 6 })
          }
          .width('100%')
          .padding({ top: 56, bottom: 32 })
          .justifyContent(FlexAlign.Center)

          Column() {
            if (this.pending2fa) {
              this.tfaForm()
            } else {
              this.credentialForm()
            }
          }
          .width('100%')
          .padding({ left: 28, right: 28 })

          Row() {
            Text(this.isRegister ? t('login.have_account') : t('login.no_account'))
              .fontSize(13)
              .fontColor('#999999')
            Button(this.isRegister ? t('login.go_login') : t('login.go_register'))
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
