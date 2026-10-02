/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { GameItem, ExchangeQuoteResult, ExchangeDoneResult, CaptchaClick } from '../model/DataModels';
import { GameService, PaymentService } from '../service/DataService';
import { LoadingView, ErrorView, NavBar, trimZeros } from '../common/Components';
import { CaptchaDialog } from '../common/CaptchaDialog';
import { ExchangePickerCard } from '../common/ExchangePickerCard';
import { amountFormatOk, exchangeCurrencies, exchangePayload, exchangeSymbol } from '../common/ExchangeLogic';
import { t, t1 } from '../i18n/Translations';

/**
 * 兑换：选游戏 + 币种 + 方向 → 询价（仅展示）→ 买入/卖出（服务端重新定价）
 * direction='out' 时 platform_amount 承载的是【游戏币】数量，服务端复用了同一字段名
 */
@Entry
@Component
struct ExchangePage {
  @StorageLink('app_locale') localeCode: string = 'zh';
  @State games: GameItem[] = [];
  @State gameId: string = '';
  @State currencyId: string = '';
  /** 不能命名为 direction：ArkUI CustomComponent 已有同名布局属性 */
  @State exchangeDirection: string = 'in';
  @State amount: string = '';
  @State amountError: string = '';
  @State errorMsg: string = '';
  @State isLoading: boolean = true;
  @State quoteBusy: boolean = false;
  @State quote: ExchangeQuoteResult | null = null;
  @State submitting: boolean = false;
  @State done: ExchangeDoneResult | null = null;
  /** 验证码弹框开关：仅卖出需要（服务端刻意没给买入加） */
  @State captchaOpen: boolean = false;

  aboutToAppear(): void {
    this.loadGames();
  }

  async loadGames(): Promise<void> {
    this.isLoading = true;
    this.errorMsg = '';
    try {
      this.games = await GameService.list();
      if (!this.gameId && this.games.length > 0) {
        this.pickGame(this.games[0]);
      }
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : t('exchange.load_games_failed');
    } finally {
      this.isLoading = false;
    }
  }

  private pickGame(game: GameItem): void {
    this.gameId = game.id;
    const list = game.currencies ?? [];
    this.currencyId = list.length > 0 ? list[0].id : '';
    this.stale();
  }

  /** 数量/方向/游戏变化后旧报价作废：不允许拿过期价格确认 */
  private stale(): void {
    this.quote = null;
    this.errorMsg = '';
    this.amountError = '';
  }

  async quoteNow(): Promise<void> {
    if (this.quoteBusy) {
      return;
    }
    const amount = this.amount.trim();
    this.stale();
    if (!amount) {
      this.amountError = t('exchange.amount_required');
      return;
    }
    if (!amountFormatOk(amount)) {
      this.amountError = t('exchange.amount_invalid');
      return;
    }
    if (!this.gameId || !this.currencyId) {
      this.errorMsg = t('exchange.select_first');
      return;
    }

    this.quoteBusy = true;
    try {
      this.quote = await PaymentService.quoteExchange(
        exchangePayload(this.gameId, this.currencyId, this.exchangeDirection, amount)
      );
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : t('exchange.quote_failed');
    } finally {
      this.quoteBusy = false;
    }
  }

  /**
   * 本地校验 → 卖出先弹验证码框，买入后端无验证码直接提交。
   * 询价与成交必须是同一份 quote，弹框期间报价保持不动
   */
  confirm(): void {
    if (this.submitting) {
      return;
    }
    const amount = this.amount.trim();
    if (!this.quote) {
      this.errorMsg = t('exchange.quote_first');
      return;
    }
    if (!amount) {
      this.amountError = t('exchange.amount_required');
      return;
    }

    this.errorMsg = '';
    if (this.exchangeDirection !== 'out') {
      this.doConfirm();
      return;
    }
    this.captchaOpen = true;
  }

