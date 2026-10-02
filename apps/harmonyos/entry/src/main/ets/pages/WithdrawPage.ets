/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { WithdrawApplyRequest, WithdrawApplyResult, CaptchaClick } from '../model/DataModels';
import { WalletService, PaymentService } from '../service/DataService';
import { NavBar, trimZeros } from '../common/Components';
import { CaptchaDialog } from '../common/CaptchaDialog';
import { t, t1 } from '../i18n/Translations';

/** 提现方式白名单（WithdrawController 的 in: paypal,bank,crypto） */
const METHOD_OPTIONS: string[] = ['paypal', 'bank', 'crypto'];

/**
 * 提现：方式 + 平台币金额 + 收款信息 → 申请 → 展示手续费/实际到账/新余额
 * 金额只做正则校验，不做数值转换；限额由服务端判定（400/403/429/503 原样展示）
 */
@Entry
@Component
struct WithdrawPage {
  @StorageLink('app_locale') localeCode: string = 'zh';
  @State method: string = 'paypal';
  @State amount: string = '';
  @State accountInfo: string = '';
  @State amountError: string = '';
  @State accountError: string = '';
  @State errorMsg: string = '';
  @State submitting: boolean = false;
  @State done: WithdrawApplyResult | null = null;
  @State walletBalance: string = '';
  /** 验证码弹框开关：提现申请由服务端强制验证码 */
  @State captchaOpen: boolean = false;

  aboutToAppear(): void {
    this.loadBalance();
  }

  async loadBalance(): Promise<void> {
    try {
      const info = await WalletService.info();
      this.walletBalance = info.balance;
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // 余额仅作展示，取不到不影响提交
      this.walletBalance = '';
    }
  }

  private methodLabel(m: string): string {
    if (m === 'paypal') {
      return 'PayPal';
    }
    if (m === 'bank') {
      return t('withdraw.method_bank');
    }
    if (m === 'crypto') {
      return t('withdraw.method_crypto');
    }
    return m;
  }

  private statusLabel(status: string): string {
    if (status === 'pending') {
      return t('withdraw.status_pending');
    }
    if (status === 'reviewing') {
      return t('withdraw.status_reviewing');
    }
    if (status === 'manual_review') {
      return t('withdraw.status_manual_review');
    }
    if (status === 'approved') {
      return t('withdraw.status_approved');
    }
    if (status === 'processing') {
      return t('withdraw.status_processing');
    }
    if (status === 'completed') {
      return t('withdraw.status_completed');
    }
    if (status === 'paid') {
      return t('withdraw.status_paid');
    }
    if (status === 'rejected') {
      return t('withdraw.status_rejected');
    }
    if (status === 'cancelled') {
      return t('withdraw.status_cancelled');
    }
    if (status === 'failed') {
      return t('withdraw.status_failed');
    }
    return status || '-';
  }

  private statusColor(status: string): string {
    if (status === 'rejected' || status === 'failed' || status === 'cancelled') {
      return '#FF4D4F';
    }
    if (status === 'completed' || status === 'paid' || status === 'approved') {
      return '#52C41A';
    }
    return '#FA8C16';
  }

  /**
   * 本地校验 → 开验证码框。
   * 提现是敏感操作，服务端强制点击验证码；校验不过就不该消耗一次取图
   */
  submit(): void {
    if (this.submitting) {
      return;
    }
    const amount = this.amount.trim();
    const accountInfo = this.accountInfo.trim();
    this.amountError = '';
    this.accountError = '';
    this.errorMsg = '';

    if (!amount) {
      this.amountError = t('withdraw.amount_required');
      return;
    }
    // 平台币 4 位小数（与钱包余额精度一致），纯正则校验
    if (!/^\d+(\.\d{1,4})?$/.test(amount)) {
      this.amountError = t('withdraw.amount_invalid');
      return;
    }
    if (!accountInfo) {
      this.accountError = t('withdraw.account_required');
      return;
    }

    this.captchaOpen = true;
  }

  /** 验证码确认后真正提交：captcha_key + clicks 与原字段同级 */
  async doSubmit(captchaKey: string, clicks: CaptchaClick[]): Promise<void> {
    if (this.submitting) {
      return;
    }
    const amount = this.amount.trim();
    const accountInfo = this.accountInfo.trim();

    this.submitting = true;
    this.errorMsg = '';
    try {
      const req: WithdrawApplyRequest = {
        platform_amount: amount,
        method: this.method,
        account_info: accountInfo,
        captcha_key: captchaKey,
        clicks: clicks
      };
      this.done = await PaymentService.applyWithdraw(req);
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : t('withdraw.failed');
    } finally {
      this.submitting = false;
      // 无论成败都关框：成功露出结果页；失败即作废本次挑战，服务端 message 显示在原错误位，下次开框重取新图
      this.captchaOpen = false;
    }
  }

  private reset(): void {
    this.done = null;
    this.amount = '';
    this.accountInfo = '';
    this.amountError = '';
    this.accountError = '';
    this.errorMsg = '';
    this.loadBalance();
  }

