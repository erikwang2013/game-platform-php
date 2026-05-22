/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { UserService } from '../service/DataService';
import { User } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';

@Entry
@Component
struct UserListPage {
  @State users: User[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State keyword: string = '';
  @State isRefreshing: boolean = false;
  private readonly limit: number = 15;

  aboutToAppear(): void {
    this.loadData();
  }

  async loadData(isRefresh: boolean = false): Promise<void> {
    if (isRefresh) {
      this.page = 1;
      this.isRefreshing = true;
    } else {
      this.isLoading = true;
    }
    this.hasError = false;

    try {
      const result = await UserService.getList({
        page: this.page,
        limit: this.limit,
        keyword: this.keyword || undefined
      });
      if (isRefresh || this.page === 1) {
        this.users = result.list;
      } else {
        this.users = this.users.concat(result.list);
      }
      this.total = result.total;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.hasError = true;
      promptAction.showToast({ message: '加载失败' });
    } finally {
      this.isLoading = false;
      this.isRefreshing = false;
    }
  }

  loadMore(): void {
    if (this.users.length >= this.total) return;
    this.page++;
    this.loadData();
  }

  onSearch(): void {
    this.page = 1;
    this.loadData(true);
  }

  build() {
    Column() {
      // 顶栏
      Row() {
        Button({ type: ButtonType.Circle }) {
          Image($rawfile('back.png')).width(24).height(24)
        }
        .width(36).height(36)
        .backgroundColor('#FFFFFF')
        .onClick(() => router.back())

        Text('用户管理')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        Button('+ 新增')
          .fontSize(13)
          .fontColor('#FFFFFF')
          .backgroundColor('#1677FF')
          .borderRadius(4)
          .height(32)
          .onClick(() => {
            router.pushUrl({ url: 'pages/UserDetailPage', params: { mode: 'create' } });
          })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      // 搜索栏
      Row() {
        TextInput({ placeholder: '搜索用户名或姓名', text: this.keyword })
          .height(40)
          .fontSize(14)
          .backgroundColor('#F5F5F5')
          .borderRadius(8)
          .layoutWeight(1)
          .padding({ left: 12, right: 12 })
          .onChange((value: string) => {
            this.keyword = value;
          })
          .onSubmit(() => {
            this.onSearch();
          })

        Button('搜索')
          .fontSize(13)
          .fontColor('#FFFFFF')
          .backgroundColor('#1677FF')
          .borderRadius(4)
          .width(60)
          .height(40)
          .margin({ left: 8 })
          .onClick(() => {
            this.onSearch();
          })
      }
      .width('100%')
      .padding({ left: 16, right: 16, top: 8, bottom: 8 })
      .backgroundColor('#FFFFFF')

      // 内容
      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadData(true) });
      } else if (this.users.length === 0) {
        EmptyView({ message: '暂无用户数据' });
      } else {
        // 刷新组件
        Refresh({ refreshing: $$this.isRefreshing }) {
          List() {
            ForEach(this.users, (user: User, index: number) => {
              ListItem() {
                Row() {
                  // 头像
                  Circle({ width: 40, height: 40 })
                    .fill('#E6F0FF')

                  // 信息
                  Column() {
                    Row() {
                      Text(user.username)
                        .fontSize(15)
                        .fontWeight(FontWeight.Medium)

                      Text(user.status === 1 ? '启用' : '禁用')
                        .fontSize(10)
                        .fontColor(user.status === 1 ? '#52C41A' : '#FF4D4F')
                        .backgroundColor(user.status === 1 ? '#E6FFF0' : '#FFE6E6')
                        .borderRadius(2)
                        .padding({ left: 6, right: 6, top: 2, bottom: 2 })
                        .margin({ left: 8 })
                    }

                    Text(user.real_name)
                      .fontSize(12)
                      .fontColor('#999999')
                      .margin({ top: 2 })
                  }
                  .alignItems(HorizontalAlign.Start)
                  .layoutWeight(1)
                  .margin({ left: 12 })

                  Image($rawfile('arrow_right.png'))
                    .width(16)
                    .height(16)
                    .fillColor('#CCCCCC')
                }
                .width('100%')
                .height(64)
                .padding({ left: 16, right: 16 })
                .backgroundColor('#FFFFFF')
                .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
                .onClick(() => {
                  router.pushUrl({
                    url: 'pages/UserDetailPage',
                    params: { mode: 'edit', userId: user.id }
                  });
                })
              }
            })

            // 加载更多
            ListItem() {
              Row() {
                if (this.users.length >= this.total) {
                  Text('— 没有更多了 —')
                    .fontSize(12)
                    .fontColor('#CCCCCC')
                } else {
                  Button('加载更多')
                    .fontSize(13)
                    .fontColor('#1677FF')
                    .backgroundColor(Color.Transparent)
                    .onClick(() => this.loadMore())
                }
              }
              .justifyContent(FlexAlign.Center)
              .width('100%')
              .height(48)
            }
          }
          .onReachEnd(() => {
            // 通过 ListItem 中的按钮手动加载更多
          })
          .width('100%')
          .layoutWeight(1)
          .edgeEffect(EdgeEffect.Spring)
        }
        .onRefreshing(() => {
          this.loadData(true);
        })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}
