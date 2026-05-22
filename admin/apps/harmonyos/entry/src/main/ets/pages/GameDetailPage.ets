/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import router from '@ohos.router';

@Entry
@Component
struct GameDetailPage {
  @State gameId: string = '';
  @State gameName: string = '';
  @State gameDesc: string = '';

  aboutToAppear(): void {
    const params = router.getParams() as Record<string, Object>;
    this.gameId = params?.['gameId'] as string || '';
    this.gameName = '游戏详情';
  }

  build() {
    Column() {
      Row() {
        Button('← 返回').onClick(() => router.back())
        Text(this.gameName).fontSize(18).fontWeight(FontWeight.Bold).layoutWeight(1).textAlign(TextAlign.Center)
        Blank()
      }.padding(16)

      Column() {
        Text('游戏信息').fontSize(18).fontWeight(FontWeight.Bold)
        Text('即将推出更多功能').fontSize(14).fontColor('#999').margin({ top: 16 })
      }
      .width('100%')
      .layoutWeight(1)
      .justifyContent(FlexAlign.Center)
      .alignItems(HorizontalAlign.Center)

      Button('开始游戏')
        .width('90%')
        .margin({ bottom: 24 })
        .onClick(() => {
          // Launch game
        })
    }
    .width('100%')
    .height('100%')
  }
}
