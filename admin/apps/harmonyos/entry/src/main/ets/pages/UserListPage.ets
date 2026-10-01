/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { UserService } from '../service/DataService';
import { User } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { TokenManager } from '../utils/TokenManager';
import { t, t1 } from '../i18n/Translations';

@Entry
@Component
struct UserListPage {
  @StorageLink('app_locale') localeCode: string = 'en';
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

  /**
   * 选中的行里有没有**当前登录的管理员自己**。
   * 用用户名认人：`game_admin_user.username` 是唯一键（uk_username），且当前用户名在登录时
   * 就落进了 AppStorage（见 TokenManager::getAdminUsername）。
   */
  private selectedSelf(): boolean {
    const me = TokenManager.getAdminUsername();
    if (me.length === 0) {
      return false;
    }
    for (let i = 0; i < this.users.length; i++) {
      if (this.users[i].username === me && this.selectedIds.includes(this.users[i].id)) {
        return true;
      }
    }
    return false;
  }

  batchOperate(status: number): void {
    // 自我保护：批量停用把自己关了，就再没人能开回来了（后端不禁止自伤，只验操作者密码）
    if (status === 0 && this.selectedSelf()) {
      promptAction.showToast({ message: t('user.batch_self_block') });
      return;
    }
    const label = status === 1 ? t('user.status_enabled') : t('user.status_disabled');
    promptAction.showDialog({
      title: t1('user.batch_title', 'action', label),
      message: t('user.batch_confirm').replace('{action}', label).replace('{count}', `${this.selectedIds.length}`),
      buttons: [
        { text: t('common.cancel'), color: '#999999' },
        { text: t('common.confirm'), color: '#1677FF' }
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
      promptAction.showToast({ message: status === 1 ? t('user.batch_enabled') : t('user.batch_disabled') });
      this.toggleSelectionMode();
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: t('res.op_failed') });
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
      // 位置参数（原参数对象写法既是字面量实参、签名侧还是交叉类型，两处都编不过）
      const result = await UserService.getList(this.page, this.limit, this.keyword);
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
      promptAction.showToast({ message: t('common.load_failed') });
    } finally {
      this.isLoading = false;
      this.isRefreshing = false;
    }
  }

  /**
   * 翻页 = **累加**下一页（列表尾部「加载更多」按钮 / 下拉刷新回到第 1 页），
   * 不挂 PagerBar：那套是「整页替换 + 页码跳转」，与本页「搜索 + 批量勾选」的用法不是一回事
   * —— 累加式翻页换到第 N 页会把用户勾了一半的选区连行一起换掉。分页参数 page/limit 照发。
   */
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
          Image($r('app.media.ic_back')).width(24).height(24)
        }
        .width(36).height(36)
        .backgroundColor('#FFFFFF')
        .onClick(() => router.back())

        Text(t('user.manage_title'))
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        Button(this.selectionMode ? t('common.cancel') : t('user.batch_mode'))
          .fontSize(13)
          .fontColor('#1677FF')
          .backgroundColor('#E6F0FF')
          .borderRadius(4)
          .height(32)
          .margin({ right: 8 })
          .onClick(() => {
            this.toggleSelectionMode();
          })

        Button(t('res.create'))
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
        TextInput({ placeholder: t('user.search_placeholder'), text: this.keyword })
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

        Button(t('common.search'))
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
        EmptyView({ message: t('user.empty') });
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

                      Text(user.status === 1 ? t('user.status_enabled') : t('user.status_disabled'))
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

                  Image($r('app.media.ic_arrow_right'))
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
                  Text(t('common.no_more'))
                    .fontSize(12)
                    .fontColor('#CCCCCC')
                } else {
                  Button(t('common.load_more'))
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
          Text(t1('common.selected_count', 'count', `${this.selectedIds.length}`))
            .fontSize(13)
            .fontColor('#666666')

          Blank()

          Button(t('user.batch_disable'))
            .fontSize(13)
            .fontColor('#FFFFFF')
            .backgroundColor('#FF4D4F')
            .borderRadius(4)
            .height(32)
            .enabled(this.selectedIds.length > 0)
            .onClick(() => {
              this.batchOperate(0);
            })

          Button(t('user.batch_enable'))
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
