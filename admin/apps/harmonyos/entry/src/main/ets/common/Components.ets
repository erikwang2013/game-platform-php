/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 统计卡片图标名 → 模块内 media 资源。
 *
 * 图标名来自 /admin/v1/dashboard 的 stat.icon，是运行时字符串；而 $r() 只接受编译期字面量，
 * 所以动态资源名必须先收敛成有限集合。原先的 `$rawfile(`${icon}.png`)` 是典型的静默失败：
 * $rawfile 不做存在性校验，图标名写错或资源缺失只会渲染成空白，构建照过。
 */
export function statIcon(name: string): Resource {
  switch (name) {
    case 'person_add':
      return $r('app.media.ic_person_add');
    case 'bolt':
      return $r('app.media.ic_bolt');
    case 'description':
      return $r('app.media.ic_description');
    default:
      return $r('app.media.ic_people');
  }
}

/**
 * 统计卡片组件
 */
@Component
export struct StatCard {
  @Prop label: string = '';
  @Prop value: string = '';
  @Prop icon: string = 'people';
  @Prop color: string = '#1677FF';
  @Prop trend: number | null = null;

  build() {
    Column() {
      Row() {
        Image(statIcon(this.icon))
          .width(20)
          .height(20)
          .fillColor(this.color)
      }
      .justifyContent(FlexAlign.Start)

      Blank()

      Text(this.label)
        .fontSize(12)
        .fontColor('#666666')
        .margin({ top: 4 })

      Text(this.value)
        .fontSize(24)
        .fontWeight(FontWeight.Bold)
        .fontColor(this.color)
        .margin({ top: 2 })
    }
    .width('100%')
    .height(110)
    .padding(16)
    .backgroundColor('#FFFFFF')
    .borderRadius(8)
    .shadow({ radius: 4, color: '#00000010', offsetY: 2 })
  }
}

/**
 * 加载状态组件
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
    .height('100%')
  }
}

/**
 * 空状态组件
 */
@Component
export struct EmptyView {
  @Prop message: string = '暂无数据';

  build() {
    Column() {
      // 吉祥物小骰（Dicey）：原先指向并不存在的 rawfile/empty.png（空态一直无图），
      // 改为随模块编译的 media 资源——restool 会在编译期校验该引用，写错即构建失败
      Image($r('app.media.mascot'))
        .width(72)
        .height(60)
        .objectFit(ImageFit.Contain)

      Text(this.message)
        .fontSize(14)
        .fontColor('#999999')
        .margin({ top: 12 })
    }
    .justifyContent(FlexAlign.Center)
    .width('100%')
    .height('100%')
  }
}

/**
 * 错误状态组件
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
    .height('100%')
  }
}

/**
 * 底部版权栏
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
