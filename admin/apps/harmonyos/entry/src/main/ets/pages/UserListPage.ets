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
  @State selectionMode: boolean = false;
  @State selectedIds: string[] = [];
  private readonly limit: number = 15;

  toggleSelectionMode(): void {
    this.selectionMode = !this.selectionMode;
    this.selectedIds = [];
  }

  toggleSelect(id: string, checked: boolean): void {
    if (checked) {
      if (!this.selectedIds.includes(id)) {
        this.selectedIds = this.selectedIds.concat(id);
      }
    } else {
      this.selectedIds = this.selectedIds.filter((x: string) => x !== id);
    }
  }

  batchOperate(status: number): void {
    const label = status === 1 ? '启用' : '禁用';
    promptAction.showDialog({
      title: `批量${label}`,
      message: `确认${label}选中的 ${this.selectedIds.length} 个用户？`,
      buttons: [
        { text: '取消', color: '#999999' },
        { text: '确认', color: '#1677FF' }
      ]
    }).then((result: promptAction.ShowDialogSuccessResponse) => {
      if (result.index === 1) {
        this.doBatch(status);
      }
    }).catch(() => {
    });
  }

  async doBatch(status: number): Promise<void> {
    try {
      await UserService.batchStatus(this.selectedIds, status);
      promptAction.showToast({ message: status === 1 ? '批量启用成功' : '批量禁用成功' });
      this.toggleSelectionMode();
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: '操作失败' });
    }
  }

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

        Button(this.selectionMode ? '取消' : '批量')
          .fontSize(13)
          .fontColor('#1677FF')
          .backgroundColor('#E6F0FF')
          .borderRadius(4)
          .height(32)
          .margin({ right: 8 })
          .onClick(() => {
            this.toggleSelectionMode();
          })

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
                  // 批量选择
                  if (this.selectionMode) {
                    Checkbox()
                      .select(this.selectedIds.includes(user.id))
                      .selectedColor('#1677FF')
                      .margin({ right: 12 })
                      .onChange((checked: boolean) => {
                        this.toggleSelect(user.id, checked);
                      })
                  }

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
                  if (this.selectionMode) {
                    // 选择模式下点行切换选中
                    this.toggleSelect(user.id, !this.selectedIds.includes(user.id));
                    return;
                  }
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

      // 批量操作栏
      if (this.selectionMode) {
        Row() {
          Text(`已选 ${this.selectedIds.length} 项`)
            .fontSize(13)
            .fontColor('#666666')

          Blank()

          Button('批量禁用')
            .fontSize(13)
            .fontColor('#FFFFFF')
            .backgroundColor('#FF4D4F')
            .borderRadius(4)
            .height(32)
            .enabled(this.selectedIds.length > 0)
            .onClick(() => {
              this.batchOperate(0);
            })

          Button('批量启用')
            .fontSize(13)
            .fontColor('#FFFFFF')
            .backgroundColor('#1677FF')
            .borderRadius(4)
            .height(32)
            .margin({ left: 8 })
            .enabled(this.selectedIds.length > 0)
            .onClick(() => {
              this.batchOperate(1);
            })
        }
        .width('100%')
        .height(52)
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { top: 0.5 }, color: '#EEEEEE' })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}
