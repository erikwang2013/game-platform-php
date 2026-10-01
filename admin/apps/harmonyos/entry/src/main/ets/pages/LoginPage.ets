/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import http from '@ohos.net.http';
import { TokenManager } from '../utils/TokenManager';
import { API_BASE_URL } from '../common/AppConfig';
import { t, t1 } from '../i18n/Translations';

// 库仅下发 text/order, 刻意不含 x/y: 坐标为服务端校验依据, 下发即泄题
interface CaptchaTarget {
  order: number;
  text: string;
}

interface CaptchaData {
  key: string;
  image: string; // base64(无 data URI 前缀)
  texts: CaptchaTarget[];
}

interface ClickPoint {
  x: number;
  y: number;
}

/**
 * 已点标记。必须显式声明成 interface：ArkTS 禁 `arkts-no-obj-literals-as-types`，
 * 内联的 `{x: number, y: number, order: number}` 既不能当字段类型、也不能当 ForEach 回调参数类型
 * （真编译器报错；codelinter 对此类规则完全免疫，只能靠 hvigor 编译发现）。
 */
interface ClickMarker {
  x: number;
  y: number;
  order: number;
}

/** generate 的原始响应形状（texts 在 extra 里，与归一化后的 CaptchaData 不同形） */
interface CaptchaExtra {
  texts?: CaptchaTarget[];
}

interface CaptchaRawData {
  key: string;
  image: string;
  extra?: CaptchaExtra;
}

interface CaptchaResp {
  code: number;
  message?: string;
  data?: CaptchaRawData;
}

interface LoginData {
  access_token: string;
  refresh_token: string;
}

interface LoginResp {
  code: number;
  message?: string;
  data?: LoginData;
}

/**
 * 画布尺寸：服务端点击验证码恒为 300x200（easy/medium/hard 三档实测一致）。
 * 图按 1:1 显示，点击坐标无需换算；若服务端改画布，这里与 Image 尺寸须同步改。
 */
const CAPTCHA_W = 300;
const CAPTCHA_H = 200;

