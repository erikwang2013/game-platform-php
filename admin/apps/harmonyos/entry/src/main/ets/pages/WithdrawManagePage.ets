/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { WithdrawService } from '../service/DataService';
import { WithdrawOrderItem } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { confirmAction } from '../common/FormDialog';
import { PagerBar } from '../common/ResourceViews';

/**
 * 提现管理页 —— 全局开关 + 订单列表 + 四种**资金动作**（审核 / 复核确认 / 打款 / 同步）+ 批量审核。
 *
 * **为什么不套 FundModules 那套配置驱动**：本模块没有 CRUD —— 一张提现单不可新建、不可改、不可删，
 * 行上要挂的是各带不同入参的四个动作，而 ResourceModule 契约只认「新建 + 局部更新 + 删除 +
 * 一个无 body 的行内动作」。表达它要给契约加「行内多动作」整层（与工单页同一判断），故本页只借
 * 底座里中性那几样（confirmAction 做二次确认、PagerBar 与三种状态视图），行布局自己画。
 *
 * 端点（admin/app/admin/v1/controller/WithdrawController.php + WithdrawReviewTrait.php）：
 *   列表     GET  /admin/v1/withdraw/orders?page=&limit=&status=
 *   开关读   GET  /admin/v1/withdraw/switch      （与 PUT 共用 toggleSwitch，GET 分支回 switchState()）
 *   开关写   PUT  /admin/v1/withdraw/switch      {enabled: 0|1}
 *   审核     PUT  /admin/v1/withdraw/review      {order_id, action: approve|reject|confirm, note?}
 *   批量审核 POST /admin/v1/withdraw/batch-review {ids: [hashid], action: approve|reject, note?}
 *   执行打款 POST /admin/v1/withdraw/execute-payout {order_id}
 *   同步状态 POST /admin/v1/withdraw/sync-payout    {order_id}
 *
 * 三条贯穿本页的口径：
 *  1. **资金动作一律二次确认，文案带订单标识 + 金额**；确认后**读服务端 message**（WithdrawService
 *     四个方法的返回值就是它），不做乐观更新 —— 状态的唯一真值是服务端，动作返回后重新拉列表。
 *  2. 金额（platform_amount/fiat_amount 都是 DECIMAL 的 string cast）**只做展示与拼接，不做任何
 *     算术**：连「批量合计」都不算（前端求和要 float，金额禁 float）。批量确认文案改成逐单列出。
 *  3. 门禁按 order.status 与 payout_batch_id 收：只有 pending 能审、只有 approved 能打款、
 *     只有已提交过 PayPal（payout_batch_id 非空）才谈得上同步（否则后端 422「尚未执行打款」）。
 */

/** 状态过滤：'' = 全部（控制器 status 为空时不加 where）。processing 是打款 CAS 写入的在途态 */
const STATUS_FILTERS: string[] = ['全部', '待审核', '已通过', '打款中', '已驳回', '已完成'];
const STATUS_VALUES: string[] = ['', 'pending', 'approved', 'processing', 'rejected', 'completed'];

function statusText(status: string): string {
  if (status === 'pending') {
    return '待审核';
  }
  if (status === 'approved') {
    return '已通过';
  }
  if (status === 'processing') {
    return '打款中';
  }
  if (status === 'rejected') {
    return '已驳回';
  }
  return status === 'completed' ? '已完成' : status;
}

/** 五态各有各的颜色：待审核要醒目、已驳回是退回、已完成是终态 */
function statusColor(status: string): string {
  if (status === 'pending') {
    return '#FA8C16';
  }
  if (status === 'rejected') {
    return '#FF4D4F';
  }
  return status === 'completed' ? '#52C41A' : '#1677FF';
}

