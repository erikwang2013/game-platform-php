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
 * 只读结果弹框 —— 展示「有话说」的动作回执（沙箱试算结果 / 风控时间线 / 团伙成员 / 检测候选）。
 *
 * 为什么不复用 FormDialog：它是可编辑表单（字段 + 取消/确定 + 提交态），而这些结果没有入参、
 * 只有一个「关闭」——硬塞进去要给 FormDialog 加一个「只读模式」分支，而这里一个组件就够。
 * lines 是一行一条的纯文本（键值用「 · 」拼），页面负责拼内容，本组件不认识任何业务。
 */
@Component
export struct ResultDialog {
  @Prop title: string = '';
  @Prop lines: string[] = [];
  onClose?: () => void;

  build() {
    Stack({ alignContent: Alignment.Center }) {
      // 遮罩与卡片是 Stack 兄弟节点：卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      Column()
        .width('100%')
        .height('100%')
        .backgroundColor('#00000066')
        .onClick(() => {
          if (this.onClose) {
            this.onClose();
          }
        })

      Column() {
        Text(this.title)
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .margin({ bottom: 12 })

        Scroll() {
          Column() {
            ForEach(this.lines, (line: string) => {
              Text(line)
                .fontSize(12)
                .fontColor('#333333')
                .width('100%')
                .margin({ bottom: 4 })
            }, (line: string, index: number) => `${index}:${line}`)
          }
          .width('100%')
          .alignItems(HorizontalAlign.Start)
        }
        .width('100%')
        // 行数不定（时间线最多 200 行）：框内滚动，别把卡片撑出屏幕
        .constraintSize({ maxHeight: 420 })

        Button('关闭')
          .fontSize(14)
          .fontColor('#666666')
          .backgroundColor('#F5F5F5')
          .width('100%')
          .margin({ top: 14 })
          .onClick(() => {
            if (this.onClose) {
              this.onClose();
            }
          })
      }
      .width(340)
      .padding(16)
      .backgroundColor(Color.White)
      .borderRadius(12)
    }
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