@Entry
@Component
struct LoginPage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State username: string = '';
  @State password: string = '';
  @State isLoading: boolean = false;
  @State showPassword: boolean = false;

  // 验证码状态
  @State captchaOpen: boolean = false;
  @State captchaData: CaptchaData | null = null;
  @State captchaLoading: boolean = false;
  @State clicks: ClickPoint[] = [];
  @State clickMarkers: ClickMarker[] = [];
  @State captchaError: string = '';
  @State loginError: string = '';

  private httpClient: http.HttpRequest = http.createHttp();

  async loadCaptcha(): Promise<void> {
    this.captchaLoading = true;
    this.clicks = [];
    this.clickMarkers = [];
    this.captchaError = '';

    try {
      const resp = await this.httpClient.request(`${API_BASE_URL}/api/v1/captcha/generate`, {
        method: http.RequestMethod.POST,
        header: { 'Content-Type': 'application/json', 'X-Client-Platform': 'harmonyos' },
        extraData: JSON.stringify({ difficulty: 'medium' }),
        connectTimeout: 10000,
        readTimeout: 10000
      });

      // 显式标注：JSON.parse 返回 any，ArkTS 禁裸 any（arkts-no-any-unknown）
      const result: CaptchaResp = JSON.parse(resp.result as string) as CaptchaResp;
      const d: CaptchaRawData | undefined = result.data;
      if (result.code === 0 && d !== undefined) {
        this.captchaData = { key: d.key, image: d.image, texts: d.extra?.texts ?? [] };
        this.captchaData!.texts.sort((a, b) => a.order - b.order);
      } else {
        this.captchaError = t('login.captcha_load_failed');
      }
    } catch (e) {
      this.captchaError = t('login.captcha_network_error');
    } finally {
      this.captchaLoading = false;
    }
  }

  /** 账号密码齐才弹框；关框即作废本次挑战 */
  openCaptcha(): void {
    const trimmed = this.username.trim();
    if (!trimmed) {
      this.loginError = t('login.username_placeholder');
      return;
    }
    if (!this.password) {
      this.loginError = t('login.password_placeholder');
      return;
    }
    this.loginError = '';
    this.captchaOpen = true;
    this.captchaData = null;
    this.loadCaptcha();
  }

  closeCaptcha(): void {
    this.captchaOpen = false;
    this.captchaData = null;
    this.clicks = [];
    this.clickMarkers = [];
  }

  /** 图按 1:1 显示（宽高比与画布一致，无留白），组件坐标即画布坐标 */
  onCaptchaClick(event: ClickEvent): void {
    if (!this.captchaData || this.clicks.length >= this.captchaData.texts.length) return;

    const imgX = Math.round(event.x);
    const imgY = Math.round(event.y);
    if (imgX < 0 || imgY < 0 || imgX > CAPTCHA_W || imgY > CAPTCHA_H) return;

    this.clicks.push({ x: imgX, y: imgY });
    this.clickMarkers.push({ x: event.x, y: event.y, order: this.clicks.length });
    this.captchaError = '';
    this.loginError = '';
  }

  async login(): Promise<void> {
    const trimmedUsername = this.username.trim();
    if (!trimmedUsername) {
      this.loginError = t('login.username_placeholder');
      return;
    }
    if (!this.password) {
      this.loginError = t('login.password_placeholder');
      return;
    }
    if (!this.captchaData || this.clicks.length < this.captchaData.texts.length) return;

    this.isLoading = true;
    this.loginError = '';

    try {
      const resp = await this.httpClient.request(`${API_BASE_URL}/api/v1/auth/login`, {
        method: http.RequestMethod.POST,
        header: { 'Content-Type': 'application/json', 'X-Client-Platform': 'harmonyos' },
        extraData: JSON.stringify({
          username: trimmedUsername,
          password: this.password,
          captcha_key: this.captchaData!.key,
          clicks: this.clicks
        }),
        connectTimeout: 15000,
        readTimeout: 15000
      });

      const result: LoginResp = JSON.parse(resp.result as string) as LoginResp;
      const d: LoginData | undefined = result.data;
      if (result.code === 0 && d !== undefined) {
        await TokenManager.saveTokens(d.access_token, d.refresh_token);
        AppStorage.setOrCreate('adminUsername', trimmedUsername);
        router.replaceUrl({ url: 'pages/DashboardPage' });
      } else {
        this.loginError = result.message ?? t('login.error_failed');
        this.closeCaptcha();
      }
    } catch (e) {
      this.loginError = t('login.error_network');
      this.closeCaptcha();
    } finally {
      this.isLoading = false;
    }
  }

  /** 弹框内的验证码主体：提示 + 图 + 标记 + 计数 */
  @Builder
  captchaBody() {
    Column() {
      Row() {
        Text(t('login.captcha_prompt'))
          .fontSize(12)
          .fontColor('#666666')
        ForEach(this.captchaData!.texts, (t: CaptchaTarget, i: number) => {
          Text(i > 0 ? ' → ' : '')
            .fontSize(12)
            .fontColor('#999999')
          Text(`"${t.text}"`)
            .fontSize(13)
            .fontWeight(this.clicks.length === i ? FontWeight.Bold : FontWeight.Normal)
            .fontColor(this.clicks.length > i ? '#52C41A' : (this.clicks.length === i ? '#1677FF' : '#333333'))
        })
      }
      .width('100%')
      .margin({ bottom: 8 })

      Stack({ alignContent: Alignment.TopStart }) {
        Image((this.captchaData!.image.startsWith('data:') ?? false)
          ? this.captchaData!.image
          : `data:image/png;base64,${this.captchaData!.image}`)
          .width(CAPTCHA_W)
          .height(CAPTCHA_H)
          .objectFit(ImageFit.Contain)
          .borderRadius(8)
          .border({ width: 1, color: '#EEEEEE' })
          .onClick((event: ClickEvent) => { this.onCaptchaClick(event); })

        ForEach(this.clickMarkers, (m: ClickMarker) => {
          Circle({ width: 26, height: 26 })
            .fill('#1677FFCC')
            .position({ x: m.x - 13, y: m.y - 13 })
          Text(`${m.order}`)
            .fontSize(13)
            .fontColor('#FFFFFF')
            .fontWeight(FontWeight.Bold)
            .position({ x: m.x - 3, y: m.y - 8 })
        })
      }
      .width(CAPTCHA_W)
      .height(CAPTCHA_H)

      Row() {
        // 两个占位符串起来换（t1 只吃一个）
        Text(t('login.captcha_progress').replace('{done}', `${this.clicks.length}`)
          .replace('{total}', `${this.captchaData!.texts.length}`))
          .fontSize(12)
          .fontColor('#999999')

        Blank()

        Button(t('login.captcha_refresh'))
          .fontSize(12)
          .fontColor('#1677FF')
          .backgroundColor(Color.Transparent)
          .onClick(() => { this.loadCaptcha(); })
      }
      .width('100%')
      .margin({ top: 6 })
    }
    .width('100%')
  }

  @Builder
  captchaDialog() {
    Column() {
      Text(t('login.captcha_section'))
        .fontSize(16)
        .fontWeight(FontWeight.Bold)
        .width('100%')
        .margin({ bottom: 12 })

      if (this.captchaLoading) {
        LoadingProgress()
          .width(36)
          .height(36)
          .color('#1677FF')
      } else if (this.captchaError && !this.captchaData) {
        Column() {
          Text(this.captchaError)
            .fontSize(13)
            .fontColor('#FF4D4F')
          Button(t('common.retry'))
            .fontSize(13)
            .fontColor('#1677FF')
            .backgroundColor(Color.Transparent)
            .margin({ top: 6 })
            .onClick(() => { this.loadCaptcha(); })
        }
        .width('100%')
      } else if (this.captchaData) {
        this.captchaBody()
      }

      Row() {
        Button(t('common.cancel'))
          .fontSize(14)
          .fontColor('#666666')
          .backgroundColor('#F5F5F5')
          .layoutWeight(1)
          .onClick(() => { this.closeCaptcha(); })

        Button(this.isLoading ? t('login.submitting') : t('login.confirm_login'))
          .fontSize(14)
          .fontColor('#FFFFFF')
          .backgroundColor(this.isLoading || !this.captchaDone() ? '#99BBFF' : '#1677FF')
          .layoutWeight(1)
          .margin({ left: 10 })
          .enabled(!this.isLoading && this.captchaDone())
          .onClick(() => { this.login(); })
      }
      .width('100%')
      .margin({ top: 14 })
    }
    .width(340)
    .padding(16)
    .backgroundColor(Color.White)
    .borderRadius(12)
  }

  private captchaDone(): boolean {
    return this.captchaData !== null && this.clicks.length >= this.captchaData!.texts.length;
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      Column() {
        Scroll() {
          Column() {
            // 顶部品牌区
            Column() {
              // 吉祥物「小骰」(Dicey)：原为通用骰面 logo.svg，按 240:200 等比显示
              Image($r('app.media.mascot'))
                .width(72)
                .height(60)
                .objectFit(ImageFit.Contain)
                .margin({ bottom: 8 })

              Text(t('login.title'))
                .fontSize(22)
                .fontWeight(FontWeight.Bold)
                .fontColor('#1677FF')

              Text(t('login.subtitle'))
                .fontSize(12)
                .fontColor('#999999')
                .margin({ top: 2 })
            }
            .width('100%')
            .padding({ top: 40, bottom: 24 })
            .justifyContent(FlexAlign.Center)

            // 表单区
            Column() {
              // 用户名
              TextInput({ placeholder: t('login.username_placeholder'), text: this.username })
                .height(48)
                .fontSize(15)
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .padding({ left: 16, right: 16 })
                .onChange((v: string) => { this.username = v; })

              // 密码
              Row() {
                TextInput({ placeholder: t('login.password_placeholder'), text: this.password })
                  .height(48)
                  .fontSize(15)
                  .type(this.showPassword ? InputType.Normal : InputType.Password)
                  .backgroundColor('#F5F5F5')
                  .borderRadius(8)
                  .padding({ left: 16, right: 16 })
                  .layoutWeight(1)
                  .onChange((v: string) => { this.password = v; })

                Button(this.showPassword ? t('login.hide_password') : t('login.show_password'))
                  .fontSize(12)
                  .fontColor('#1677FF')
                  .backgroundColor(Color.Transparent)
                  .width(56)
                  .onClick(() => { this.showPassword = !this.showPassword; })
              }
              .width('100%')
              .margin({ top: 14 })

              Text(t('login.captcha_required_hint'))
                .fontSize(12)
                .fontColor('#999999')
                .width('100%')
                .margin({ top: 14 })

              // 错误提示
              if (this.loginError) {
                Text(this.loginError)
                  .fontSize(13)
                  .fontColor('#FF4D4F')
                  .width('100%')
                  .textAlign(TextAlign.Center)
                  .padding({ top: 10, bottom: 2 })
              }

              // 登录按钮 —— 点击后弹出验证码框
              Button(t('login.submit'))
                .width('100%')
                .height(48)
                .fontSize(16)
                .fontColor('#FFFFFF')
                .backgroundColor(this.isLoading ? '#99BBFF' : '#1677FF')
                .borderRadius(8)
                .margin({ top: 20 })
                .enabled(!this.isLoading)
                .onClick(() => { this.openCaptcha(); })
            }
            .width('100%')
            .padding({ left: 28, right: 28 })

            // 底部操作
            Row() {
              Text(t('login.no_account'))
                .fontSize(13)
                .fontColor('#999999')
              Button(t('login.register'))
                .fontSize(13)
                .fontColor('#1677FF')
                .backgroundColor(Color.Transparent)
                .onClick(() => {
                  promptAction.showToast({ message: t('login.register_soon') });
                })
            }
            .justifyContent(FlexAlign.Center)
            .margin({ top: 16 })

            // 版权
            Text('Copyright (c) 2026 erik — https://erik.xyz')
              .fontSize(10)
              .fontColor('#CCCCCC')
              .textAlign(TextAlign.Center)
              .width('100%')
              .padding({ top: 20, bottom: 24 })
          }
          .width('100%')
        }
        .width('100%')
        .layoutWeight(1)
        .scrollable(ScrollDirection.Vertical)
      }
      .width('100%')
      .height('100%')

      // 验证码弹框：遮罩与卡片是 Stack 里的兄弟节点，
      // 卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      if (this.captchaOpen) {
        Column()
          .width('100%')
          .height('100%')
          .backgroundColor('#00000066')
          .onClick(() => { this.closeCaptcha(); })

        this.captchaDialog()
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#FFFFFF')
  }
}