@Entry
@Component
struct WithdrawManagePage {
  @State orders: WithdrawOrderItem[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  /** 正在处理的行 id：禁用该行按钮，防连点（打款这类动作重复提交是有代价的） */
  @State busyId: string = '';
  /** 状态过滤下标（值见 STATUS_VALUES） */
  @State filterIndex: number = 0;
  /** 全局提现开关：真值来自服务端（GET 读数 / PUT 回执），不是本地翻转 */
  @State switchOn: boolean = false;
  @State switchLoaded: boolean = false;
  @State switchBusy: boolean = false;
  /** 批量勾选的订单 id（只有待审核行可勾）；换页/换过滤时清空 */
  @State selected: string[] = [];
  private readonly limit: number = 15;

  aboutToAppear(): void {
    this.loadData(true);
    this.loadSwitch();
  }

  async loadData(reset: boolean = false): Promise<void> {
    if (reset) {
      this.page = 1;
      // 勾选是按 id 存的：换页/换过滤后这些 id 可能已不在列表里，留着会「确认时看不见」
      this.selected = [];
    }
    this.isLoading = true;
    this.hasError = false;
    try {
      const result = await WithdrawService.getOrders(this.page, this.limit, STATUS_VALUES[this.filterIndex]);
      this.orders = result.list;
      this.total = result.total;
    } catch (e) {
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      this.hasError = true;
      promptAction.showToast({ message: '加载失败' });
    } finally {
      this.isLoading = false;
    }
  }

  async loadSwitch(): Promise<void> {
    try {
      this.switchOn = await WithdrawService.getSwitch();
      this.switchLoaded = true;
    } catch (e) {
      // 开关读数失败不该拖垮整个页面（订单列表照常）；开关保持禁用并等重试
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      this.switchLoaded = false;
    }
  }

  private pageCount(): number {
    return Math.max(1, Math.ceil(this.total / this.limit));
  }

  private goPage(delta: number): void {
    const next = this.page + delta;
    if (next < 1 || next > this.pageCount()) {
      return;
    }
    this.page = next;
    this.loadData();
  }

  /** 鉴权失效：回登录页并让调用方提前返回；返回值 = 是否已跳转 */
  private redirectIfUnauthorized(e: Object): boolean {
    if (e instanceof Error && e.message === 'UNAUTHORIZED') {
      router.replaceUrl({ url: 'pages/LoginPage' });
      return true;
    }
    return false;
  }

  /** 行内动作失败：提示服务端 message（鉴权失效则回登录页） */
  private reportError(e: Object): void {
    if (this.redirectIfUnauthorized(e)) {
      return;
    }
    promptAction.showToast({ message: e instanceof Error && e.message.length > 0 ? e.message : '操作失败' });
  }

  /** 订单标识：有订单号用订单号，没有退回 hashid（二次确认文案里必须带它） */
  private orderLabel(item: WithdrawOrderItem): string {
    const no = item.order_no ?? '';
    return no.length > 0 ? no : item.id;
  }

  /** 金额文案：平台币 + 法币（都是服务端给的字符串，原样拼，不做换算） */
  private amountText(item: WithdrawOrderItem): string {
    const fiat = item.fiat_amount.length > 0
      ? `（法币 ${item.fiat_amount} ${item.currency}）`
      : '';
    return `${item.platform_amount} 平台币${fiat}`;
  }

  private subtitle(item: WithdrawOrderItem): string {
    const parts: string[] = [];
    // user 可能是 null（关联查不到时 toArray 带出 null）——只判 undefined 会在这种行上抛
    const user = item.user;
    parts.push(user !== undefined && user !== null ? user.username : `用户 ${item.user_id}`);
    if (item.method.length > 0) {
      parts.push(item.method);
    }
    const payout = item.payout_status ?? '';
    if (payout.length > 0) {
      parts.push(`打款 ${payout}`);
    }
    parts.push(this.orderLabel(item));
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

  /* ------------------------------ 单笔资金动作 ------------------------------ */

  /** 审核（通过/驳回）—— 不可逆或带退款 ⇒ 二次确认，文案带订单标识 + 金额 */
  private reviewRow(item: WithdrawOrderItem, action: string): void {
    const title = action === 'reject' ? '确认驳回' : '确认通过';
    const body = action === 'reject'
      ? `确定驳回提现订单「${this.orderLabel(item)}」（${this.amountText(item)}）吗？\n将向该用户退还 ${item.platform_amount} 平台币，不可撤销。`
      : `确定通过提现订单「${this.orderLabel(item)}」（${this.amountText(item)}）吗？\n通过后该笔进入可打款状态。`;
    confirmAction(title, body, action === 'reject' ? '驳回' : '通过',
      action === 'reject' ? '#FF4D4F' : '#52C41A').then((ok: boolean) => {
      if (ok) {
        this.doReview(item.id, action);
      }
    });
  }

  private async doReview(orderId: string, action: string): Promise<void> {
    this.busyId = orderId;
    try {
      const message = await WithdrawService.review(orderId, action, '');
      promptAction.showToast({ message: message });
      this.loadData(true);
    } catch (e) {
      this.reportError(e);
    } finally {
      this.busyId = '';
    }
  }

  /** 复核确认（双审第二票）：同样二次确认 */
  private confirmRow(item: WithdrawOrderItem): void {
    confirmAction('确认复核',
      `确定复核确认提现订单「${this.orderLabel(item)}」（${this.amountText(item)}）吗？\n确认后该笔由另一位管理员放行。`,
      '确认', '#1677FF').then((ok: boolean) => {
      if (ok) {
        this.doReview(item.id, 'confirm');
      }
    });
  }

  /** 执行打款：外部副作用且不可撤销 ⇒ 二次确认 */
  private payoutRow(item: WithdrawOrderItem): void {
    confirmAction('确认打款',
      `确定对提现订单「${this.orderLabel(item)}」（${this.amountText(item)}）执行打款吗？\n款项将提交给 PayPal，提交后不可撤销。`,
      '打款', '#FF4D4F').then((ok: boolean) => {
      if (ok) {
        this.doPayout(item.id);
      }
    });
  }

  private async doPayout(orderId: string): Promise<void> {
    this.busyId = orderId;
    try {
      const message = await WithdrawService.executePayout(orderId);
      promptAction.showToast({ message: message });
      this.loadData(true);
    } catch (e) {
      this.reportError(e);
    } finally {
      this.busyId = '';
    }
  }

  /** 同步打款状态：会打 PayPal 并回写本地状态 ⇒ 同样二次确认 */
  private syncRow(item: WithdrawOrderItem): void {
    confirmAction('确认同步',
      `确定从 PayPal 同步提现订单「${this.orderLabel(item)}」（${this.amountText(item)}）的打款状态吗？`,
      '同步', '#1677FF').then((ok: boolean) => {
      if (ok) {
        this.doSync(item.id);
      }
    });
  }

  private async doSync(orderId: string): Promise<void> {
    this.busyId = orderId;
    try {
      // 这个端点的 message 是框架缺省 'success'（后端没给话术）⇒ 批次状态在 data.synced_status 里；
      // 这里仍原样提示服务端 message，同步结果以**重新拉到的列表**为准（不做乐观更新）
      const message = await WithdrawService.syncPayout(orderId);
      promptAction.showToast({ message: message });
      this.loadData(true);
    } catch (e) {
      this.reportError(e);
    } finally {
      this.busyId = '';
    }
  }

  /* ------------------------------- 批量审核 ------------------------------- */

  private isSelected(id: string): boolean {
    return this.selected.indexOf(id) >= 0;
  }

  private toggleSelected(id: string): void {
    const next: string[] = [];
    for (let i = 0; i < this.selected.length; i++) {
      if (this.selected[i] !== id) {
        next.push(this.selected[i]);
      }
    }
    if (!this.isSelected(id)) {
      next.push(id);
    }
    this.selected = next;
  }

  private orderById(id: string): WithdrawOrderItem | null {
    for (let i = 0; i < this.orders.length; i++) {
      if (this.orders[i].id === id) {
        return this.orders[i];
      }
    }
    return null;
  }

  /** 批量确认文案：逐单列出标识 + 金额（**不求和**：金额禁 float，前端不做算术） */
  private batchMessage(action: string): string {
    const lines: string[] = [];
    for (let i = 0; i < this.selected.length; i++) {
      const item = this.orderById(this.selected[i]);
      if (item !== null) {
        lines.push(`「${this.orderLabel(item)}」${this.amountText(item)}`);
      }
    }
    const verb = action === 'reject' ? '驳回' : '通过';
    const tail = action === 'reject' ? '（将逐单向用户退款，不可撤销）' : '（通过后进入可打款状态）';
    return `确定批量${verb}选中的 ${lines.length} 笔提现申请吗？\n${lines.join('\n')}\n${tail}`;
  }

  private batchRow(action: string): void {
    if (this.selected.length === 0) {
      return;
    }
    confirmAction(action === 'reject' ? '批量驳回' : '批量通过', this.batchMessage(action),
      action === 'reject' ? '驳回' : '通过',
      action === 'reject' ? '#FF4D4F' : '#52C41A').then((ok: boolean) => {
      if (ok) {
        this.doBatch(action);
      }
    });
  }

  private async doBatch(action: string): Promise<void> {
    this.busyId = 'batch';
    try {
      // 后端逐单处理并回报 "批量处理完成: N 笔（失败 M 笔）" ⇒ 原样提示，再重新拉列表
      const message = await WithdrawService.batchReview(this.selected, action, '');
      promptAction.showToast({ message: message });
      this.selected = [];
      this.loadData(true);
    } catch (e) {
      this.reportError(e);
    } finally {
      this.busyId = '';
    }
  }

  /* ------------------------------- 全局开关 ------------------------------- */

  /**
   * 开关翻转：先把控件状态跟着手势走（否则取消/失败时它停在错的位置），
   * PUT 回执里的 enabled 才是真值 —— 成功按回执落，失败按回执…拿不到就退回原值。
   */
  private async applySwitch(isOn: boolean): Promise<void> {
    this.switchOn = isOn;
    if (this.switchBusy) {
      return;
    }
    this.switchBusy = true;
    try {
      this.switchOn = await WithdrawService.setSwitch(isOn);
      promptAction.showToast({ message: this.switchOn ? '提现已开启' : '提现已关闭' });
    } catch (e) {
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      this.switchOn = !isOn;
      this.reportError(e);
    } finally {
      this.switchBusy = false;
    }
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

        Text('提现管理')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      this.switchBar()
      this.filterBar()

      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadData() });
      } else if (this.orders.length === 0) {
        EmptyView({ message: '暂无提现订单' });
      } else {
        List() {
          ForEach(this.orders, (item: WithdrawOrderItem) => {
            ListItem() {
              this.orderRow(item)
            }
          }, (item: WithdrawOrderItem) => item.id)
        }
        .width('100%')
        .layoutWeight(1)
        .edgeEffect(EdgeEffect.Spring)
      }

