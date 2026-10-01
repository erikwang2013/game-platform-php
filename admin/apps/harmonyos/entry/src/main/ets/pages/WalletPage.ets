/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import router from '@ohos.router';
import { t, t1 } from '../i18n/Translations';

@Entry
@Component
struct WalletPage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State balance: string = '0.0000';
  @State frozenBalance: string = '0.0000';

  aboutToAppear(): void {
    this.loadWallet();
  }

  async loadWallet(): Promise<void> {
    // Load from API — simplified for now
    this.balance = '--';
  }

  build() {
    Column() {
      Row() {
        Button(t('common.back')).onClick(() => router.back())
        Blank()
      }.padding(16)

      Column() {
        Text(t('wallet.balance')).fontSize(14).fontColor('#666')
        Text(this.balance).fontSize(36).fontWeight(FontWeight.Bold).margin({ top: 8 })
        Text(t1('wallet.frozen', 'amount', this.frozenBalance)).fontSize(12).fontColor('#999')
      }
      .width('90%')
      .padding(24)
      .borderRadius(16)
      .backgroundColor('#1677FF')
      .margin({ top: 16 })

      Grid() {
        GridItem() {
          Column() {
            Text('💰').fontSize(28)
            Text(t('wallet.recharge')).fontSize(14)
          }.onClick(() => {})
        }
        GridItem() {
          Column() {
            Text('🔄').fontSize(28)
            Text(t('wallet.exchange')).fontSize(14)
          }.onClick(() => {})
        }
        GridItem() {
          Column() {
            Text('💸').fontSize(28)
            Text(t('wallet.withdraw')).fontSize(14)
          }.onClick(() => {})
        }
        GridItem() {
          Column() {
            Text('📋').fontSize(28)
            Text(t('wallet.transactions')).fontSize(14)
          }.onClick(() => {})
        }
      }
      .columnsTemplate('1fr 1fr 1fr 1fr')
      .width('90%')
      .margin({ top: 24 })
    }
    .width('100%')
    .height('100%')
  }
}
