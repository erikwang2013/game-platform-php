/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import router from '@ohos.router';
import http from '@ohos.net.http';
import promptAction from '@ohos.promptAction';
import { TokenManager } from '../utils/TokenManager';
import { API_BASE_URL } from '../common/AppConfig';
import { pageQuery } from '../model/DataModels';
import { PagerBar } from '../common/ResourceViews';
import { t, t1 } from '../i18n/Translations';

@Entry
@Component
struct GameHallPage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State games: Array<GameItem> = [];
  @State isLoading: boolean = true;
  @State searchKeyword: string = '';
  @State page: number = 1;
  @State total: number = 0;
  private readonly limit: number = 15;

  aboutToAppear(): void {
    this.loadGames();
  }

  async loadGames(): Promise<void> {
    this.isLoading = true;
    try {
      const token = TokenManager.getAccessToken();
      const request = http.createHttp();
      // 分页参数走 pageQuery 扇出（本端点读 limit，别名一起发不影响）
      const response = await request.request(`${API_BASE_URL}/admin/v1/game/list?${pageQuery(this.page, this.limit)}`, {
        method: http.RequestMethod.GET,
        header: {
          'Content-Type': 'application/json',

          'X-Client-Platform': 'harmonyos',
          'Authorization': `Bearer ${token}`,
        },
      });

      if (response.responseCode === 200) {
        const body = JSON.parse(response.result as string);
        if (body.code === 0) {
          this.games = body.data.list || [];
          this.total = Number(body.data.total || 0);
        }
      }
    } catch (e) {
      promptAction.showToast({ message: t('common.load_failed') });
    } finally {
      this.isLoading = false;
    }
  }

  pageCount(): number {
    return Math.max(1, Math.ceil(this.total / this.limit));
  }

  goPage(delta: number): void {
    const next = this.page + delta;
    if (next < 1 || next > this.pageCount()) {
      return;
    }
    this.page = next;
    this.loadGames();
  }

  build() {
    Column() {
      // Header
      Row() {
        Text(t('game.hall'))
          .fontSize(20)
          .fontWeight(FontWeight.Bold)
        Blank()
        Button(t('game.my_wallet'))
          .onClick(() => router.pushUrl({ url: 'pages/WalletPage' }))
      }
      .width('100%')
      .padding(16)

      // Search
      TextInput({ placeholder: t('game.search_placeholder'), text: this.searchKeyword })
        .width('100%')
        .margin({ left: 16, right: 16, bottom: 8 })
        .onChange((value: string) => { this.searchKeyword = value })
        .onSubmit(() => { this.loadGames() })

      // Game List
      if (this.isLoading) {
        LoadingProgress()
      } else {
        List() {
          ForEach(this.games, (game: GameItem) => {
            ListItem() {
              Row() {
                Image(game.cover_image || '')
                  .width(60).height(60)
                  .borderRadius(8)
                  .objectFit(ImageFit.Cover)
                Column() {
                  Text(game.name).fontSize(16).fontWeight(FontWeight.Medium)
                  Text(game.description || '').fontSize(12).maxLines(2).textOverflow({ overflow: TextOverflow.Ellipsis })
                }
                .alignItems(HorizontalAlign.Start)
                .layoutWeight(1)
                .margin({ left: 12 })
                Button(t('game.enter'))
                  .fontSize(12)
                  .onClick(() => {
                    router.pushUrl({ url: 'pages/GameDetailPage', params: { gameId: game.id } })
                  })
              }
              .width('100%')
              .padding(12)
            }
          })
        }
      }

      // 分页：本页打的是分页端点（/admin/v1/game/list，服务端默认只回 15 条），
      // 不挂分页条就永远只能看到第一页
      if (!this.isLoading && this.total > this.limit) {
        PagerBar({
          page: this.page,
          pageCount: this.pageCount(),
          onPrev: () => {
            this.goPage(-1);
          },
          onNext: () => {
            this.goPage(1);
          }
        })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}

interface GameItem {
  id: string;
  name: string;
  slug?: string;
  description?: string;
  cover_image?: string;
}
