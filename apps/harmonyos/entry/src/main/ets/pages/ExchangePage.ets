/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { GameItem, GameCurrency, ExchangeRequest, ExchangeQuoteResult, ExchangeDoneResult } from '../model/DataModels';
import { GameService, PaymentService } from '../service/DataService';
import { LoadingView, ErrorView, NavBar, trimZeros } from '../common/Components';

/**
 * 兑换：选游戏 + 币种 + 方向 → 询价（仅展示）→ 买入/卖出（服务端重新定价）
 * direction='out' 时 platform_amount 承载的是【游戏币】数量，服务端复用了同一字段名
 */
@Entry
@Component
struct ExchangePage {
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
      this.errorMsg = msg.length > 0 ? msg : '加载游戏失败';
    } finally {
      this.isLoading = false;
    }
  }

  private currentCurrencies(): GameCurrency[] {
    for (let i = 0; i < this.games.length; i++) {
      if (this.games[i].id === this.gameId) {
        return this.games[i].currencies ?? [];
      }
    }
    return [];
  }

  private currentSymbol(): string {
    const list = this.currentCurrencies();
    for (let i = 0; i < list.length; i++) {
      if (list[i].id === this.currencyId) {
        return list[i].symbol;
      }
    }
    return '';
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

  private payload(amount: string): ExchangeRequest {
    return {
      game_id: this.gameId,
      currency_id: this.currencyId,
      direction: this.exchangeDirection,
      platform_amount: amount
    };
  }

  /** 只做格式正则校验，不做数值转换 */
  private amountOk(amount: string): boolean {
    return /^\d+(\.\d{1,8})?$/.test(amount);
  }

  async quoteNow(): Promise<void> {
    if (this.quoteBusy) {
      return;
    }
    const amount = this.amount.trim();
    this.stale();
    if (!amount) {
      this.amountError = '请输入兑换数量';
      return;
    }
    if (!this.amountOk(amount)) {
      this.amountError = '数量格式不正确，请输入数字';
      return;
    }
    if (!this.gameId || !this.currencyId) {
      this.errorMsg = '请先选择游戏与币种';
      return;
    }

    this.quoteBusy = true;
    try {
      this.quote = await PaymentService.quoteExchange(this.payload(amount));
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = msg.length > 0 ? msg : '询价失败';
    } finally {
      this.quoteBusy = false;
    }
  }

  async confirm(): Promise<void> {
    if (this.submitting) {
      return;
    }
    const amount = this.amount.trim();
    if (!this.quote) {
      this.errorMsg = '请先询价';
      return;
    }
    if (!amount) {
      this.amountError = '请输入兑换数量';
      return;
    }

    this.submitting = true;
    this.errorMsg = '';
    try {
      const req = this.payload(amount);
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
      this.errorMsg = msg.length > 0 ? msg : '兑换失败';
    } finally {
      this.submitting = false;
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
    Column() {
      NavBar({ title: '兑换' })

      if (this.isLoading) {
        LoadingView({ message: '加载游戏中...' })
      } else if (this.done) {
        Scroll() {
          Column() {
            Column() {
              Row() {
                Text('兑换成功')
                  .fontSize(16)
                  .fontWeight(FontWeight.Bold)
                  .layoutWeight(1)
                Text(this.done!.direction === 'in' ? '买入' : '卖出')
                  .fontSize(12)
                  .fontColor('#52C41A')
                  .backgroundColor('#F6FFED')
                  .borderRadius(4)
                  .padding({ left: 8, right: 8, top: 4, bottom: 4 })
              }
              .width('100%')

              this.resultRow('单号', this.done!.exchange_id)
              this.resultRow(
                this.done!.direction === 'in' ? '支付平台币' : '卖出游戏币',
                trimZeros(
                  this.done!.direction === 'in' ? this.done!.platform_amount : this.done!.game_amount
                )
              )
              this.resultRow(
                this.done!.direction === 'in' ? '到账游戏币（已扣点差）' : '到账平台币（已扣点差）',
                trimZeros(
                  this.done!.direction === 'in' ? this.done!.game_amount : this.done!.platform_amount
                )
              )
              this.resultRow('点差', trimZeros(this.done!.spread_fee))
              this.resultRow('汇率', this.done!.rate)
              this.resultRow('账户余额', trimZeros(this.done!.balance_after))

              Button('再兑换一笔')
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
        ErrorView({ message: this.errorMsg })
      } else if (this.games.length === 0) {
        ErrorView({ message: '暂无可兑换的游戏' })
      } else {
        Scroll() {
          Column() {
            Column() {
              Text('方向')
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              Row() {
                this.directionChip('买入（平台币 → 游戏币）', 'in')
                this.directionChip('卖出（游戏币 → 平台币）', 'out')
              }
              .width('100%')
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            Column() {
              Text('选择游戏')
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 8 })

              ForEach(this.games, (g: GameItem) => {
                Row() {
                  Text(g.name || g.slug || '未命名')
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
                  this.pickGame(g);
                })
              }, (g: GameItem) => g.id)

              Text('选择币种')
                .fontSize(15)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ top: 8, bottom: 8 })

              if (this.currentCurrencies().length === 0) {
                Text('该游戏暂无可兑换币种')
                  .fontSize(13)
                  .fontColor('#999999')
                  .width('100%')
              } else {
                ForEach(this.currentCurrencies(), (c: GameCurrency) => {
                  Row() {
                    Column() {
                      Text(`${c.name}（${c.symbol}）`)
                        .fontSize(14)
                        .fontColor('#333333')
                      Text(`1 平台币 = ${c.exchange_rate} ${c.symbol}`)
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
                    this.currencyId = c.id;
                    this.stale();
                  })
                }, (c: GameCurrency) => c.id)
              }
            }
            .width('100%')
            .padding(16)
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .margin({ left: 16, right: 16, top: 12 })

            Column() {
              Text(this.exchangeDirection === 'in' ? '平台币数量' : `游戏币数量（${this.currentSymbol()}）`)
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
                Text('卖出时此处填写的是游戏币数量，服务端按同一字段名接收')
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

              Button(this.quoteBusy ? '询价中...' : '询价')
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
                    this.exchangeDirection === 'in' ? '支付平台币' : '卖出游戏币',
                    trimZeros(this.quote!.platform_amount)
                  )
                  if (this.exchangeDirection === 'in') {
                    this.resultRow('兑换游戏币（扣点差前）', `${trimZeros(this.quote!.game_amount ?? '')} ${this.currentSymbol()}`)
                    this.resultRow('实际到账游戏币', `${trimZeros(this.quote!.actual_game_amount ?? '')} ${this.currentSymbol()}`)
                  } else {
                    this.resultRow('折合平台币（扣点差前）', trimZeros(this.quote!.platform_equivalent ?? ''))
                    this.resultRow('实际到账平台币', trimZeros(this.quote!.actual_platform_amount ?? ''))
                  }
                  this.resultRow('点差', trimZeros(this.quote!.spread_fee))
                  this.resultRow('汇率', this.quote!.rate)
                  this.resultRow('点差率', `${trimZeros(this.quote!.spread_pct)}%`)
                }
                .width('100%')
                .padding({ left: 12, right: 12, top: 4, bottom: 4 })
                .borderRadius(8)
                .backgroundColor('#F8F9FA')
                .margin({ top: 12 })

                Text('报价仅作展示，买入/卖出时服务端会重新定价。')
                  .fontSize(12)
                  .fontColor('#999999')
                  .width('100%')
                  .margin({ top: 8 })
              }

              Button(this.submitting
                ? '提交中...'
                : (this.exchangeDirection === 'in' ? '确认买入' : '确认卖出'))
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
