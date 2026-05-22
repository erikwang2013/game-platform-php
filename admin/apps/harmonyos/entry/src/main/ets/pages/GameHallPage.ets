/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import router from '@ohos.router';
import http from '@ohos.net.http';
import promptAction from '@ohos.promptAction';
import { TokenManager } from '../utils/TokenManager';

@Entry
@Component
struct GameHallPage {
  @State games: Array<GameItem> = [];
  @State isLoading: boolean = true;
  @State searchKeyword: string = '';

  aboutToAppear(): void {
    this.loadGames();
  }

  async loadGames(): Promise<void> {
    this.isLoading = true;
    try {
      const token = TokenManager.getAccessToken();
      const request = http.createHttp();
      const response = await request.request('http://10.0.2.2:8788/api/game/list', {
        method: http.RequestMethod.GET,
        header: {
          'Content-Type': 'application/json',
          'API-Version': 'v1',
          'X-Client-Platform': 'harmonyos',
          'Authorization': `Bearer ${token}`,
        },
      });

      if (response.responseCode === 200) {
        const body = JSON.parse(response.result as string);
        if (body.code === 0) {
          this.games = body.data.list || [];
        }
      }
    } catch (e) {
      promptAction.showToast({ message: '加载失败' });
    } finally {
      this.isLoading = false;
    }
  }

  build() {
    Column() {
      // Header
      Row() {
        Text('游戏大厅')
          .fontSize(20)
          .fontWeight(FontWeight.Bold)
        Blank()
        Button('我的钱包')
          .onClick(() => router.pushUrl({ url: 'pages/WalletPage' }))
      }
      .width('100%')
      .padding(16)

      // Search
      TextInput({ placeholder: '搜索游戏...', text: this.searchKeyword })
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
                Button('进入')
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
