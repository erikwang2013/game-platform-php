/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { PaymentMethodItem, DepositCreateRequest, DepositCreateResult } from '../model/DataModels';
import { PaymentService } from '../service/DataService';
import { LoadingView, ErrorView, NavBar, trimZeros, isZeroAmount } from '../common/Components';

/**
 * 充值：选支付方式 + 币种 + 金额 → 创建订单 → 展示收银台链接
 * 原生端没有浏览器弹窗，checkout_url 只做展示与复制，不尝试打开
 */
@Entry
@Component
struct DepositPage {
  @State methods: PaymentMethodItem[] = [];
  @State methodId: string = '';
  @State currency: string = 'USD';
  @State amount: string = '';
  @State amountError: string = '';
  @State errorMsg: string = '';
  @State isLoading: boolean = true;
  @State submitting: boolean = false;
  @State done: DepositCreateResult | null = null;

  aboutToAppear(): void {
    this.loadMethods();
  }

  async loadMethods(): Promise<void> {
    this.isLoading = true;
    this.errorMsg = '';
    try {
      this.methods = await PaymentService.methods();
      if (!this.methodId && this.methods.length > 0) {
        this.methodId = this.methods[0].id;
      }
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : '加载支付方式失败';
    } finally {
      this.isLoading = false;
    }
  }

  /** 金额精度预检：JPY/KRW 零小数，其余最多 2 位；只做正则校验，不做数值转换 */
  private amountOk(amount: string, currency: string): boolean {
    if (currency === 'JPY' || currency === 'KRW') {
      return /^\d+$/.test(amount);
    }
    return /^\d+(\.\d{1,2})?$/.test(amount);
  }

  /** 支付方式限额提示；max_amount 数值为 0 表示不限。仅展示，不做金额运算。 */
  private limitText(m: PaymentMethodItem): string {
    const max = isZeroAmount(m.max_amount) ? '不限' : trimZeros(m.max_amount);
    return `${trimZeros(m.min_amount)} ~ ${max}`;
  }

  async submit(): Promise<void> {
    if (this.submitting) {
      return;
    }
    const amount = this.amount.trim();
    this.amountError = '';
    this.errorMsg = '';
    if (!amount) {
      this.amountError = '请输入充值金额';
      return;
    }
    if (!this.amountOk(amount, this.currency)) {
      this.amountError = (this.currency === 'JPY' || this.currency === 'KRW')
        ? `${this.currency} 金额只能为整数`
        : '金额最多 2 位小数';
      return;
    }
    if (!this.methodId) {
      this.errorMsg = '请选择支付方式';
      return;
    }

    this.submitting = true;
    try {
      const req: DepositCreateRequest = {
        amount: amount,
        currency: this.currency,
        payment_method_id: this.methodId
      };
      this.done = await PaymentService.createDeposit(req);
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : '创建充值订单失败';
    } finally {
      this.submitting = false;
    }
  }

  private reset(): void {
    this.done = null;
    this.amount = '';
    this.amountError = '';
    this.errorMsg = '';
  }

  @Builder
  currencyChip(code: string) {
    Text(code)
      .fontSize(13)
      .fontColor(this.currency === code ? '#FFFFFF' : '#333333')
      .backgroundColor(this.currency === code ? '#1677FF' : '#F5F5F5')
      .borderRadius(6)
      .padding({ top: 10, bottom: 10 })
      .textAlign(TextAlign.Center)
      .layoutWeight(1)
      .margin({ left: 3, right: 3 })
      .onClick(() => {
        this.currency = code;
        this.amountError = '';
      })
  }

