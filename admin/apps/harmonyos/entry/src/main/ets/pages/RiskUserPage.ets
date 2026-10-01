/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { RiskService } from '../service/RiskService';
import { RiskUserItem, RiskTimelineResult, RiskTimelineItem } from '../model/RiskModels';
import { LoadingView, ErrorView, EmptyView, ResultDialog } from '../common/Components';
import { FormField, confirmAction } from '../common/FormDialog';
import { FormPayload } from '../model/DataModels';
import { PagerBar, ExtraActionDialog } from '../common/ResourceViews';
import { t, t1 } from '../i18n/Translations';

/**
 * 异常用户页 —— 列表 + 三个行内动作：冻结 / 解冻 / 时间线。
 *
 * 行标识是 **`user_id`（hashid）**，不是 id（RiskUserController 的队列来自 user_trust，主键是 user_id）。
 *
 * 两个资金动作的口径（本批最需要小心的地方）：
 *  · **冻结无请求体**（POST …/{hashid}/hold）：金额由**服务端**取钱包可用余额全额，本页只发路径。
 *  · **解冻金额是 bcmath 十进制字符串**（POST …/{hashid}/release {amount}）：客户端**原样收发**，
 *    不做任何算术、不过 Number()（过一道浮点就丢精度）；空串 = 不带该参数 = 全额释放。
 *    实际释放量以回执 `released_amount` 为准（受冻结子台账剩余量约束，可能小于请求量）。
 *  · 两个动作都会**动钱** ⇒ 发出前必须二次确认，且确认文案要指名是谁（用户名 + user_id）。
 *
 * 端点：GET  /admin/v1/risk/users?page=&size=（响应 {total, items}，**没有 /list 段**）
 *       POST /admin/v1/risk/users/{hashid}/hold
 *       POST /admin/v1/risk/users/{hashid}/release {amount?}
 *       GET  /admin/v1/risk/users/{hashid}/timeline
 */

/**
 * 解冻表单：amount 用 text 而不是 number —— number 控件会过 Number()（float），金额只能原样传字符串。
 *
 * 三个文案字段存的是**键名**不是译文（`FormDialog` 渲染时对 label/hint/placeholder 各调一次 `t()`）。
 * 这里**不能**在模块顶层调 `t()` 取成字符串：模块求值发生在 `LocaleController.init()` 之前，
 * 会把启动时的默认语言固化进常量，切换语言后这两条文案再也不变（同 `RULE_ACTION_OPTIONS` 的取舍）。
 */
const RELEASE_FIELDS: FormField[] = [
  {
    name: 'amount', label: 'risk.user.field_amount', type: 'text',
    hint: 'risk.user.amount_hint',
    placeholder: 'risk.user.amount_placeholder'
  }
];

function timelineLines(result: RiskTimelineResult): string[] {
  const lines: string[] = [];
  const events = result.events ?? [];
  lines.push(t1('risk.user.timeline_total', 'total', `${result.total}`));
  for (let i = 0; i < events.length; i++) {
    const e: RiskTimelineItem = events[i];
    const resultText = e.result.length > 0 ? ` · ${e.result}` : '';
    const detail = e.detail.length > 0 ? ` · ${e.detail}` : '';
    lines.push(`${e.time} · ${e.source} · ${e.type} · ${e.action}${resultText}${detail}`);
  }
  if (events.length === 0) {
    lines.push(t('risk.user.timeline_empty'));
  }
  return lines;
}

