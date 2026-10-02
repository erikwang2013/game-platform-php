/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { t } from '../i18n/Translations';

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
  @StorageLink('app_locale') localeCode: string = 'zh';
  @Prop message: string = t('common.loading');

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
  @StorageLink('app_locale') localeCode: string = 'zh';
  @Prop message: string = t('common.no_data');

  build() {
    Column() {
      // 吉祥物小骰（Dicey）：240x200 的 6:5 比例，资源见 resources/base/media/mascot.svg
      Image($r('app.media.mascot'))
        .width(72)
        .height(60)
        .opacity(0.9)

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
 * 错误状态
 */
@Component
export struct ErrorView {
  @StorageLink('app_locale') localeCode: string = 'zh';
  @Prop message: string = t('common.load_failed');
  /**
   * 重试回调，由调用方传入（各页自己的 loadXxx）。
   * 不传时**整个按钮不渲染** —— 本组件此前是「按钮恒在、回调可为空」，漏传的调用点
   * 会渲染出一个点了没反应的死按钮；与选图等处的「不摆按不动的按钮」同一条约定。
   * 编译期拦不住漏传（plain 成员必须初始化、@Prop 无默认值时本版编译器都不报调用点），
   * 所以靠 check-errorview-retry.sh 数调用点。
   */
  onRetry?: () => void;

  build() {
    Column() {
      Text(this.message)
        .fontSize(14)
        .fontColor('#999999')

      if (this.onRetry) {
        Button(t('common.retry'))
          .margin({ top: 12 })
          .fontSize(14)
          .onClick(() => {
            if (this.onRetry) {
              this.onRetry();
            }
          })
      }
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
  @StorageLink('app_locale') localeCode: string = 'zh';
  @Prop title: string = '';

  build() {
    Row() {
      Button(t('common.back'))
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
  @StorageLink('app_locale') localeCode: string = 'zh';
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
        Text(t('nav.hall'))
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
        Text(t('nav.wallet'))
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
        Text(t('nav.profile'))
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