  /** 验证码确认后真正成交（买入无验证码参数，卖出带 captcha_key + clicks） */
  async doConfirm(captchaKey?: string, clicks?: CaptchaClick[]): Promise<void> {
    if (this.submitting) {
      return;
    }
    const amount = this.amount.trim();
    if (!this.quote) {
      this.errorMsg = t('exchange.quote_first');
      return;
    }
    if (!amount) {
      this.amountError = t('exchange.amount_required');
      return;
    }

    this.submitting = true;
    this.errorMsg = '';
    try {
      const req = exchangePayload(this.gameId, this.currencyId, this.exchangeDirection, amount, captchaKey, clicks);
      this.done = this.exchangeDirection === 'in'
        ? await PaymentService.buyExchange(req)
        : await PaymentService.sellExchange(req);
      this.quote = null;
      this.amount = '';
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : t('exchange.failed');
    } finally {
      this.submitting = false;
      // 无论成败都关框：成功露出结果页；失败即作废本次挑战，服务端 message 显示在原错误位，下次开框重取新图
      this.captchaOpen = false;
    }
  }

  private reset(): void {
    this.done = null;
    this.amount = '';
    this.amountError = '';
    this.errorMsg = '';
    this.quote = null;
  }

  @Builder
  resultRow(label: string, value: string) {
    Row() {
      Text(label)
        .fontSize(13)
        .fontColor('#999999')
        .width(120)
      Text(value || '-')
        .fontSize(14)
        .fontColor('#333333')
        .layoutWeight(1)
    }
    .width('100%')
    .padding({ top: 8, bottom: 8 })
  }