      if (this.selected.length > 0) {
        this.batchBar()
      } else if (!this.isLoading && !this.hasError && this.total > this.limit) {
        PagerBar({
          page: this.page,
          pageCount: this.pageCount(),
          onPrev: () => {
            this.goPage(-1);
          },
          onNext: () => {
            this.goPage(1);
          }
        })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }

  /** 全局开关条：关掉之后用户侧不能再提交提现申请（可逆操作，故不拦二次确认） */
  @Builder
  switchBar() {
    Row() {
      Column() {
        Text('全局提现开关')
          .fontSize(15)
          .fontColor('#333333')

        Text(this.switchLoaded ? (this.switchOn ? '提现开放中' : '提现已关闭，用户无法申请') : '读取中…')
          .fontSize(12)
          .fontColor('#999999')
          .margin({ top: 2 })
      }
      .alignItems(HorizontalAlign.Start)
      .layoutWeight(1)

      Toggle({ type: ToggleType.Switch, isOn: this.switchOn })
        .enabled(this.switchLoaded && !this.switchBusy)
        .onChange((isOn: boolean) => {
          this.applySwitch(isOn);
        })
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 10, bottom: 10 })
    .backgroundColor('#FFFFFF')
  }

  /** 状态过滤：订单列表天然按状态看，切一下就少翻几页 */
  @Builder
  filterBar() {
    Row() {
      Text('状态')
        .fontSize(14)
        .fontColor('#666666')

      Select(this.buildFilterOptions())
        .value(STATUS_FILTERS[this.filterIndex])
        .selected(this.filterIndex)
        .layoutWeight(1)
        .margin({ left: 12 })
        .onSelect((index: number) => {
          this.filterIndex = index;
          this.loadData(true);
        })
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 8, bottom: 8 })
    .backgroundColor('#FFFFFF')
  }

  private buildFilterOptions(): SelectOption[] {
    const options: SelectOption[] = [];
    for (let i = 0; i < STATUS_FILTERS.length; i++) {
      options.push({ value: STATUS_FILTERS[i] });
    }
    return options;
  }

  /** 行内小按钮（行内动作与批量条共用）：文案 / 配色 / 可用性都由调用点给 */
  @Builder
  smallButton(text: string, color: string, bg: string, enabled: boolean, action: () => void) {
    Button(text)
      .fontSize(12)
      .fontColor(color)
      .backgroundColor(bg)
      .borderRadius(4)
      .height(28)
      .padding({ left: 10, right: 10 })
      .margin({ left: 6 })
      .enabled(enabled)
      .onClick(action)
  }

  /** 批量操作条：勾了单才出现（占的正是分页条的位置，二者不会同时在屏幕上） */
  @Builder
  batchBar() {
    Row() {
      Text(`已选 ${this.selected.length} 笔`)
        .fontSize(13)
        .fontColor('#666666')
        .layoutWeight(1)

      this.smallButton('清空', '#666666', '#F5F5F5', this.busyId !== 'batch', () => {
        this.selected = [];
      });
      this.smallButton('批量通过', '#52C41A', '#E6FFF0', this.busyId !== 'batch', () => {
        this.batchRow('approve');
      });
      this.smallButton('批量驳回', '#FF4D4F', '#FFE6E6', this.busyId !== 'batch', () => {
        this.batchRow('reject');
      });
    }
    .width('100%')
    .height(56)
    .padding({ left: 16, right: 16 })
    .backgroundColor('#FFFFFF')
    .border({ width: { top: 0.5 }, color: '#EEEEEE' })
  }

  /** 一行提现单：状态徽标 + 金额 + 用户/订单号 + 行尾动作（按状态与批次号收窄） */
  @Builder
  orderRow(item: WithdrawOrderItem) {
    Column() {
      Row() {
        // 待审核行可勾（批量只处理待审核：后端 where status='pending' 逐单跳过其余）
        if (this.canReview(item)) {
          Checkbox()
            .select(this.isSelected(item.id))
            .width(18)
            .height(18)
            .margin({ right: 8 })
            .enabled(this.busyId !== 'batch')
            .onChange(() => {
              this.toggleSelected(item.id);
            })
        }

        Text(this.amountText(item))
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        Text(statusText(item.status))
          .fontSize(10)
          .fontColor(statusColor(item.status))
          .backgroundColor('#F5F5F5')
          .borderRadius(2)
          .padding({ left: 6, right: 6, top: 2, bottom: 2 })
      }
      .width('100%')

      Row() {
        Text(this.subtitle(item))
          .fontSize(12)
          .fontColor('#999999')
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .layoutWeight(1)

        if (this.canReview(item)) {
          this.smallButton('通过', '#52C41A', '#E6FFF0', this.busyId !== item.id, () => {
            this.reviewRow(item, 'approve');
          });
          this.smallButton('驳回', '#FF4D4F', '#FFE6E6', this.busyId !== item.id, () => {
            this.reviewRow(item, 'reject');
          });
        }

        // 双审第二票：初审已记（reviewer_id>0）的待审核单才渲染
        if (this.canConfirm(item)) {
          this.smallButton('复核确认', '#1677FF', '#E6F0FF', this.busyId !== item.id, () => {
            this.confirmRow(item);
          });
        }

        if (this.canPayout(item)) {
          this.smallButton('打款', '#FA8C16', '#FFF2E6', this.busyId !== item.id, () => {
            this.payoutRow(item);
          });
        }

        if (this.canSync(item)) {
          this.smallButton('同步', '#1677FF', '#E6F0FF', this.busyId !== item.id, () => {
            this.syncRow(item);
          });
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
