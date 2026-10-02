/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { apiService } from '../service/ApiService';
import { GameItem, PageResult, pageQuery } from '../model/DataModels';
import { PagerBar } from '../common/ResourceViews';
import { t } from '../i18n/Translations';

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
      // 分页参数走 pageQuery 扇出（本端点读 limit，别名一起发不影响）；keyword 走 GameManagePage
      // 同一条路（GameController::list 的 where('name','like')）—— 此前搜索框只改状态、从不进请求，
      // 是个按下去没反应的死控件。
      let path = `/admin/v1/game/list?${pageQuery(this.page, this.limit)}`;
      if (this.searchKeyword.length > 0) {
        path += `&keyword=${encodeURIComponent(this.searchKeyword)}`;
      }
      const body = await apiService.get<PageResult<GameItem>>(path);
      if (body.code === 0) {
        this.games = body.data.list;
        this.total = Number(body.data.total);
      }
    } catch (e) {
      // apiService 在刷新也失败时会抛 UNAUTHORIZED（旧的自建 http 客户端没有这条路径，
      // 换过来之后必须接住，否则会话过期时这页只会弹一句「加载失败」）
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
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
        // 墓碑：「我的钱包」入口已摘除，连页面（pages/WalletPage.ets）一起删了 —— 那是个**假页**：
        // loadWallet() 不调任何端点（`this.balance = '--'` + "simplified for now" 注释），
        // 四个宫格按钮的 onClick 全是空的，点进去只有一个永远显示不出数字的余额。
        // 本树**没有**能接的钱包端点：admin/config/route.php 里 wallet / balance 零命中
        // （钱包是 C 端的，端点在 service/config/route.php:80-81 的 /api/v1/wallet/info|transactions，
        // 且 C 端那棵树 apps/harmonyos 里有一份真实现）。要重建得先有管理端钱包端点，
        // 别对着一个不存在的接口再搭一次壳。
      }
      .width('100%')
      .padding(16)

      // Search
      TextInput({ placeholder: t('game.search_placeholder'), text: this.searchKeyword })
        .width('100%')
        .margin({ left: 16, right: 16, bottom: 8 })
        .onChange((value: string) => { this.searchKeyword = value })
        // 换关键词必须回第 1 页：停在第 3 页上搜，命中的 2 条都在第 1 页 ⇒ 搜出个空列表
        .onSubmit(() => { this.page = 1; this.loadGames() })

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
