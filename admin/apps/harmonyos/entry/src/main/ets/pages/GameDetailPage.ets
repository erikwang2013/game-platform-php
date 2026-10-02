/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { router } from '@kit.ArkUI';
import { promptAction } from '@kit.ArkUI';
import { apiService } from '../service/ApiService';
import { t } from '../i18n/Translations';

interface GameDetail {
  id: string;
  name: string;
  slug: string;
  description: string;
  cover_image: string;
  currencies: Array<{ id: string; name: string; symbol: string; exchange_rate: string }>;
}

/** 启动参数：GameController::launch 收 game_id（hashid，不是裸 BIGINT） */
interface LaunchParams {
  game_id: string;
}

@Entry
@Component
struct GameDetailPage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State gameId: string = '';
  @State game: GameDetail | null = null;
  @State isLoading: boolean = true;
  @State errorMsg: string = '';

  aboutToAppear(): void {
    const params = router.getParams() as Record<string, Object>;
    this.gameId = (params?.['gameId'] as string) || '';
    this.loadGameDetail();
  }

  async loadGameDetail(): Promise<void> {
    this.isLoading = true;
    try {
      // 走 apiService：语言头 / 来源端头 / 401 刷新只有一份实现在 ApiService 里，
      // 自建客户端的那些头此前是手抄的（且没有 X-Language，服务端 message 恒中文）
      const body = await apiService.get<GameDetail>(`/admin/v1/game/${this.gameId}`);
      if (body.code === 0) {
        this.game = body.data;
      }
    } catch (e) {
      // apiService 在刷新也失败时抛 UNAUTHORIZED（旧的自建客户端没有这条路径）
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errorMsg = t('common.load_failed');
    } finally {
      this.isLoading = false;
    }
  }

  async launchGame(): Promise<void> {
    try {
      const params: LaunchParams = { game_id: this.gameId };
      const body = await apiService.post<void>('/admin/v1/game/launch', params);
      if (body.code === 0) {
        promptAction.showToast({ message: t('game.launch_success') });
      }
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: t('game.launch_failed') });
    }
  }

  build() {
    Column() {
      // Header
      Row() {
        Button(t('common.back'))
          .onClick(() => router.back())
          .backgroundColor('#00000000')
        Text(this.game?.name || t('game.detail_title'))
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .layoutWeight(1)
          .textAlign(TextAlign.Center)
        Blank()
      }
      .width('100%')
      .padding(16)

      if (this.isLoading) {
        LoadingProgress().margin({ top: 100 })
      } else if (this.game) {
        Scroll() {
          Column() {
            // Cover image
            Image(this.game!.cover_image || '')
              .width('100%')
              .height(200)
              .objectFit(ImageFit.Cover)
              .borderRadius(12)
              .margin({ top: 8, left: 16, right: 16 })

            // Description
            Text(this.game!.description || t('game.no_description'))
              .fontSize(14)
              .fontColor('#666')
              .margin({ top: 16, left: 16, right: 16 })
              .maxLines(5)

            // Currencies
            if (this.game!.currencies && this.game!.currencies.length > 0) {
              Text(t('game.currencies_title'))
                .fontSize(16)
                .fontWeight(FontWeight.Bold)
                .margin({ top: 24, left: 16 })

              List() {
                ForEach(this.game!.currencies, (currency) => {
                  ListItem() {
                    Row() {
                      Column() {
                        Text(currency.name)
                          .fontSize(16)
                          .fontWeight(FontWeight.Medium)
                        Text(t('game.exchange_rate_line')
                          .replace('{rate}', currency.exchange_rate).replace('{symbol}', currency.symbol))
                          .fontSize(12)
                          .fontColor('#999')
                      }
                      .alignItems(HorizontalAlign.Start)
                      .layoutWeight(1)

                      Text(currency.symbol)
                        .fontSize(24)
                        .fontColor('#1677FF')
                    }
                    .width('100%')
                    .padding(12)
                    .margin({ left: 16, right: 16, top: 4 })
                    .borderRadius(8)
                    .backgroundColor('#F8F9FA')
                  }
                })
              }
              .margin({ top: 8 })
            }
          }
        }
        .layoutWeight(1)

        // Launch button
        Button(t('game.start'))
          .width('90%')
          .height(48)
          .fontSize(16)
          .backgroundColor('#1677FF')
          .borderRadius(24)
          .margin({ bottom: 24 })
          .onClick(() => { this.launchGame() })
      } else {
        Text(this.errorMsg || t('game.not_found'))
          .fontSize(14)
          .fontColor('#999')
          .margin({ top: 100 })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#FFFFFF')
  }
}
