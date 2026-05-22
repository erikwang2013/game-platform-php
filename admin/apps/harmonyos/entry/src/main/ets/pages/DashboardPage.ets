/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { DashboardService } from '../service/DataService';
import { DashboardData, StatCard as StatCardType, OperationLogItem } from '../model/DataModels';
import { StatCard, LoadingView, ErrorView, EmptyView, CopyrightBar } from '../common/Components';

@Entry
@Component
struct DashboardPage {
  @State dashboardData: DashboardData | null = null;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;

  aboutToAppear(): void {
    this.loadDashboard();
  }

  async loadDashboard(): Promise<void> {
    this.isLoading = true;
    this.hasError = false;
    try {
      this.dashboardData = await DashboardService.getDashboard();
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.hasError = true;
    } finally {
      this.isLoading = false;
    }
  }

  build() {
    Column() {
      // 顶栏
      Row() {
        Text('仪表盘')
          .fontSize(20)
          .fontWeight(FontWeight.Bold)

        Blank()

        // 导出菜单
        Button('导出')
          .fontSize(13)
          .fontColor('#1677FF')
          .backgroundColor('#E6F0FF')
          .borderRadius(4)
          .onClick(() => {
            promptAction.showToast({ message: '导出功能已触发，查看系统通知' });
          })

        Image($rawfile('avatar.png'))
          .width(36)
          .height(36)
          .borderRadius(18)
          .margin({ left: 12 })
          .onClick(() => {
            router.pushUrl({ url: 'pages/ProfilePage' });
          })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      // 内容区
      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadDashboard() });
      } else if (!this.dashboardData) {
        EmptyView();
      } else {
        Scroll() {
          Column() {
            // 统计卡片网格
            Grid() {
              ForEach(this.dashboardData!.stats, (stat: StatCardType, index: number) => {
                GridItem() {
                  StatCard({
                    label: stat.label,
                    value: stat.value,
                    icon: stat.icon,
                    color: stat.color,
                    trend: stat.trend
                  })
                }
              })
            }
            .columnsTemplate('1fr 1fr')
            .rowsGap(12)
            .columnsGap(12)
            .width('100%')
            .margin({ top: 16 })
            .padding({ left: 16, right: 16 })

            // 最近操作日志
            Column() {
              Row() {
                Text('最近操作')
                  .fontSize(16)
                  .fontWeight(FontWeight.Medium)

                Blank()

                Text('查看全部 >')
                  .fontSize(12)
                  .fontColor('#1677FF')
              }
              .width('100%')
              .margin({ bottom: 12 })

              if (this.dashboardData!.recent_logs.length === 0) {
                Text('暂无操作记录')
                  .fontSize(13)
                  .fontColor('#999999')
                  .width('100%')
                  .textAlign(TextAlign.Center)
                  .padding({ top: 24, bottom: 24 })
              } else {
                ForEach(this.dashboardData!.recent_logs.slice(0, 8), (log: OperationLogItem) => {
                  Row() {
                    Circle({ width: 32, height: 32 })
                      .fill('#E6F0FF')

                    Column() {
                      Text(log.action)
                        .fontSize(13)
                        .fontColor('#333333')

                      Text(log.created_at ?? '')
                        .fontSize(11)
                        .fontColor('#999999')
                        .margin({ top: 2 })
                    }
                    .alignItems(HorizontalAlign.Start)
                    .margin({ left: 8 })
                    .layoutWeight(1)

                    Text(log.ip ?? '')
                      .fontSize(11)
                      .fontColor('#999999')
                  }
                  .width('100%')
                  .height(48)
                  .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
                })
              }
            }
            .width('100%')
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .padding(16)
            .margin({ left: 16, right: 16, top: 12 })

            // 底部版权
            CopyrightBar();
          }
          .width('100%')
        }
        .width('100%')
        .layoutWeight(1)
        .backgroundColor('#F5F5F5')
      }
    }
    .width('100%')
    .height('100%')
  }
}