  build() {
    Column() {
      NavBar({ title: '充值' })

      if (this.isLoading) {
        LoadingView({ message: '加载支付方式中...' })
      } else if (this.done) {
        Scroll() {
          Column() {
            Column() {
              Row() {
                Text('订单已创建')
                  .fontSize(16)
                  .fontWeight(FontWeight.Bold)
                  .layoutWeight(1)
                Text('待支付')
                  .fontSize(12)
                  .fontColor('#FA8C16')
                  .backgroundColor('#FFF7E6')
                  .borderRadius(4)
                  .padding({ left: 8, right: 8, top: 4, bottom: 4 })
              }
              .width('100%')

              this.resultRow('订单号', this.done!.order_no)
              this.resultRow('充值金额', `${trimZeros(this.done!.amount)} ${this.currency}`)
              this.resultRow('到账平台币', trimZeros(this.done!.platform_amount))
              if (this.done!.expires_at) {
                this.resultRow('支付截止', this.done!.expires_at)
              }

              Text('支付链接')
                .fontSize(13)
                .fontColor('#999999')
                .width('100%')
                .margin({ top: 16, bottom: 6 })

              Text(this.done!.checkout_url || '（网关未返回支付链接）')
                .fontSize(13)
                .fontColor('#1677FF')
                .width('100%')
                .padding(12)
                .borderRadius(8)
                .backgroundColor('#F5F5F5')
                .copyOption(CopyOptions.LocalDevice)

              Text('本应用无法直接打开浏览器，请长按上方链接复制，再到浏览器中完成支付。')
                .fontSize(12)
                .fontColor('#999999')
                .width('100%')
                .margin({ top: 8 })

              Button('再充一笔')
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
      } else if (this.methods.length === 0) {
        ErrorView({ message: this.errorMsg || '暂无可用支付方式' })
      } else {
        Scroll() {
          Column() {
            Column() {
              Text('支付方式')
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              ForEach(this.methods, (m: PaymentMethodItem) => {
                Row() {
                  Column() {
                    Text(m.name || m.provider)
                      .fontSize(15)
                      .fontWeight(FontWeight.Medium)
                      .fontColor('#333333')
                    Text(this.limitText(m))
                      .fontSize(12)
                      .fontColor('#999999')
                      .margin({ top: 2 })
                  }
                  .alignItems(HorizontalAlign.Start)
                  .layoutWeight(1)

                  if (this.methodId === m.id) {
                    Text('✓')
                      .fontSize(16)
                      .fontColor('#1677FF')
                      .fontWeight(FontWeight.Bold)
                  }
                }
                .width('100%')
                .padding(12)
                .margin({ bottom: 8 })
                .borderRadius(8)
                .backgroundColor(this.methodId === m.id ? '#E6F0FF' : '#F8F9FA')
                .border({
                  width: 1,
                  color: this.methodId === m.id ? '#1677FF' : '#F8F9FA'
                })
                .onClick(() => {
                  this.methodId = m.id;
                  this.errorMsg = '';
                })
              }, (m: PaymentMethodItem) => m.id)
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            Column() {
              Text('币种')
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              Row() {
                this.currencyChip('USD')
                this.currencyChip('CNY')
                this.currencyChip('EUR')
                this.currencyChip('JPY')
              }
              .width('100%')

              Row() {
                this.currencyChip('KRW')
                this.currencyChip('GBP')
                this.currencyChip('BRL')
                this.currencyChip('INR')
              }
              .width('100%')
              .margin({ top: 6 })

              Text('币种需与所选支付方式一致，否则服务端会拒绝下单')
                .fontSize(12)
                .fontColor('#999999')
                .width('100%')
                .margin({ top: 8 })
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            Column() {
              Text(`金额（${this.currency}）`)
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              TextInput({ placeholder: this.currency === 'JPY' || this.currency === 'KRW' ? '整数金额' : '0.00', text: this.amount })
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

              if (this.errorMsg) {
                Text(this.errorMsg)
                  .fontSize(12)
                  .fontColor('#FF4D4F')
                  .width('100%')
                  .margin({ top: 6 })
              }

              Button(this.submitting ? '提交中...' : '创建充值订单')
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
}
