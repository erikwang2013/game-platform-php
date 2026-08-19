/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { WalletInfo, TransactionItem } from '../model/DataModels';
import { WalletService } from '../service/DataService';
import { LoadingView, EmptyView, ErrorView, BottomNav } from '../common/Components';

@Entry
@Component
struct WalletPage {
  @State wallet: WalletInfo | null = null;
  @State transactions: TransactionItem[] = [];
  @State isLoading: boolean = true;
  @State txLoading: boolean = true;
  @State errorMsg: string = '';

  aboutToAppear(): void {
    this.loadAll();
  }

  async loadAll(): Promise<void> {
    await Promise.all([this.loadWallet(), this.loadTransactions()]);
  }

  async loadWallet(): Promise<void> {
    this.isLoading = true;
    this.errorMsg = '';
    try {
      this.wallet = await WalletService.info();
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

  async loadTransactions(): Promise<void> {
    this.txLoading = true;
    try {
      this.transactions = await WalletService.transactions(1);
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
    } finally {
      this.txLoading = false;
    }
  }

  private typeLabel(type: string): string {
    if (type === 'deposit') {
      return '充值';
    }
    if (type === 'withdraw') {
      return '提现';
    }
    if (type === 'exchange_in') {
      return '兑入';
    }
    if (type === 'exchange_out') {
      return '兑出';
    }
    if (type === 'game_earn') {
      return '游戏赢得';
    }
    if (type === 'game_spend') {
      return '游戏消费';
    }
    return type || '-';
  }

  private typeColor(type: string): string {
    if (type === 'deposit' || type === 'exchange_in' || type === 'game_earn') {
      return '#52C41A';
    }
    if (type === 'withdraw' || type === 'exchange_out' || type === 'game_spend') {
      return '#FF4D4F';
    }
    return '#1677FF';
  }

  build() {
    Column() {
      Row() {
        Text('我的钱包')
          .fontSize(20)
          .fontWeight(FontWeight.Bold)
      }
      .width('100%')
      .padding({ left: 16, right: 16, top: 16, bottom: 8 })

      if (this.isLoading) {
        LoadingView({ message: '加载钱包中...' })
      } else if (this.errorMsg) {
        ErrorView({ message: this.errorMsg })
      } else {
        Scroll() {
          Column() {
            Column() {
              Text('可用余额')
                .fontSize(13)
                .fontColor('#E6F0FF')
              Text(this.wallet?.balance ?? '0.0000')
                .fontSize(32)
                .fontWeight(FontWeight.Bold)
                .fontColor('#FFFFFF')
                .margin({ top: 6 })
              Text(`冻结 ${this.wallet?.frozen_balance ?? '0.0000'}`)
                .fontSize(12)
                .fontColor('#E6F0FF')
                .margin({ top: 4 })

              Row() {
                Column() {
                  Text('累计获得')
                    .fontSize(11)
                    .fontColor('#E6F0FF')
                  Text(this.wallet?.total_earned ?? '0.0000')
                    .fontSize(14)
                    .fontColor('#FFFFFF')
                    .margin({ top: 2 })
                }
                .layoutWeight(1)
                Column() {
                  Text('累计支出')
                    .fontSize(11)
                    .fontColor('#E6F0FF')
                  Text(this.wallet?.total_spent ?? '0.0000')
                    .fontSize(14)
                    .fontColor('#FFFFFF')
                    .margin({ top: 2 })
                }
                .layoutWeight(1)
              }
              .width('100%')
              .margin({ top: 16 })
            }
            .width('100%')
            .padding(20)
            .borderRadius(16)
            .backgroundColor('#1677FF')
            .margin({ left: 16, right: 16, top: 8 })

            Text('流水记录')
              .fontSize(16)
              .fontWeight(FontWeight.Bold)
              .width('100%')
              .padding({ left: 16, right: 16, top: 20, bottom: 8 })

            if (this.txLoading) {
              LoadingProgress()
                .width(28)
                .height(28)
                .color('#1677FF')
                .margin({ top: 16 })
            } else if (this.transactions.length === 0) {
              EmptyView({ message: '暂无流水' })
            } else {
              ForEach(this.transactions, (tx: TransactionItem) => {
                Row() {
                  Column() {
                    Text(this.typeLabel(tx.type))
                      .fontSize(15)
                      .fontWeight(FontWeight.Medium)
                    Text(tx.created_at || '')
                      .fontSize(11)
                      .fontColor('#999999')
                      .margin({ top: 2 })
                    if (tx.remark) {
                      Text(tx.remark)
                        .fontSize(11)
                        .fontColor('#999999')
                        .maxLines(1)
                        .textOverflow({ overflow: TextOverflow.Ellipsis })
                    }
                  }
                  .alignItems(HorizontalAlign.Start)
                  .layoutWeight(1)

                  Column() {
                    Text(tx.amount)
                      .fontSize(16)
                      .fontWeight(FontWeight.Bold)
                      .fontColor(this.typeColor(tx.type))
                    Text(`余额 ${tx.balance_after}`)
                      .fontSize(11)
                      .fontColor('#999999')
                      .margin({ top: 2 })
                  }
                  .alignItems(HorizontalAlign.End)
                }
                .width('100%')
                .padding(12)
                .margin({ left: 16, right: 16, bottom: 8 })
                .borderRadius(8)
                .backgroundColor('#FFFFFF')
              }, (tx: TransactionItem) => tx.id)
            }
          }
          .width('100%')
          .padding({ bottom: 16 })
        }
        .layoutWeight(1)
      }

      BottomNav({ current: 'wallet' })
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}
