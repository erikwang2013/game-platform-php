/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * ⚠ **已知缺口，只留注记、本批不造功能**：本树**没有充值/提现订单列表**。
 * 服务端两条端点都已就绪且被两棵 web 树用着（`service/config/route.php:85` 的
 * `/deposit/orders`、`:95` 的 `/withdraw/orders`，C 端 web 的「钱包记录」页就是它们），
 * 本树只调 `/wallet/info` 与流水列表。要补＝新页面 + 分页 + 13 表词条（属新功能，不在此批）。
 */

import router from '@ohos.router';
import { WalletInfo, TransactionItem } from '../model/DataModels';
import { WalletService } from '../service/DataService';
import { LoadingView, EmptyView, ErrorView, BottomNav } from '../common/Components';
import { t, t1 } from '../i18n/Translations';

@Entry
@Component
struct WalletPage {
  @StorageLink('app_locale') localeCode: string = 'zh';
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
      this.errorMsg = msg.length > 0 ? msg : t('common.load_failed');
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
      return t('common.deposit');
    }
    if (type === 'withdraw') {
      return t('common.withdraw');
    }
    if (type === 'exchange_in') {
      return t('wallet.tx_exchange_in');
    }
    if (type === 'exchange_out') {
      return t('wallet.tx_exchange_out');
    }
    if (type === 'game_earn') {
      return t('wallet.tx_game_earn');
    }
    if (type === 'game_spend') {
      return t('wallet.tx_game_spend');
    }
    return type || '-';
  }

  private go(url: string): void {
    router.pushUrl({ url: url });
  }

  @Builder
  actionItem(label: string, url: string) {
    Column() {
      Text(label)
        .fontSize(14)
        .fontWeight(FontWeight.Medium)
        .fontColor('#1677FF')
    }
    .layoutWeight(1)
    .height(48)
    .justifyContent(FlexAlign.Center)
    .onClick(() => {
      this.go(url);
    })
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
        Text(t('wallet.title'))
          .fontSize(20)
          .fontWeight(FontWeight.Bold)
      }
      .width('100%')
      .padding({ left: 16, right: 16, top: 16, bottom: 8 })

      if (this.isLoading) {
        LoadingView({ message: t('wallet.loading') })
      } else if (this.errorMsg) {
        ErrorView({ message: this.errorMsg, onRetry: () => { this.loadAll(); } })
      } else {
        Scroll() {
          Column() {
            Column() {
              Text(t('wallet.available_balance'))
                .fontSize(13)
                .fontColor('#E6F0FF')
              Text(this.wallet?.balance ?? '0.0000')
                .fontSize(32)
                .fontWeight(FontWeight.Bold)
                .fontColor('#FFFFFF')
                .margin({ top: 6 })
              Text(t1('wallet.frozen', 'amount', this.wallet?.frozen_balance ?? '0.0000'))
                .fontSize(12)
                .fontColor('#E6F0FF')
                .margin({ top: 4 })

              Row() {
                Column() {
                  Text(t('wallet.total_earned'))
                    .fontSize(11)
                    .fontColor('#E6F0FF')
                  Text(this.wallet?.total_earned ?? '0.0000')
                    .fontSize(14)
                    .fontColor('#FFFFFF')
                    .margin({ top: 2 })
                }
                .layoutWeight(1)
                Column() {
                  Text(t('wallet.total_spent'))
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

            Row() {
              this.actionItem(t('common.deposit'), 'pages/DepositPage')
              this.actionItem(t('common.withdraw'), 'pages/WithdrawPage')
              this.actionItem(t('common.exchange'), 'pages/ExchangePage')
            }
            .width('100%')
            .padding({ top: 6, bottom: 6 })
            .margin({ left: 16, right: 16, top: 12 })
            .borderRadius(12)
            .backgroundColor('#FFFFFF')

            Text(t('wallet.transactions'))
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
              EmptyView({ message: t('wallet.no_transactions') })
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
                    Text(t1('wallet.balance_after', 'amount', tx.balance_after))
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
