/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { GameItem, GameCurrency } from '../model/DataModels';
import { GameService } from '../service/DataService';
import { LoadingView, ErrorView } from '../common/Components';

@Entry
@Component
struct GameDetailPage {
  @State gameId: string = '';
  @State game: GameItem | null = null;
  @State isLoading: boolean = true;
  @State launching: boolean = false;
  @State errorMsg: string = '';

  aboutToAppear(): void {
    const params = router.getParams() as Record<string, Object>;
    this.gameId = (params?.['gameId'] as string) || '';
    this.loadGameDetail();
  }

  async loadGameDetail(): Promise<void> {
    if (!this.gameId) {
      this.errorMsg = '缺少游戏 ID';
      this.isLoading = false;
      return;
    }
    this.isLoading = true;
    this.errorMsg = '';
    try {
      this.game = await GameService.detail(this.gameId);
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

  async launchGame(): Promise<void> {
    if (this.launching) {
      return;
    }
    this.launching = true;
    try {
      const result = await GameService.launch(this.gameId);
      promptAction.showToast({ message: `已启动 ${result.name}` });
    } catch (e) {
      const msg = `${e}`;
      if (msg.indexOf('UNAUTHORIZED') >= 0) {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: msg.length > 0 ? msg : '启动失败' });
    } finally {
      this.launching = false;
    }
  }

  build() {
    Column() {
      Row() {
        Button('← 返回')
          .fontSize(14)
          .fontColor('#1677FF')
          .backgroundColor(Color.Transparent)
          .onClick(() => {
            router.back();
          })
        Text(this.game?.name || '游戏详情')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .layoutWeight(1)
          .textAlign(TextAlign.Center)
        Blank().width(64)
      }
      .width('100%')
      .height(52)
      .padding({ left: 8, right: 8 })
      .backgroundColor('#FFFFFF')

      if (this.isLoading) {
        LoadingView({ message: '加载详情中...' })
      } else if (this.game) {
        Scroll() {
          Column() {
            Column() {
              Text((this.game!.name || '?').substring(0, 1))
                .fontSize(48)
                .fontColor('#FFFFFF')
                .fontWeight(FontWeight.Bold)
            }
            .width('100%')
            .height(160)
            .borderRadius(12)
            .backgroundColor('#1677FF')
            .justifyContent(FlexAlign.Center)
            .margin({ top: 12 })

            Row() {
              Text(this.game!.name)
                .fontSize(22)
                .fontWeight(FontWeight.Bold)
                .fontColor('#333333')
                .layoutWeight(1)
              if (this.game!.type) {
                Text(this.game!.type)
                  .fontSize(12)
                  .fontColor('#1677FF')
                  .backgroundColor('#E6F0FF')
                  .borderRadius(4)
                  .padding({ left: 8, right: 8, top: 4, bottom: 4 })
              }
            }
            .width('100%')
            .margin({ top: 16 })

            Text(this.game!.description || '暂无简介')
              .fontSize(14)
              .fontColor('#666666')
              .margin({ top: 8 })
              .width('100%')

            if (this.game!.currencies && this.game!.currencies.length > 0) {
              Text('支持币种')
                .fontSize(16)
                .fontWeight(FontWeight.Bold)
                .width('100%')
                .margin({ top: 24, bottom: 8 })

              ForEach(this.game!.currencies, (currency: GameCurrency) => {
                Row() {
                  Column() {
                    Text(currency.name)
                      .fontSize(15)
                      .fontWeight(FontWeight.Medium)
                    Text(`1 平台币 = ${currency.exchange_rate} ${currency.symbol}`)
                      .fontSize(12)
                      .fontColor('#999999')
                      .margin({ top: 2 })
                  }
                  .alignItems(HorizontalAlign.Start)
                  .layoutWeight(1)

                  Text(currency.symbol)
                    .fontSize(18)
                    .fontColor('#1677FF')
                    .fontWeight(FontWeight.Bold)
                }
                .width('100%')
                .padding(12)
                .margin({ bottom: 8 })
                .borderRadius(8)
                .backgroundColor('#F8F9FA')
              }, (currency: GameCurrency) => currency.id)
            }
          }
          .width('100%')
          .padding({ left: 16, right: 16, bottom: 16 })
        }
        .layoutWeight(1)

        Button(this.launching ? '启动中...' : '开始游戏')
          .width('90%')
          .height(48)
          .fontSize(16)
          .fontColor('#FFFFFF')
          .backgroundColor(this.launching ? '#99BBFF' : '#1677FF')
          .borderRadius(24)
          .margin({ bottom: 24 })
          .enabled(!this.launching)
          .onClick(() => {
            this.launchGame();
          })
      } else {
        ErrorView({ message: this.errorMsg || '游戏不存在' })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#FFFFFF')
  }
}
