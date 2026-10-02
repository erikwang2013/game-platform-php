/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { WithdrawOrderItem } from '../model/DataModels';
import { t } from '../i18n/Translations';

/**
 * 提现页的行与行内小按钮 —— 从 pages/WithdrawManagePage.ets 搬来（那份 644 行，越过本仓 500 行上限）。
 *
 * 搬的判据是**与页面状态无关**：行只吃 item / selected / busyId 三个入参，四个资金动作一律经回调
 * 交回页面 —— 二次确认、busyId 防连点、批量勾选集都留在页面（那是页面的状态，不是行的）。
 * 金额与订单标识两个纯函数也搬来这里：页面的二次确认文案与行内显示用的是**同一份**实现
 * （提现页的确认文案必带订单号 + 金额，两处各写一遍迟早对不上）。
 *
 * 行内小按钮抽成组件而不是全局 @Builder：全局 @Builder 的入参是传值的，语言切换时不会重渲染；
 * @Prop 会（本树所有要跟语言的组件都是这个形状，见 RowActionBar）。
 */

/** 每次渲染重新查表 ⇒ 语言切换即时生效（顶层调 t() 会把启动语言固化进常量） */
export function statusText(status: string): string {
  if (status === 'pending') {
    return t('wd.status_pending');
  }
  if (status === 'approved') {
    return t('wd.status_approved');
  }
  if (status === 'processing') {
    return t('wd.status_processing');
  }
  if (status === 'rejected') {
    return t('wd.status_rejected');
  }
  return status === 'completed' ? t('wd.status_completed') : status;
}

/** 五态各有各的颜色：待审核要醒目、已驳回是退回、已完成是终态 */
export function statusColor(status: string): string {
  if (status === 'pending') {
    return '#FA8C16';
  }
  if (status === 'rejected') {
    return '#FF4D4F';
  }
  return status === 'completed' ? '#52C41A' : '#1677FF';
}

/** 订单标识：订单号为空时回落 hashid（后端有一批历史单没单号） */
export function withdrawOrderLabel(item: WithdrawOrderItem): string {
  const no = item.order_no ?? '';
  return no.length > 0 ? no : item.id;
}

/** 金额文案：平台币 + 法币（都是服务端给的字符串，原样拼，不做换算） */
export function withdrawAmountText(item: WithdrawOrderItem): string {
  const fiat = item.fiat_amount.length > 0
    ? t('wd.amount_fiat').replace('{amount}', item.fiat_amount).replace('{currency}', item.currency)
    : '';
  return t('wd.amount_platform').replace('{amount}', item.platform_amount).replace('{extra}', fiat);
}

/**
 * 状态过滤的候选项：'' = 全部（控制器 status 为空时不加 where）。processing 是打款 CAS 写入的在途态。
 * 存**键名**不是译文 —— 渲染处（Select.value 与 options）各查一次表。
 * 下标到查询值的映射（STATUS_VALUES）留在页面：那是查询串的事，不是这个控件的事。
 */
const STATUS_FILTERS: string[] = [
  'wd.status_all', 'wd.status_pending', 'wd.status_approved', 'wd.status_processing',
  'wd.status_rejected', 'wd.status_completed'
];

/** 全局开关条：关掉之后用户侧不能再提交提现申请（可逆操作，故不拦二次确认） */
@Component
export struct WithdrawSwitchBar {
  @StorageLink('app_locale') localeCode: string = 'en';
  /** 开关当前读数（真值来自服务端，不是本地翻转） */
  @Prop isOn: boolean = false;
  /** 读数是否已回来（没回来＝文案「读取中」且不可点） */
  @Prop loaded: boolean = false;
  @Prop busy: boolean = false;
  onToggle?: (isOn: boolean) => void;

  build() {
    Row() {
      Column() {
        Text(t('wd.switch_label'))
          .fontSize(15)
          .fontColor('#333333')

        Text(this.loaded
          ? (this.isOn ? t('wd.switch_open') : t('wd.switch_closed'))
          : t('wd.switch_loading'))
          .fontSize(12)
          .fontColor('#999999')
          .margin({ top: 2 })
      }
      .alignItems(HorizontalAlign.Start)
      .layoutWeight(1)

      Toggle({ type: ToggleType.Switch, isOn: this.isOn })
        .enabled(this.loaded && !this.busy)
        .onChange((isOn: boolean) => {
          if (this.onToggle) {
            this.onToggle(isOn);
          }
        })
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 10, bottom: 10 })
    .backgroundColor('#FFFFFF')
  }
}

