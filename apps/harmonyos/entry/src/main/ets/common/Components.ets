/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';

/**
 * 加载状态
 */
@Component
export struct LoadingView {
  @Prop message: string = '加载中...';

  build() {
    Column() {
      LoadingProgress()
        .width(40)
        .height(40)
        .color('#1677FF')

      Text(this.message)
        .fontSize(14)
        .fontColor('#999999')
        .margin({ top: 12 })
    }
    .justifyContent(FlexAlign.Center)
    .width('100%')
    .layoutWeight(1)
  }
}

/**
 * 空状态
 */
@Component
export struct EmptyView {
  @Prop message: string = '暂无数据';

  build() {
    Column() {
      Text(this.message)
        .fontSize(14)
        .fontColor('#999999')
    }
    .justifyContent(FlexAlign.Center)
    .width('100%')
    .layoutWeight(1)
  }
}

/**
 * 错误状态
 */
@Component
export struct ErrorView {
  @Prop message: string = '加载失败';
  onRetry?: () => void;

  build() {
    Column() {
      Text(this.message)
        .fontSize(14)
        .fontColor('#999999')

      Button('重试')
        .margin({ top: 12 })
        .fontSize(14)
        .onClick(() => {
          if (this.onRetry) {
            this.onRetry();
          }
        })
    }
    .justifyContent(FlexAlign.Center)
    .width('100%')
    .layoutWeight(1)
  }
}

/**
 * 底部版权
 */
@Component
export struct CopyrightBar {
  build() {
    Text('Copyright (c) 2026 erik — https://erik.xyz')
      .fontSize(11)
      .fontColor('#CCCCCC')
      .textAlign(TextAlign.Center)
      .width('100%')
      .padding({ top: 8, bottom: 8 })
  }
}

/**
 * C 端底部导航：大厅 / 钱包 / 我的
 */
@Component
export struct BottomNav {
  @Prop current: string = 'hall';

  private go(target: string, url: string): void {
    if (this.current === target) {
      return;
    }
    router.replaceUrl({ url: url });
  }

  build() {
    Row() {
      Column() {
        Text('大厅')
          .fontSize(12)
          .fontColor(this.current === 'hall' ? '#1677FF' : '#999999')
          .fontWeight(this.current === 'hall' ? FontWeight.Medium : FontWeight.Normal)
      }
      .layoutWeight(1)
      .height(52)
      .justifyContent(FlexAlign.Center)
      .onClick(() => {
        this.go('hall', 'pages/GameHallPage');
      })

      Column() {
        Text('钱包')
          .fontSize(12)
          .fontColor(this.current === 'wallet' ? '#1677FF' : '#999999')
          .fontWeight(this.current === 'wallet' ? FontWeight.Medium : FontWeight.Normal)
      }
      .layoutWeight(1)
      .height(52)
      .justifyContent(FlexAlign.Center)
      .onClick(() => {
        this.go('wallet', 'pages/WalletPage');
      })

      Column() {
        Text('我的')
          .fontSize(12)
          .fontColor(this.current === 'profile' ? '#1677FF' : '#999999')
          .fontWeight(this.current === 'profile' ? FontWeight.Medium : FontWeight.Normal)
      }
      .layoutWeight(1)
      .height(52)
      .justifyContent(FlexAlign.Center)
      .onClick(() => {
        this.go('profile', 'pages/ProfilePage');
      })
    }
    .width('100%')
    .height(56)
    .backgroundColor('#FFFFFF')
    .border({ width: { top: 0.5 }, color: '#EEEEEE' })
  }
}
