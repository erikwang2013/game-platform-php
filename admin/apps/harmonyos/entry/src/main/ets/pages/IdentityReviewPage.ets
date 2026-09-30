/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { IdentityService } from '../service/DataService';
import { IdentityItem, FormPayload, FormValues } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { FormDialog, FormField, confirmAction } from '../common/FormDialog';
import { PagerBar } from '../common/ResourceViews';

/**
 * KYC 身份审核页 —— 列表 + 「审核」行动作。
 *
 * **为什么不套 ResourceModules 那套配置驱动**：那套的契约只认「表单 CRUD + 单个无 body 的
 * 行内动作」，而本模块的审核是 PUT /identity/review（id 在 **body** 里、动作值在「结论」字段里），
 * 且只有 pending 行才该出现按钮。要在共用页上表达它得给契约加三个开关（动作方法/动作字段体/
 * 动作按钮可条件出现），反而比单独一页贵。故本页只复用底座里真正中性的三样：
 * FormDialog（审核表单）、confirmAction（驳回二次确认）、PagerBar / 三种状态视图。
 *
 * 端点（admin/app/admin/v1/controller/IdentityController.php）：
 *   列表 GET  /admin/v1/identity/list?page=&limit=（另有 status 过滤，本页不挂筛选）
 *   审核 PUT  /admin/v1/identity/review  {id: hashid, action: approve|reject, note: ≤500}
 * 审核只推进 pending（控制器用 CAS：并发两人审同一单只有一个拿到 affected=1）。
 */

const REVIEW_FIELDS: FormField[] = [
  {
    name: 'action', label: '审核结论', type: 'select', required: true,
    options: [
      { label: '通过', value: 'approve' },
      { label: '驳回', value: 'reject' }
    ]
  },
  {
    name: 'note', label: '审核备注', type: 'textarea',
    placeholder: '最长 500 字；驳回时这段文字会原样发给用户'
  }
];

/** 空预填值：结论**故意不预选** —— 审核是判断，不该有个默认答案（必填校验会拦下没选的提交） */
const REVIEW_INITIAL: FormValues = {};

function identityStatusText(status: string): string {
  if (status === 'approved') {
    return '已通过';
  }
  return status === 'rejected' ? '已驳回' : '待审核';
}

/** 三态徽标配色：ResourceRowItem 那个绿/红二元 chip 表达不了「还没审」 */
function identityStatusColor(status: string): string {
  if (status === 'approved') {
    return '#52C41A';
  }
  return status === 'rejected' ? '#FF4D4F' : '#FA8C16';
}

@Entry
@Component
struct IdentityReviewPage {
  @State rows: IdentityItem[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  /** 正在处理的行 id：禁用该行按钮，防连点 */
  @State busyId: string = '';
  @State formOpen: boolean = false;
  /** 被审对象（用户名），进驳回确认文案 —— 二次确认必须说清审的是谁 */
  @State formWho: string = '';
  private reviewId: string = '';
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
      const result = await IdentityService.getList(this.page, this.limit);
      this.rows = result.list;
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

  /** KYC 主体是谁：优先登录名，退昵称……KYC 记录只有这两样能对上人 */
  private who(item: IdentityItem): string {
    const user = item.user;
    // 三态：undefined（未来若控制器改成不带动）/ null（关联查不到，**实测是这条**）都要防
    if (user !== undefined && user !== null && user.username.length > 0) {
      return user.username;
    }
    return '该用户';
  }

  private subtitle(item: IdentityItem): string {
    const parts: string[] = [];
    parts.push(this.who(item));
    const realName = item.real_name ?? '';
    if (realName.length > 0) {
      parts.push(realName);
    }
    if (item.id_type.length > 0) {
      parts.push(item.id_type);
    }
    const country = item.country ?? '';
    if (country.length > 0) {
      parts.push(country);
    }
    parts.push(item.created_at);
    // 已审过的把结论备注带上：驳回原因是对上下文最有用的那条信息
    const note = item.review_note ?? '';
    if (item.status !== 'pending' && note.length > 0) {
      parts.push(`备注：${note}`);
    }
    return parts.join(' · ');
  }

  private openReview(item: IdentityItem): void {
    this.reviewId = item.id;
    this.formWho = this.who(item);
    this.formOpen = true;
  }

  /**
   * 提交审核。驳回是危险动作 ⇒ 发出去之前再问一句（文案带对象标识）；
   * 用户在确认框里取消时抛错 ⇒ FormDialog 不关框，用户可以把结论改成「通过」再提交。
   */
  private async submitReview(body: FormPayload): Promise<void> {
    const action = `${body['action'] ?? ''}`;
    if (action === 'reject') {
      const ok = await confirmAction('确认驳回',
        `确定驳回「${this.formWho}」的身份认证吗？结论会通知用户，且审核后不能再改。`,
        '驳回', '#FF4D4F');
      if (!ok) {
        throw new Error('已取消驳回，未提交任何修改');
      }
    }
    try {
      await IdentityService.review(this.reviewId, action, `${body['note'] ?? ''}`);
    } catch (e) {
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      // ArkTS 只许 throw Error 及其子类，故原样转出给弹框显示服务端 message
      throw e as Error;
    }
    promptAction.showToast({ message: action === 'approve' ? '已通过' : '已驳回' });
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      // 审核表单：结论 + 备注。挂载时才读 initial，故每次开框都是新快照
      if (this.formOpen) {
        FormDialog({
          title: `审核「${this.formWho}」的身份认证`,
          fields: REVIEW_FIELDS,
          initial: REVIEW_INITIAL,
          onSubmit: (body: FormPayload) => this.submitReview(body),
          onDone: () => {
            this.formOpen = false;
            this.loadData(true);
          },
          onCancel: () => {
            this.formOpen = false;
          }
        })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
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

        Text('身份审核')
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
        EmptyView({ message: '暂无 KYC 申请' });
      } else {
        List() {
          ForEach(this.rows, (item: IdentityItem) => {
            ListItem() {
              this.reviewRow(item)
            }
          }, (item: IdentityItem) => item.id)
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

  /** 一行 KYC 记录：主行（用户名 + 状态）+ 摘要 + 行尾动作（待审核的可审） */
  @Builder
  reviewRow(item: IdentityItem) {
    Column() {
      Row() {
        Text(this.who(item))
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        Text(identityStatusText(item.status))
          .fontSize(10)
          .fontColor(identityStatusColor(item.status))
          .backgroundColor('#F5F5F5')
          .borderRadius(2)
          .padding({ left: 6, right: 6, top: 2, bottom: 2 })
      }
      .width('100%')

      Row() {
        Text(this.subtitle(item))
          .fontSize(12)
          .fontColor('#999999')
          .maxLines(2)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .layoutWeight(1)

        // 已审过的记录没有可做的动作（后端对非 pending 一律 422），故不画按钮
        if (item.status === 'pending') {
          Button('审核')
            .fontSize(12)
            .fontColor('#1677FF')
            .backgroundColor('#E6F0FF')
            .borderRadius(4)
            .height(28)
            .padding({ left: 10, right: 10 })
            .margin({ left: 6 })
            .enabled(this.busyId !== item.id)
            .onClick(() => {
              this.openReview(item);
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