/** 状态过滤：订单列表天然按状态看，切一下就少翻几页 */
@Component
export struct WithdrawFilterBar {
  @StorageLink('app_locale') localeCode: string = 'en';
  @Prop index: number = 0;
  /** 换了过滤项：页面回第 1 页重拉（清空勾选也归它） */
  onPick?: (index: number) => void;

  private options(): SelectOption[] {
    const options: SelectOption[] = [];
    for (let i = 0; i < STATUS_FILTERS.length; i++) {
      options.push({ value: t(STATUS_FILTERS[i]) });
    }
    return options;
  }

  build() {
    Row() {
      Text(t('wd.filter_label'))
        .fontSize(14)
        .fontColor('#666666')

      Select(this.options())
        .value(t(STATUS_FILTERS[this.index]))
        .selected(this.index)
        .layoutWeight(1)
        .margin({ left: 12 })
        .onSelect((index: number) => {
          if (this.onPick) {
            this.onPick(index);
          }
        })
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 8, bottom: 8 })
    .backgroundColor('#FFFFFF')
  }
}

/** 行内小按钮（行内动作与批量条共用）：文案 / 配色 / 可用性都由调用点给 */
@Component
export struct SmallButton {
  @Prop text: string = '';
  @Prop color: string = '';
  @Prop bg: string = '';
  /** 禁用（提交中）：不响应点击。**不能叫 enabled** —— 那是 ArkUI 组件的自带成员名，
   * @Prop 重名会报「not assignable to the same property in base type 'CustomComponent'」*/
  @Prop disabled: boolean = false;
  action?: () => void;

  build() {
    Button(this.text)
      .fontSize(12)
      .fontColor(this.color)
      .backgroundColor(this.bg)
      .borderRadius(4)
      .height(28)
      .padding({ left: 10, right: 10 })
      .margin({ left: 6 })
      .enabled(!this.disabled)
      .onClick(() => {
        if (this.action) {
          this.action();
        }
      })
  }
}

@Component
export struct WithdrawOrderRow {
  /** 语言变更要重渲染本行（状态徽标与按钮文案都由 t() 出） */
  @StorageLink('app_locale') localeCode: string = 'en';
  /**
   * 本行数据。@Prop 而非普通成员：重拉列表后同一个 id 会换成**新对象**，普通成员只在构造时读一次
   * ⇒ 审核完刷新，行上还挂着旧状态（徽标不变、按钮还是不该点的那些）。缺省值只为满足 ArkTS
   * 要求的成员初始化。
   */
  @Prop item: WithdrawOrderItem = {
    id: '', user_id: 0, platform_amount: '', fiat_amount: '', currency: '', method: '', status: '', created_at: ''
  };
  /** 本行被勾中（批量审核只处理待审核行） */
  @Prop selected: boolean = false;
  /** 正在跑的动作标识：'batch' = 批量条在跑、等于本行 id = 本行在跑（各自的按钮各自禁用） */
  @Prop busyId: string = '';
  onToggleSelect?: (id: string) => void;
  onReview?: (item: WithdrawOrderItem, action: string) => void;
  onConfirm?: (item: WithdrawOrderItem) => void;
  onPayout?: (item: WithdrawOrderItem) => void;
  onSync?: (item: WithdrawOrderItem) => void;

  private subtitle(item: WithdrawOrderItem): string {
    const parts: string[] = [];
    // user 可能是 null（关联查不到时 toArray 带出 null）——只判 undefined 会在这种行上抛
    const user = item.user;
    parts.push(user !== undefined && user !== null
      ? user.username : t('wd.user_fallback').replace('{id}', `${item.user_id}`));
    if (item.method.length > 0) {
      parts.push(item.method);
    }
    const payout = item.payout_status ?? '';
    if (payout.length > 0) {
      parts.push(t('wd.subtitle_payout').replace('{status}', payout));
    }
    parts.push(withdrawOrderLabel(item));
    parts.push(item.created_at);
    return parts.join(' · ');
  }