  @Builder
  resultRow(label: string, value: string) {
    Row() {
      Text(label)
        .fontSize(13)
        .fontColor('#999999')
        .width(90)
      Text(value || '-')
        .fontSize(14)
        .fontColor('#333333')
        .layoutWeight(1)
    }
    .width('100%')
    .padding({ top: 8, bottom: 8 })
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      // 验证码弹框：遮罩与卡片是 Stack 里的兄弟节点，
      // 卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      if (this.captchaOpen) {
        CaptchaDialog({
          submitting: this.submitting,
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
    .backgroundColor('#F5F5F5')
  }

  @Builder
  mainView() {
    Column() {
      NavBar({ title: t('common.withdraw') })

      if (this.done) {
        Scroll() {
          Column() {
            Column() {
              Row() {
                Text(t('withdraw.submitted'))
                  .fontSize(16)
                  .fontWeight(FontWeight.Bold)
                  .layoutWeight(1)
                Text(this.statusLabel(this.done!.status))
                  .fontSize(12)
                  .fontColor(this.statusColor(this.done!.status))
                  .backgroundColor('#F5F5F5')
                  .borderRadius(4)
                  .padding({ left: 8, right: 8, top: 4, bottom: 4 })
              }
              .width('100%')

              this.resultRow(t('common.order_no'), this.done!.order_no)
              this.resultRow(t('withdraw.amount_label'), t1('common.platform_coin_amount', 'amount', trimZeros(this.done!.platform_amount)))
              this.resultRow(t('withdraw.fee'), trimZeros(this.done!.fee))
              this.resultRow(t('withdraw.actual_amount'), trimZeros(this.done!.actual_amount))
              this.resultRow(t('common.balance_after'), trimZeros(this.done!.balance_after))
              if (this.done!.created_at) {
                this.resultRow(t('withdraw.created_at'), this.done!.created_at)
              }

              Button(t('withdraw.again'))
                .width('100%')
                .height(44)
                .fontSize(15)
                .fontColor('#1677FF')
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .margin({ top: 20 })
                .onClick(() => {
                  this.reset();
                })
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })
          }
          .width('100%')
          .padding({ bottom: 24 })
        }
        .layoutWeight(1)
      } else {
        Scroll() {
          Column() {
            Column() {
              Text(t('withdraw.method_label'))
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              Row() {
                ForEach(METHOD_OPTIONS, (m: string) => {
                  Text(this.methodLabel(m))
                    .fontSize(13)
                    .fontColor(this.method === m ? '#FFFFFF' : '#333333')
                    .backgroundColor(this.method === m ? '#1677FF' : '#F5F5F5')
                    .borderRadius(6)
                    .padding({ top: 10, bottom: 10 })
                    .textAlign(TextAlign.Center)
                    .layoutWeight(1)
                    .margin({ left: 3, right: 3 })
                    .onClick(() => {
                      this.method = m;
                      this.errorMsg = '';
                    })
                }, (m: string) => m)
              }
              .width('100%')
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            Column() {
              Row() {
                Text(t('withdraw.amount_platform_label'))
                  .fontSize(15)
                  .fontWeight(FontWeight.Medium)
                  .layoutWeight(1)
                if (this.walletBalance) {
                  Text(t1('withdraw.available', 'amount', trimZeros(this.walletBalance)))
                    .fontSize(12)
                    .fontColor('#999999')
                }
              }
              .width('100%')
              .margin({ bottom: 8 })

              TextInput({ placeholder: t('withdraw.min_amount_hint'), text: this.amount })
                .height(44)
                .fontSize(14)
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .onChange((v: string) => {
                  this.amount = v;
                  this.amountError = '';
                  this.errorMsg = '';
                })

              if (this.amountError) {
                Text(this.amountError)
                  .fontSize(12)
                  .fontColor('#FF4D4F')
                  .width('100%')
                  .margin({ top: 6 })
              }

              Text(t('withdraw.account_info'))
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ top: 16, bottom: 8 })

              TextArea({ placeholder: t('withdraw.account_placeholder'), text: this.accountInfo })
                .height(96)
                .fontSize(14)
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .padding({ left: 12, right: 12, top: 10, bottom: 10 })
                .onChange((v: string) => {
                  this.accountInfo = v;
                  this.accountError = '';
                  this.errorMsg = '';
                })

              if (this.accountError) {
                Text(this.accountError)
                  .fontSize(12)
                  .fontColor('#FF4D4F')
                  .width('100%')
                  .margin({ top: 6 })
              }

              if (this.errorMsg) {
                Text(this.errorMsg)
                  .fontSize(12)
                  .fontColor('#FF4D4F')
                  .width('100%')
                  .margin({ top: 6 })
              }

              Button(this.submitting ? t('common.submitting') : t('withdraw.submit'))
                .width('100%')
                .height(48)
                .fontSize(16)
                .fontColor('#FFFFFF')
                .backgroundColor(this.submitting ? '#99BBFF' : '#1677FF')
                .borderRadius(24)
                .margin({ top: 16 })
                .enabled(!this.submitting)
                .onClick(() => {
                  this.submit();
                })

              Text(t('withdraw.review_hint'))
                .fontSize(12)
                .fontColor('#999999')
                .width('100%')
                .margin({ top: 8 })
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12, bottom: 24 })
          }
          .width('100%')
        }
        .layoutWeight(1)
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}
