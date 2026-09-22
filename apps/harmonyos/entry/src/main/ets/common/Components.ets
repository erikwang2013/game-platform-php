/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';

/**
 * 展示用：去掉金额字符串的小数尾零（"12.3400" → "12.34"，"0.0000" → "0"）
 * 纯字符串处理，不参与任何数值运算；仅用于展示，提交值一律原样透传
 */
export function trimZeros(value: string): string {
  if (!value || value.indexOf('.') < 0) {
    return value;
  }
  let out = value;
  while (out.endsWith('0')) {
    out = out.substring(0, out.length - 1);
  }
  if (out.endsWith('.')) {
    out = out.substring(0, out.length - 1);
  }
  return out;
}

/**
 * 数值为 0 的金额字符串。服务端金额列是 DECIMAL(18,4)，下发 "0.0000" 这类形式，
 * 判断"不限"不能写成 === '0'，否则零值判断永远为假
 */
export function isZeroAmount(value: string): boolean {
  return /^0+(\.0+)?$/.test(value);
}

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
 * 二级页顶部导航栏：返回 + 居中标题
 */
@Component
export struct NavBar {
  @Prop title: string = '';

  build() {
    Row() {
      Button('← 返回')
        .fontSize(14)
        .fontColor('#1677FF')
        .backgroundColor(Color.Transparent)
        .onClick(() => {
          router.back();
        })
      Text(this.title)
        .fontSize(18)
        .fontWeight(FontWeight.Bold)
        .layoutWeight(1)
        .textAlign(TextAlign.Center)
      Blank().width(64)
    }
    .width('100%')
    .height(52)
    .padding({ left: 8, right: 8 })
    .backgroundColor('#FFFFFF')
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
