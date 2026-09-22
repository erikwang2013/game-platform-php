/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { WithdrawApplyRequest, WithdrawApplyResult } from '../model/DataModels';
import { WalletService, PaymentService } from '../service/DataService';
import { NavBar, trimZeros } from '../common/Components';

/** 提现方式白名单（WithdrawController 的 in: paypal,bank,crypto） */
const METHOD_OPTIONS: string[] = ['paypal', 'bank', 'crypto'];

/**
 * 提现：方式 + 平台币金额 + 收款信息 → 申请 → 展示手续费/实际到账/新余额
 * 金额只做正则校验，不做数值转换；限额由服务端判定（400/403/429/503 原样展示）
 */
@Entry
@Component
struct WithdrawPage {
  @State method: string = 'paypal';
  @State amount: string = '';
  @State accountInfo: string = '';
  @State amountError: string = '';
  @State accountError: string = '';
  @State errorMsg: string = '';
  @State submitting: boolean = false;
  @State done: WithdrawApplyResult | null = null;
  @State walletBalance: string = '';

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
      return '银行卡';
    }
    if (m === 'crypto') {
      return '加密货币';
    }
    return m;
  }

  private statusLabel(status: string): string {
    if (status === 'pending') {
      return '待审核';
    }
    if (status === 'reviewing') {
      return '审核中';
    }
    if (status === 'manual_review') {
      return '人工审核';
    }
    if (status === 'approved') {
      return '已通过';
    }
    if (status === 'processing') {
      return '处理中';
    }
    if (status === 'completed') {
      return '已完成';
    }
    if (status === 'paid') {
      return '已打款';
    }
    if (status === 'rejected') {
      return '已驳回';
    }
    if (status === 'cancelled') {
      return '已取消';
    }
    if (status === 'failed') {
      return '失败';
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

  async submit(): Promise<void> {
    if (this.submitting) {
      return;
    }
    const amount = this.amount.trim();
    const accountInfo = this.accountInfo.trim();
    this.amountError = '';
    this.accountError = '';
    this.errorMsg = '';

    if (!amount) {
      this.amountError = '请输入提现金额';
      return;
    }
    // 平台币 4 位小数（与钱包余额精度一致），纯正则校验
    if (!/^\d+(\.\d{1,4})?$/.test(amount)) {
      this.amountError = '金额格式不正确，最多 4 位小数';
      return;
    }
    if (!accountInfo) {
      this.accountError = '请填写收款账号信息';
      return;
    }

    this.submitting = true;
    try {
      const req: WithdrawApplyRequest = {
        platform_amount: amount,
        method: this.method,
        account_info: accountInfo
      };
      this.done = await PaymentService.applyWithdraw(req);
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : '提现申请失败';
    } finally {
      this.submitting = false;
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
    Column() {
      NavBar({ title: '提现' })

      if (this.done) {
        Scroll() {
          Column() {
            Column() {
              Row() {
                Text('提现申请已提交')
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

              this.resultRow('订单号', this.done!.order_no)
              this.resultRow('提现金额', `${trimZeros(this.done!.platform_amount)} 平台币`)
              this.resultRow('手续费', trimZeros(this.done!.fee))
              this.resultRow('实际到账', trimZeros(this.done!.actual_amount))
              this.resultRow('账户余额', trimZeros(this.done!.balance_after))
              if (this.done!.created_at) {
                this.resultRow('提交时间', this.done!.created_at)
              }

              Button('再提一笔')
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
              Text('提现方式')
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
                Text('提现金额（平台币）')
                  .fontSize(15)
                  .fontWeight(FontWeight.Medium)
                  .layoutWeight(1)
                if (this.walletBalance) {
                  Text(`可用 ${trimZeros(this.walletBalance)}`)
                    .fontSize(12)
                    .fontColor('#999999')
                }
              }
              .width('100%')
              .margin({ bottom: 8 })

              TextInput({ placeholder: '最低 0.0001', text: this.amount })
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

              Text('收款账号信息')
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ top: 16, bottom: 8 })

              TextArea({ placeholder: 'PayPal 邮箱 / 银行卡号与开户行 / 钱包地址', text: this.accountInfo })
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

              Button(this.submitting ? '提交中...' : '提交提现申请')
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

              Text('提现需人工/风控审核，限额与手续费以服务端返回为准。')
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