@Entry
@Component
struct RiskUserPage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State rows: RiskUserItem[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  /** 正在处理的行 user_id（冻结/时间线是直接发的，用它在按钮上挡连点） */
  @State busyId: string = '';
  /** 解冻弹框 */
  @State releaseOpen: boolean = false;
  /** 只读结果弹框（冻结回执 / 时间线） */
  @State resultOpen: boolean = false;
  @State resultTitle: string = '';
  @State resultLines: string[] = [];
  /** 解冻对象是谁（确认文案必须指名） */
  @State releaseWho: string = '';
  private releaseId: string = '';
  private readonly limit: number = 15;

  aboutToAppear(): void {
    this.loadData(true);
  }

  async loadData(reset: boolean = false): Promise<void> {
    if (reset) {
      this.page = 1;
    }
    this.isLoading = true;
    this.hasError = false;
    try {
      const result = await RiskService.listRiskUsers(this.page, this.limit);
      this.rows = result.items ?? [];
      this.total = result.total ?? 0;
    } catch (e) {
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      this.hasError = true;
      promptAction.showToast({ message: t('common.load_failed') });
    } finally {
      this.isLoading = false;
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

  private redirectIfUnauthorized(e: Object): boolean {
    if (e instanceof Error && e.message === 'UNAUTHORIZED') {
      router.replaceUrl({ url: 'pages/LoginPage' });
      return true;
    }
    return false;
  }

  /** 主体是谁：优先用户名，退 hashid（确认文案不能只说「该用户」，出错时说不清动了谁的钱） */
  private who(item: RiskUserItem): string {
    return item.username.length > 0 ? item.username : item.user_id;
  }

  private openResult(title: string, lines: string[]): void {
    this.resultTitle = title;
    this.resultLines = lines;
    this.resultOpen = true;
  }

  /** 冻结：无请求体，金额服务端算 —— 先问一句再发（动钱的动作） */
  private async holdRow(item: RiskUserItem): Promise<void> {
    const ok = await confirmAction(t('risk.user.hold_title'),
      t('risk.user.hold_confirm').replace('{who}', this.who(item)).replace('{id}', item.user_id),
      t('risk.user.action_hold'), '#FF4D4F');
    if (!ok) {
      return;
    }
    this.busyId = item.user_id;
    try {
      const lines = await RiskService.holdUser(item.user_id);
      this.openResult(t1('risk.user.hold_result_titled', 'who', this.who(item)), lines);
      this.loadData(true);
    } catch (e) {
      this.reportError(e);
    } finally {
      this.busyId = '';
    }
  }

  private openRelease(item: RiskUserItem): void {
    this.releaseId = item.user_id;
    this.releaseWho = this.who(item);
    this.releaseOpen = true;
  }

  /** 解冻：金额原样上送（空串 = 全额）；发出前再确认一次，文案指名是谁、放多少 */
  private async submitRelease(id: string, body: FormPayload): Promise<string[]> {
    const amount = `${body['amount'] ?? ''}`.trim();
    const what = amount.length > 0 ? t1('risk.user.release_amount_unit', 'amount', amount)
      : t('risk.user.release_all');
    const ok = await confirmAction(t('risk.user.release_title'),
      t('risk.user.release_confirm').replace('{who}', this.releaseWho).replace('{id}', id).replace('{what}', what),
      t('risk.user.action_release'), '#FA8C16');
    if (!ok) {
      throw new Error(t('risk.user.release_cancelled'));
    }
    return RiskService.releaseUser(id, amount);
  }

  /** 时间线：只读，最多 200 条 ⇒ 结果框里滚动看 */
  private async timelineRow(item: RiskUserItem): Promise<void> {
    this.busyId = item.user_id;
    try {
      const result = await RiskService.timeline(item.user_id);
      this.openResult(t1('risk.user.timeline_titled', 'who', this.who(item)), timelineLines(result));
    } catch (e) {
      this.reportError(e);
    } finally {
      this.busyId = '';
    }
  }

  private reportError(e: Object): void {
    if (this.redirectIfUnauthorized(e)) {
      return;
    }
    promptAction.showToast({ message: e instanceof Error && e.message.length > 0 ? e.message : t('res.op_failed') });
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      if (this.releaseOpen) {
        ExtraActionDialog({
          title: t1('risk.user.release_titled', 'who', this.releaseWho),
          fields: RELEASE_FIELDS,
          actionId: this.releaseId,
          submit: (id: string, body: FormPayload): Promise<string[]> => this.submitRelease(id, body),
          onClose: () => {
            this.releaseOpen = false;
            this.loadData(true);
          }
        })
      }

      if (this.resultOpen) {
        ResultDialog({
          title: this.resultTitle,
          lines: this.resultLines,
          onClose: () => {
            this.resultOpen = false;
          }
        })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }

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

  @Builder
  mainView() {
    Column() {
      // 顶栏
      Row() {
        Button({ type: ButtonType.Circle }) {
          Image($r('app.media.ic_back')).width(24).height(24)
        }
        .width(36).height(36)
        .backgroundColor('#FFFFFF')
        .onClick(() => router.back())

        Text(t('risk.index.user'))
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadData() });
      } else if (this.rows.length === 0) {
        EmptyView({ message: t('risk.user.empty') });
      } else {
        List() {
          ForEach(this.rows, (item: RiskUserItem) => {
            ListItem() {
              this.userRow(item)
            }
          }, (item: RiskUserItem) => item.user_id)
        }
        .width('100%')
        .layoutWeight(1)
        .edgeEffect(EdgeEffect.Spring)
      }

      if (!this.isLoading && !this.hasError && this.total > this.limit) {
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

  /** 一行异常用户：用户名 + 信任分band 徽标 + 摘要 + 三个动作（冻结/解冻/时间线） */
  @Builder
  userRow(item: RiskUserItem) {
    Column() {
      Row() {
        Text(this.who(item))
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        Text(item.whitelisted === 1 ? t('risk.user.whitelisted') : item.band)
          .fontSize(10)
          .fontColor(item.whitelisted === 1 ? '#52C41A' : '#FA8C16')
          .backgroundColor(item.whitelisted === 1 ? '#E6FFF0' : '#FFF2E6')
          .borderRadius(2)
          .padding({ left: 6, right: 6, top: 2, bottom: 2 })
      }
      .width('100%')

      Row() {
        Text(t('risk.user.summary').replace('{score}', `${item.score}`)
          .replace('{hits}', `${item.hit_count}`)
          .replace('{last}', item.last_hit_at).replace('{id}', item.user_id))
          .fontSize(12)
          .fontColor('#999999')
          .maxLines(2)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .layoutWeight(1)

        this.smallButton(t('risk.user.action_hold'), '#FF4D4F', '#FFE6E6', this.busyId !== item.user_id, () => {
          this.holdRow(item);
        });
        this.smallButton(t('risk.user.action_release'), '#FA8C16', '#FFF2E6', this.busyId !== item.user_id, () => {
          this.openRelease(item);
        });
        this.smallButton(t('risk.user.action_timeline'), '#1677FF', '#E6F0FF', this.busyId !== item.user_id, () => {
          this.timelineRow(item);
        });
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
