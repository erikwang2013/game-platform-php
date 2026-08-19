/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { GameItem } from '../model/DataModels';
import { GameService } from '../service/DataService';
import { LoadingView, EmptyView, ErrorView, BottomNav } from '../common/Components';

@Entry
@Component
struct GameHallPage {
  @State games: GameItem[] = [];
  @State isLoading: boolean = true;
  @State errorMsg: string = '';
  @State searchKeyword: string = '';

  aboutToAppear(): void {
    this.loadGames();
  }

  async loadGames(): Promise<void> {
    this.isLoading = true;
    this.errorMsg = '';
    try {
      this.games = await GameService.list(this.searchKeyword.trim());
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

  private openDetail(game: GameItem): void {
    router.pushUrl({ url: 'pages/GameDetailPage', params: { gameId: game.id } });
  }

  build() {
    Column() {
      Row() {
        Text('游戏大厅')
          .fontSize(20)
          .fontWeight(FontWeight.Bold)
          .fontColor('#333333')
      }
      .width('100%')
      .padding({ left: 16, right: 16, top: 16, bottom: 8 })

      TextInput({ placeholder: '搜索游戏...', text: this.searchKeyword })
        .height(40)
        .fontSize(14)
        .backgroundColor('#FFFFFF')
        .borderRadius(8)
        .margin({ left: 16, right: 16, bottom: 8 })
        .onChange((value: string) => {
          this.searchKeyword = value;
        })
        .onSubmit(() => {
          this.loadGames();
        })

      if (this.isLoading) {
        LoadingView({ message: '加载游戏中...' })
      } else if (this.errorMsg) {
        ErrorView({ message: this.errorMsg })
      } else if (this.games.length === 0) {
        EmptyView({ message: this.searchKeyword ? '没有匹配的游戏' : '暂无游戏' })
      } else {
        List() {
          ForEach(this.games, (game: GameItem) => {
            ListItem() {
              Row() {
                Column() {
                  Text((game.name || '?').substring(0, 1))
                    .fontSize(22)
                    .fontColor('#1677FF')
                    .fontWeight(FontWeight.Bold)
                }
                .width(56)
                .height(56)
                .borderRadius(8)
                .backgroundColor('#E6F0FF')
                .justifyContent(FlexAlign.Center)

                Column() {
                  Row() {
                    Text(game.name || '未命名')
                      .fontSize(16)
                      .fontWeight(FontWeight.Medium)
                      .fontColor('#333333')
                      .layoutWeight(1)
                      .maxLines(1)
                      .textOverflow({ overflow: TextOverflow.Ellipsis })

                    if (game.type) {
                      Text(game.type)
                        .fontSize(11)
                        .fontColor('#1677FF')
                        .backgroundColor('#E6F0FF')
                        .borderRadius(4)
                        .padding({ left: 6, right: 6, top: 2, bottom: 2 })
                    }
                  }
                  .width('100%')

                  Text(game.description || '暂无简介')
                    .fontSize(12)
                    .fontColor('#999999')
                    .maxLines(2)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })
                    .margin({ top: 4 })
                }
                .alignItems(HorizontalAlign.Start)
                .layoutWeight(1)
                .margin({ left: 12 })

                Button('进入')
                  .fontSize(12)
                  .height(32)
                  .backgroundColor('#1677FF')
                  .fontColor('#FFFFFF')
                  .onClick(() => {
                    this.openDetail(game);
                  })
              }
              .width('100%')
              .padding(12)
              .backgroundColor('#FFFFFF')
              .borderRadius(8)
              .onClick(() => {
                this.openDetail(game);
              })
            }
            .padding({ left: 16, right: 16, bottom: 8 })
          }, (game: GameItem) => game.id)
        }
        .width('100%')
        .layoutWeight(1)
      }

      BottomNav({ current: 'hall' })
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}
