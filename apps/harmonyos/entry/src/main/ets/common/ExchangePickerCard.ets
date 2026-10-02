/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { GameItem, GameCurrency } from '../model/DataModels';
import { t } from '../i18n/Translations';

/**
 * 兑换页的「选游戏 + 选币种」卡 —— 从 `pages/ExchangePage.ets`（565 行）里整块搬出来的。
 *
 * ⚠ 搬不是重写：两层 ForEach（含各自的 keyGenerator）、选中态配色、✓ 标记、以及
 * 「点选后必须 stale() 作废旧报价」的时机都逐字保留。三条与搬之前**完全一致**：
 *   1. 卡片自己不持有状态 —— 落值仍在页面里（`pickGame` 会连带重置币种，`stale()` 页面语义）；
 *   2. 币种列表由页面算好传进来（原来页面里两次调用 `currentCurrencies()`，现在是一份）；
 *   3. 回调沿用本树既有写法（`onXxx?:` + 调用点判空，见 `common/CaptchaDialog.ets`），
 *      不写成带默认值的成员 —— 那种写法本版编译器没有先例。
 */
@Component
export struct ExchangePickerCard {
  @StorageLink('app_locale') localeCode: string = 'zh';
  @Prop games: GameItem[] = [];
  @Prop gameId: string = '';
  @Prop currencies: GameCurrency[] = [];
  @Prop currencyId: string = '';
  /** 选中游戏：页面落 gameId + 该游戏首个币种，并 stale() */
  onPickGame?: (game: GameItem) => void;
  /** 选中币种：页面落 currencyId 并 stale() */
  onPickCurrency?: (id: string) => void;

  build() {
    Column() {
      Text(t('exchange.select_game'))
        .fontSize(15)
        .fontWeight(FontWeight.Medium)
        .width('100%')
        .margin({ bottom: 8 })

      ForEach(this.games, (g: GameItem) => {
        Row() {
          Text(g.name || g.slug || t('common.unnamed'))
            .fontSize(14)
            .fontColor('#333333')
            .layoutWeight(1)
          if (this.gameId === g.id) {
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
        .backgroundColor(this.gameId === g.id ? '#E6F0FF' : '#F8F9FA')
        .onClick(() => {
          if (this.onPickGame) {
            this.onPickGame(g);
          }
        })
      }, (g: GameItem) => g.id)

      Text(t('exchange.select_currency'))
        .fontSize(15)
        .fontWeight(FontWeight.Medium)
        .width('100%')
        .margin({ top: 8, bottom: 8 })

      if (this.currencies.length === 0) {
        Text(t('exchange.no_currencies'))
          .fontSize(13)
          .fontColor('#999999')
          .width('100%')
      } else {
        ForEach(this.currencies, (c: GameCurrency) => {
          Row() {
            Column() {
              Text(t('exchange.currency_line').replace('{name}', c.name).replace('{symbol}', c.symbol))
                .fontSize(14)
                .fontColor('#333333')
              Text(t('common.rate_line').replace('{rate}', c.exchange_rate).replace('{symbol}', c.symbol))
                .fontSize(12)
                .fontColor('#999999')
                .margin({ top: 2 })
            }
            .alignItems(HorizontalAlign.Start)
            .layoutWeight(1)

            if (this.currencyId === c.id) {
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
          .backgroundColor(this.currencyId === c.id ? '#E6F0FF' : '#F8F9FA')
          .onClick(() => {
            if (this.onPickCurrency) {
              this.onPickCurrency(c.id);
            }
          })
        }, (c: GameCurrency) => c.id)
      }
    }
    .width('100%')
    .padding(16)
    .backgroundColor('#FFFFFF')
    .borderRadius(8)
    .margin({ left: 16, right: 16, top: 12 })
  }
}
