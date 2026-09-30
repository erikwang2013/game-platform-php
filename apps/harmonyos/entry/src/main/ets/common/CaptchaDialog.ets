/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { CaptchaService } from '../service/DataService';
import { CaptchaChallenge, CaptchaClick } from '../model/DataModels';

/**
 * 画布尺寸：服务端点击验证码恒为 300x200（easy/medium/hard 三档实测一致）。
 * 图按 1:1 显示，点击坐标无需换算；若服务端改画布，这里与 Image 尺寸须同步改。
 * 禁止改写成 400x250 之类再做换算 —— 那会让坐标系统性偏移，验证码永远点不对。
 */
const CAPTCHA_W = 300;
const CAPTCHA_H = 200;

/** 已点标记：x/y 为显示坐标（与画布同刻度），order 为点击序号 */
interface ClickMarker {
  x: number;
  y: number;
  order: number;
}

/**
 * 点击验证码弹框 —— 登录/注册、提现申请、兑换卖出 三处共用（买入后端刻意没加验证码）。
 *
 * 父页面用 captchaOpen 控制挂载，开框即现取新图（验证码一次性，复用旧 key 必验不过）：
 *
 *   if (this.captchaOpen) {
 *     CaptchaDialog({
 *       submitting: this.submitting,
 *       onCancel: () => { this.captchaOpen = false; },
 *       onConfirm: (key: string, clicks: CaptchaClick[]) => { this.doSubmit(key, clicks); }
 *     })
 *   }
 *
 * 确认后由父页面把 key + 画布坐标原样透传给原接口；失败时父页面关框，
 * 服务端 message 显示在原错误位，下次开框重取新图。
 */
@Component
export struct CaptchaDialog {
  /** 父页面正在提交原接口：提交中禁用框内按钮，避免重复提交 */
  @Prop submitting: boolean = false;
  /** 确认：把 captcha_key + clicks（画布坐标）交给父页面去调原接口 */
  onConfirm?: (key: string, clicks: CaptchaClick[]) => void;
  onCancel?: () => void;

  @State cap: CaptchaChallenge | null = null;
  @State loading: boolean = true;
  @State errorMsg: string = '';
  @State clicks: CaptchaClick[] = [];
  @State markers: ClickMarker[] = [];

  aboutToAppear(): void {
    this.load();
  }

  /** 取新图；失败保留错误态由用户点「重试」，不静默留个空框 */
  async load(): Promise<void> {
    this.loading = true;
    this.cap = null;
    this.clicks = [];
    this.markers = [];
    this.errorMsg = '';
    try {
      this.cap = await CaptchaService.generate();
    } catch (e) {
      const msg = `${e}`;
      this.errorMsg = msg.length > 0 ? msg : '验证码加载失败';
    } finally {
      this.loading = false;
    }
  }

  private cancel(): void {
    if (this.onCancel) {
      this.onCancel();
    }
  }

  /** 撤销 = 退回上一个点，坐标与标记两个数组必须同步弹尾 */
  private undoClick(): void {
    if (this.clicks.length === 0) {
      return;
    }
    this.clicks.pop();
    this.markers.pop();
  }

  private done(): boolean {
    const cap = this.cap;
    return cap !== null && this.clicks.length >= cap.texts.length;
  }

  private confirm(): void {
    const cap = this.cap;
    if (this.submitting || !cap || !this.done()) {
      return;
    }
    if (this.onConfirm) {
      this.onConfirm(cap.key, this.clicks);
    }
  }

  /** 图按 1:1 显示（宽高比与画布一致，无留白），组件坐标即画布坐标 */
  private onImageClick(event: ClickEvent): void {
    const cap = this.cap;
    if (!cap || this.clicks.length >= cap.texts.length) {
      return;
    }

    const imgX = Math.round(event.x);
    const imgY = Math.round(event.y);
    if (imgX < 0 || imgY < 0 || imgX > CAPTCHA_W || imgY > CAPTCHA_H) {
      return;
    }

    this.clicks.push({ x: imgX, y: imgY });
    this.markers.push({ x: event.x, y: event.y, order: this.clicks.length });
  }

