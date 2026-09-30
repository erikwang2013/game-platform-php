/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { TicketService } from '../service/DataService';
import { TicketItem, FormPayload, FormValues } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { FormDialog, FormField, confirmAction } from '../common/FormDialog';
import { PagerBar } from '../common/ResourceViews';

/**
 * 工单处理页 —— 列表 + 三种**行动作**（回复 / 指派 / 关闭）。
 *
 * **为什么不套 ResourceModules 那套配置驱动**：本模块没有 CRUD —— 没有新建、没有编辑、
 * 没有删除，行上要挂的是三个各带不同入参的动作（正文 / 受理人 / 无 body + 二次确认），
 * 而契约只认「新建 + 局部更新 + 删除 + 一个无 body 的行内动作」。表达它要给契约加
 * 「行内多动作」这一整层，比单独一页贵，故本页复用底座里中性那几样（FormDialog 做动作表单、
 * confirmAction 做关闭确认、PagerBar 与三种状态视图），行布局自己画。
 *
 * 端点（admin/app/admin/v1/controller/TicketController.php）：
 *   列表 GET  /admin/v1/ticket/list?page=&limit=（另有 status/type 过滤，本页不挂筛选）
 *   回复 POST /admin/v1/ticket/{hashid}/reply  {content}   —— 闭单后 422（按钮按 status 藏掉）
 *   指派 POST /admin/v1/ticket/{hashid}/assign {admin_id}  —— **管理员裸数字 ID**，不是 hashid
 *   关闭 POST /admin/v1/ticket/{hashid}/close  （无 body）
 */

const REPLY_FIELDS: FormField[] = [
  { name: 'content', label: '回复内容', type: 'textarea', required: true, placeholder: '将作为管理员回复发送给用户' }
];

/**
 * 受理人用 text 而非 number：控制器是 `(int) $request->input('admin_id')`，收的是 19 位雪花 ID，
 * 走 number 键盘会让 FormDialog 的 Number() 转换掉精度 —— 写进去的是另一个不存在的管理员。
 * 这里原样送字符串（PHP 侧的 (int) 精确），提交前再校验一次「纯数字」。
 */
const ASSIGN_FIELDS: FormField[] = [
  { name: 'admin_id', label: '受理人 ID', type: 'text', required: true, placeholder: '管理员数字 ID（不是列表里的 hashid）' }
];

const ASSIGN_INITIAL: FormValues = {};

function ticketStatusText(status: string): string {
  if (status === 'closed') {
    return '已关闭';
  }
  return status === 'replied' ? '已回复' : '待处理';
}

/** 三态各有各的颜色：待处理要醒目、已回复是过程态、已关闭是终态 */
function ticketStatusColor(status: string): string {
  if (status === 'closed') {
    return '#999999';
  }
  return status === 'replied' ? '#1677FF' : '#FA8C16';
}

/** 纯数字校验（不用正则字面量：ArkTS 限制规则禁 regexp literal） */
function isDigits(value: string): boolean {
  if (value.length === 0 || value.length > 19) {
    return false;
  }
  for (let i = 0; i < value.length; i++) {
    const code = value.charCodeAt(i);
    if (code < 48 || code > 57) {
      return false;
    }
  }
  return true;
}