  /* ------------------------------ 行内动作门禁 ------------------------------ */

  /** 只有待审核能审（approved/rejected/completed 都已经过了审核这一步） */
  private canReview(item: WithdrawOrderItem): boolean {
    return item.status === 'pending';
  }

  /**
   * 复核确认（双审第二票）：后端只在「已有第一审核人（reviewer_id>0）且不是本人」时才放行。
   * 列表不回传开关状态，故这里只用 review_id>0 当作「初审已过、等第二票」的显式信号；
   * 双审没开时点它会拿到 422「双重审核未启用」——把后端原话摆出来，不猜。
   */
  private canConfirm(item: WithdrawOrderItem): boolean {
    return item.status === 'pending' && (item.reviewer_id ?? 0) > 0;
  }

  /** 只有已通过能打款 */
  private canPayout(item: WithdrawOrderItem): boolean {
    return item.status === 'approved';
  }

  /** 已提交过 PayPal（有批次号）才谈得上同步，否则后端 422「尚未执行打款」 */
  private canSync(item: WithdrawOrderItem): boolean {
    return (item.payout_batch_id ?? '').length > 0;
  }

  private toggleSelect(): void {
    if (this.onToggleSelect) {
      this.onToggleSelect(this.item.id);
    }
  }

  /** 一行提现单：状态徽标 + 金额 + 用户/订单号 + 行尾动作（按状态与批次号收窄） */
  build() {
    Column() {
      Row() {
        // 待审核行可勾（批量只处理待审核：后端 where status='pending' 逐单跳过其余）
        if (this.canReview(this.item)) {
          Checkbox()
            .select(this.selected)
            .width(18)
            .height(18)
            .margin({ right: 8 })
            .enabled(this.busyId !== 'batch')
            .onChange(() => {
              this.toggleSelect();
            })
        }

        Text(withdrawAmountText(this.item))
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        Text(statusText(this.item.status))
          .fontSize(10)
          .fontColor(statusColor(this.item.status))
          .backgroundColor('#F5F5F5')
          .borderRadius(2)
          .padding({ left: 6, right: 6, top: 2, bottom: 2 })
      }
      .width('100%')

      Row() {
        Text(this.subtitle(this.item))
          .fontSize(12)
          .fontColor('#999999')
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .layoutWeight(1)

        if (this.canReview(this.item)) {
          SmallButton({
            text: t('wd.action_approve'),
            color: '#52C41A',
            bg: '#E6FFF0',
            disabled: this.busyId === this.item.id,
            action: () => {
              if (this.onReview) {
                this.onReview(this.item, 'approve');
              }
            }
          })
          SmallButton({
            text: t('wd.action_reject'),
            color: '#FF4D4F',
            bg: '#FFE6E6',
            disabled: this.busyId === this.item.id,
            action: () => {
              if (this.onReview) {
                this.onReview(this.item, 'reject');
              }
            }
          })
        }

        // 双审第二票：初审已记（reviewer_id>0）的待审核单才渲染
        if (this.canConfirm(this.item)) {
          SmallButton({
            text: t('wd.action_recheck'),
            color: '#1677FF',
            bg: '#E6F0FF',
            disabled: this.busyId === this.item.id,
            action: () => {
              if (this.onConfirm) {
                this.onConfirm(this.item);
              }
            }
          })
        }

        if (this.canPayout(this.item)) {
          SmallButton({
            text: t('wd.action_payout'),
            color: '#FA8C16',
            bg: '#FFF2E6',
            disabled: this.busyId === this.item.id,
            action: () => {
              if (this.onPayout) {
                this.onPayout(this.item);
              }
            }
          })
        }

        if (this.canSync(this.item)) {
          SmallButton({
            text: t('wd.action_sync'),
            color: '#1677FF',
            bg: '#E6F0FF',
            disabled: this.busyId === this.item.id,
            action: () => {
              if (this.onSync) {
                this.onSync(this.item);
              }
            }
          })
        }
      }
      .width('100%')
      .margin({ top: 8 })
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 10, bottom: 10 })
    .alignItems(HorizontalAlign.Start)
    .backgroundColor('#FFFFFF')
    .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
  }
}