  /** 提示行：按服务端下发的顺序拼出要点的字，坐标不下发，只给顺序 */
  private hint(): string {
    const cap = this.cap;
    if (!cap) {
      return '';
    }
    const parts: string[] = [];
    for (let i = 0; i < cap.texts.length; i++) {
      parts.push(`"${cap.texts[i].text}"`);
    }
    return parts.join(' → ');
  }

  /** 服务端下发的 image 是裸 base64（已剥 data URI 前缀），ArkUI 需要 data URI 才能渲染 */
  private imageSrc(): string {
    const cap = this.cap;
    if (!cap) {
      return '';
    }
    return cap.image.startsWith('data:') ? cap.image : `data:image/png;base64,${cap.image}`;
  }

  @Builder
  captchaBody() {
    Column() {
      Text(`请按顺序点击图中文字: ${this.hint()}`)
        .fontSize(12)
        .fontColor('#666666')
        .width('100%')
        .margin({ bottom: 8 })

      Stack({ alignContent: Alignment.TopStart }) {
        Image(this.imageSrc())
          .width(CAPTCHA_W)
          .height(CAPTCHA_H)
          .objectFit(ImageFit.Contain)
          .borderRadius(8)
          .border({ width: 1, color: '#EEEEEE' })
          .onClick((event: ClickEvent) => {
            this.onImageClick(event);
          })

        ForEach(this.markers, (m: ClickMarker) => {
          // 圆点与序号合成一个 Stack（ForEach 每轮只生成一个组件），整体定位到点击处
          Stack() {
            Circle({ width: 26, height: 26 })
              .fill('#1677FFCC')
            Text(`${m.order}`)
              .fontSize(13)
              .fontColor('#FFFFFF')
              .fontWeight(FontWeight.Bold)
          }
          .width(26)
          .height(26)
          .position({ x: m.x - 13, y: m.y - 13 })
        }, (m: ClickMarker) => `${m.order}`)
      }
      .width(CAPTCHA_W)
      .height(CAPTCHA_H)

      Row() {
        Text(`已点击 ${this.clicks.length}/${this.cap!.texts.length}`)
          .fontSize(12)
          .fontColor('#999999')

        Blank()

        Button('撤销')
          .fontSize(12)
          .fontColor('#1677FF')
          .backgroundColor(Color.Transparent)
          .enabled(!this.submitting && this.clicks.length > 0)
          .onClick(() => {
            this.undoClick();
          })

        Button('换一张')
          .fontSize(12)
          .fontColor('#1677FF')
          .backgroundColor(Color.Transparent)
          .enabled(!this.submitting)
          .onClick(() => {
            this.load();
          })
      }
      .width('100%')
      .margin({ top: 6 })
    }
    .width('100%')
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      // 遮罩与卡片是 Stack 里的兄弟节点，卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      Column()
        .width('100%')
        .height('100%')
        .backgroundColor('#00000066')
        .onClick(() => {
          this.cancel();
        })

      Column() {
        Text('安全验证')
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .margin({ bottom: 12 })

        if (this.loading) {
          LoadingProgress()
            .width(36)
            .height(36)
            .color('#1677FF')
        } else if (this.cap === null) {
          Column() {
            Text(this.errorMsg || '验证码加载失败')
              .fontSize(13)
              .fontColor('#FF4D4F')
            Button('重试')
              .fontSize(13)
              .fontColor('#1677FF')
              .backgroundColor(Color.Transparent)
              .margin({ top: 6 })
              .onClick(() => {
                this.load();
              })
          }
          .width('100%')
        } else {
          this.captchaBody()
        }

        Row() {
          Button('取消')
            .fontSize(14)
            .fontColor('#666666')
            .backgroundColor('#F5F5F5')
            .layoutWeight(1)
            .onClick(() => {
              this.cancel();
            })

          Button(this.submitting ? '提交中…' : '确认')
            .fontSize(14)
            .fontColor('#FFFFFF')
            .backgroundColor(this.submitting || !this.done() ? '#99BBFF' : '#1677FF')
            .layoutWeight(1)
            .margin({ left: 10 })
            .enabled(!this.submitting && this.done())
            .onClick(() => {
              this.confirm();
            })
        }
        .width('100%')
        .margin({ top: 14 })
      }
      .width(340)
      .padding(16)
      .backgroundColor(Color.White)
      .borderRadius(12)
    }
    .width('100%')
    .height('100%')
  }
}