@Entry
@Component
struct TicketManagePage {
  @State rows: TicketItem[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  /** 正在处理的行 id：禁用该行按钮，防连点 */
  @State busyId: string = '';
  @State formOpen: boolean = false;
  @State formTitle: string = '';
  @State formFields: FormField[] = [];
  /** 动作类型：reply / assign（关闭不走表单，只有一次确认） */
  private actionKind: string = 'reply';
  private actionId: string = '';
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
      const result = await TicketService.getList(this.page, this.limit);
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

  private subtitle(item: TicketItem): string {
    const parts: string[] = [];
    parts.push(item.user_name);
    if (item.type.length > 0) {
      parts.push(item.type);
    }
    parts.push(`回复 ${item.reply_count}`);
    // assigned_to 是裸 BIGINT（0 = 未指派），值班时看得到「这单归谁」才好接
    if (item.assigned_to > 0) {
      parts.push(`受理人 ${item.assigned_to}`);
    }
    parts.push(item.created_at);
    return parts.join(' · ');
  }

  private openReply(item: TicketItem): void {
    this.actionKind = 'reply';
    this.actionId = item.id;
    this.formTitle = `回复「${item.subject}」`;
    this.formFields = REPLY_FIELDS;
    this.formOpen = true;
  }

  private openAssign(item: TicketItem): void {
    this.actionKind = 'assign';
    this.actionId = item.id;
    this.formTitle = `指派「${item.subject}」`;
    this.formFields = ASSIGN_FIELDS;
    this.formOpen = true;
  }

  /** 回复 / 指派共用一次提交（动作类型在 actionKind 里） */
  private async submitAction(body: FormPayload): Promise<void> {
    if (this.actionKind === 'assign') {
      const adminId = `${body['admin_id'] ?? ''}`.trim();
      // 边界校验：hashid 落到 (int) 上要么变 0（静默未指派）要么变成另一个管理员的裸 id
      if (!isDigits(adminId)) {
        throw new Error('受理人 ID 必须是纯数字的管理员 ID');
      }
      try {
        await TicketService.assign(this.actionId, adminId);
      } catch (e) {
        if (this.redirectIfUnauthorized(e)) {
          return;
        }
        throw e as Error;
      }
      promptAction.showToast({ message: '已指派' });
      return;
    }

    try {
      await TicketService.reply(this.actionId, `${body['content'] ?? ''}`);
    } catch (e) {
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      throw e as Error;
    }
    promptAction.showToast({ message: '已回复' });
  }

  /** 关闭：危险动作（终态、不可再回复）⇒ 二次确认，文案带工单标题 */
  private closeRow(item: TicketItem): void {
    confirmAction('确认关闭',
      `确定关闭工单「${item.subject}」吗？关闭后不能再回复。`, '关闭', '#FF4D4F')
      .then((ok: boolean) => {
        if (ok) {
          this.doClose(item);
        }
      });
  }

  private async doClose(item: TicketItem): Promise<void> {
    this.busyId = item.id;
    try {
      await TicketService.close(item.id);
      promptAction.showToast({ message: '已关闭' });
      this.loadData(true);
    } catch (e) {
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      promptAction.showToast({
        message: e instanceof Error && e.message.length > 0 ? e.message : '操作失败'
      });
    } finally {
      this.busyId = '';
    }
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      // 动作表单（回复正文 / 受理人 ID）：挂载时才读 initial，故每次开框都是新快照
      if (this.formOpen) {
        FormDialog({
          title: this.formTitle,
          fields: this.formFields,
          initial: ASSIGN_INITIAL,
          onSubmit: (body: FormPayload) => this.submitAction(body),
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

        Text('工单管理')
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
        EmptyView({ message: '暂无工单' });
      } else {
        List() {
          ForEach(this.rows, (item: TicketItem) => {
            ListItem() {
              this.ticketRow(item)
            }
          }, (item: TicketItem) => item.id)
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

  /** 一行工单：主行（标题 + 状态）+ 摘要 + 行尾动作（回复/指派/关闭） */
  @Builder
  ticketRow(item: TicketItem) {
    Column() {
      Row() {
        Text(item.subject)
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        Text(ticketStatusText(item.status))
          .fontSize(10)
          .fontColor(ticketStatusColor(item.status))
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

        // 闭单后回复与再次关闭都是无意义（回复还会被后端 422），只留指派
        if (item.status !== 'closed') {
          Button('回复')
            .fontSize(12)
            .fontColor('#1677FF')
            .backgroundColor('#E6F0FF')
            .borderRadius(4)
            .height(28)
            .padding({ left: 10, right: 10 })
            .margin({ left: 6 })
            .enabled(this.busyId !== item.id)
            .onClick(() => {
              this.openReply(item);
            })
        }

        Button('指派')
          .fontSize(12)
          .fontColor('#FA8C16')
          .backgroundColor('#FFF2E6')
          .borderRadius(4)
          .height(28)
          .padding({ left: 10, right: 10 })
          .margin({ left: 6 })
          .enabled(this.busyId !== item.id)
          .onClick(() => {
            this.openAssign(item);
          })

        if (item.status !== 'closed') {
          Button('关闭')
            .fontSize(12)
            .fontColor('#FF4D4F')
            .backgroundColor('#FFE6E6')
            .borderRadius(4)
            .height(28)
            .padding({ left: 10, right: 10 })
            .margin({ left: 6 })
            .enabled(this.busyId !== item.id)
            .onClick(() => {
              this.closeRow(item);
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