  @Builder
  directionChip(label: string, value: string) {
    Text(label)
      .fontSize(13)
      .fontColor(this.exchangeDirection === value ? '#FFFFFF' : '#333333')
      .backgroundColor(this.exchangeDirection === value ? '#1677FF' : '#F5F5F5')
      .borderRadius(6)
      .padding({ top: 10, bottom: 10 })
      .textAlign(TextAlign.Center)
      .layoutWeight(1)
      .margin({ left: 3, right: 3 })
      .onClick(() => {
        if (this.exchangeDirection !== value) {
          this.exchangeDirection = value;
          this.stale();
        }
      })
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
            this.doConfirm(key, clicks);
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
      NavBar({ title: t('common.exchange') })

      if (this.isLoading) {
        LoadingView({ message: t('hall.loading') })
      } else if (this.done) {
        Scroll() {
          Column() {
            Column() {
              Row() {
                Text(t('exchange.success'))
                  .fontSize(16)
                  .fontWeight(FontWeight.Bold)
                  .layoutWeight(1)
                Text(this.done!.direction === 'in' ? t('exchange.buy') : t('exchange.sell'))
                  .fontSize(12)
                  .fontColor('#52C41A')
                  .backgroundColor('#F6FFED')
                  .borderRadius(4)
                  .padding({ left: 8, right: 8, top: 4, bottom: 4 })
              }
              .width('100%')

              this.resultRow(t('exchange.id_label'), this.done!.exchange_id)
              this.resultRow(
                this.done!.direction === 'in' ? t('exchange.pay_platform') : t('exchange.sell_game'),
                trimZeros(
                  this.done!.direction === 'in' ? this.done!.platform_amount : this.done!.game_amount
                )
              )
              this.resultRow(
                this.done!.direction === 'in' ? t('exchange.receive_game_after_spread') : t('exchange.receive_platform_after_spread'),
                trimZeros(
                  this.done!.direction === 'in' ? this.done!.game_amount : this.done!.platform_amount
                )
              )
              this.resultRow(t('exchange.spread'), trimZeros(this.done!.spread_fee))
              this.resultRow(t('exchange.rate'), this.done!.rate)
              this.resultRow(t('common.balance_after'), trimZeros(this.done!.balance_after))

              Button(t('exchange.again'))
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
      } else if (this.errorMsg && this.games.length === 0) {
        ErrorView({ message: this.errorMsg, onRetry: () => { this.loadGames(); } })
      } else if (this.games.length === 0) {
        ErrorView({ message: t('exchange.no_games'), onRetry: () => { this.loadGames(); } })
      } else {
        Scroll() {
          Column() {
            Column() {
              Text(t('exchange.direction'))
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              Row() {
                this.directionChip(t('exchange.buy_full'), 'in')
                this.directionChip(t('exchange.sell_full'), 'out')
              }
              .width('100%')
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            // 选游戏/选币种卡搬到了 common/ExchangePickerCard.ets
            //（本页 565 行拆分，搬的边界与三条等价性说明见该文件头）
            ExchangePickerCard({
              games: this.games,
              gameId: this.gameId,
              currencies: exchangeCurrencies(this.games, this.gameId),
              currencyId: this.currencyId,
              onPickGame: (g: GameItem) => {
                this.pickGame(g);
              },
              onPickCurrency: (id: string) => {
                this.currencyId = id;
                this.stale();
              }
            })

            Column() {
              Text(this.exchangeDirection === 'in'
                  ? t('exchange.platform_amount_label')
                  : t1('exchange.game_amount_label', 'symbol', exchangeSymbol(this.games, this.gameId, this.currencyId)))
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              TextInput({ placeholder: '0.00', text: this.amount })
                .height(44)
                .fontSize(14)
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .onChange((v: string) => {
                  this.amount = v;
                  this.stale();
                })

              if (this.exchangeDirection === 'out') {
                Text(t('exchange.sell_amount_hint'))
                  .fontSize(12)
                  .fontColor('#999999')
                  .width('100%')
                  .margin({ top: 6 })
              }

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

              Button(this.quoteBusy ? t('exchange.quoting') : t('exchange.quote'))
                .width('100%')
                .height(44)
                .fontSize(15)
                .fontColor('#1677FF')
                .backgroundColor('#E6F0FF')
                .borderRadius(22)
                .margin({ top: 16 })
                .enabled(!this.quoteBusy)
                .onClick(() => {
                  this.quoteNow();
                })

              if (this.quote) {
                Column() {
                  this.resultRow(
                    this.exchangeDirection === 'in' ? t('exchange.pay_platform') : t('exchange.sell_game'),
                    trimZeros(this.quote!.platform_amount)
                  )
                  if (this.exchangeDirection === 'in') {
                    this.resultRow(t('exchange.game_before_spread'), `${trimZeros(this.quote!.game_amount ?? '')} ${exchangeSymbol(this.games, this.gameId, this.currencyId)}`)
                    this.resultRow(t('exchange.actual_game'), `${trimZeros(this.quote!.actual_game_amount ?? '')} ${exchangeSymbol(this.games, this.gameId, this.currencyId)}`)
                  } else {
                    this.resultRow(t('exchange.platform_before_spread'), trimZeros(this.quote!.platform_equivalent ?? ''))
                    this.resultRow(t('exchange.actual_platform'), trimZeros(this.quote!.actual_platform_amount ?? ''))
                  }
                  this.resultRow(t('exchange.spread'), trimZeros(this.quote!.spread_fee))
                  this.resultRow(t('exchange.rate'), this.quote!.rate)
                  this.resultRow(t('exchange.spread_pct'), `${trimZeros(this.quote!.spread_pct)}%`)
                }
                .width('100%')
                .padding({ left: 12, right: 12, top: 4, bottom: 4 })
                .borderRadius(8)
                .backgroundColor('#F8F9FA')
                .margin({ top: 12 })

                Text(t('exchange.quote_hint'))
                  .fontSize(12)
                  .fontColor('#999999')
                  .width('100%')
                  .margin({ top: 8 })
              }

              Button(this.submitting
                ? t('common.submitting')
                : (this.exchangeDirection === 'in' ? t('exchange.confirm_buy') : t('exchange.confirm_sell')))
                .width('100%')
                .height(48)
                .fontSize(16)
                .fontColor('#FFFFFF')
                .backgroundColor(this.submitting ? '#99BBFF' : '#1677FF')
                .borderRadius(24)
                .margin({ top: 16 })
                .enabled(!this.submitting && this.quote !== null)
                .onClick(() => {
                  this.confirm();
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
}
