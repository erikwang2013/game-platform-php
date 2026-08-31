/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { DashboardService, ReportService } from '../service/DataService';
import { DashboardData, StatCard as StatCardType, OperationLogItem, ReportSummary } from '../model/DataModels';
import { StatCard, LoadingView, ErrorView, EmptyView, CopyrightBar } from '../common/Components';

@Entry
@Component
struct DashboardPage {
  @State dashboardData: DashboardData | null = null;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State report: ReportSummary | null = null;
  @State reportDays: number = 30;
  @State reportLoading: boolean = false;

  aboutToAppear(): void {
    this.loadDashboard();
    this.loadReport();
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

  // 报表汇总：近 7 天 / 近 30 天快捷预设
  async loadReport(): Promise<void> {
    this.reportLoading = true;
    try {
      const end = new Date();
      const start = new Date();
      start.setDate(start.getDate() - (this.reportDays - 1));
      const fmt = (d: Date): string => {
        const m = `${d.getMonth() + 1}`.padStart(2, '0');
        const day = `${d.getDate()}`.padStart(2, '0');
        return `${d.getFullYear()}-${m}-${day}`;
      };
      this.report = await ReportService.getSummary(fmt(start), fmt(end));
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.report = null;
      promptAction.showToast({ message: '报表加载失败' });
    } finally {
      this.reportLoading = false;
    }
  }

  switchReportDays(days: number): void {
    if (this.reportDays === days) return;
    this.reportDays = days;
    this.loadReport();
  }

  formatAmount(value: string): string {
    const num = Number(value);
    return `¥${num.toFixed(2)}`;
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

            // 报表汇总卡片
            Column() {
              Row() {
                Text('报表汇总')
                  .fontSize(16)
                  .fontWeight(FontWeight.Medium)

                Blank()

                Button('近7天')
                  .fontSize(12)
                  .height(28)
                  .fontColor(this.reportDays === 7 ? '#FFFFFF' : '#1677FF')
                  .backgroundColor(this.reportDays === 7 ? '#1677FF' : '#E6F0FF')
                  .borderRadius(4)
                  .onClick(() => this.switchReportDays(7))

                Button('近30天')
                  .fontSize(12)
                  .height(28)
                  .fontColor(this.reportDays === 30 ? '#FFFFFF' : '#1677FF')
                  .backgroundColor(this.reportDays === 30 ? '#1677FF' : '#E6F0FF')
                  .borderRadius(4)
                  .margin({ left: 8 })
                  .onClick(() => this.switchReportDays(30))
              }
              .width('100%')
              .margin({ bottom: 12 })

              if (this.reportLoading) {
                Text('加载中...')
                  .fontSize(13)
                  .fontColor('#999999')
                  .width('100%')
                  .textAlign(TextAlign.Center)
                  .padding({ top: 16, bottom: 16 })
              } else if (this.report) {
                Grid() {
                  GridItem() {
                    Column() {
                      Text('充值金额')
                        .fontSize(12)
                        .fontColor('#999999')

                      Text(this.formatAmount(this.report.deposit_amount))
                        .fontSize(16)
                        .fontWeight(FontWeight.Bold)
                        .fontColor('#1677FF')
                        .margin({ top: 4 })
                    }
                    .alignItems(HorizontalAlign.Start)
                  }

                  GridItem() {
                    Column() {
                      Text('提现金额')
                        .fontSize(12)
                        .fontColor('#999999')

                      Text(this.formatAmount(this.report.withdraw_amount))
                        .fontSize(16)
                        .fontWeight(FontWeight.Bold)
                        .fontColor('#FA8C16')
                        .margin({ top: 4 })
                    }
                    .alignItems(HorizontalAlign.Start)
                  }

                  GridItem() {
                    Column() {
                      Text('新增用户')
                        .fontSize(12)
                        .fontColor('#999999')

                      Text(`${this.report.new_users}`)
                        .fontSize(16)
                        .fontWeight(FontWeight.Bold)
                        .fontColor('#333333')
                        .margin({ top: 4 })
                    }
                    .alignItems(HorizontalAlign.Start)
                  }

                  GridItem() {
                    Column() {
                      Text('游戏局数')
                        .fontSize(12)
                        .fontColor('#999999')

                      Text(`${this.report.play_count}`)
                        .fontSize(16)
                        .fontWeight(FontWeight.Bold)
                        .fontColor('#333333')
                        .margin({ top: 4 })
                    }
                    .alignItems(HorizontalAlign.Start)
                  }
                }
                .columnsTemplate('1fr 1fr')
                .rowsGap(16)
                .columnsGap(12)
                .width('100%')
              } else {
                Text('报表加载失败，点击重试')
                  .fontSize(13)
                  .fontColor('#999999')
                  .width('100%')
                  .textAlign(TextAlign.Center)
                  .padding({ top: 16, bottom: 16 })
                  .onClick(() => this.loadReport())
              }
            }
            .width('100%')
            .backgroundColor('#FFFFFF')
            .borderRadius(8)
            .padding(16)
            .margin({ left: 16, right: 16, top: 12 })

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
