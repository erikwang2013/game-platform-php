/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { AnnouncementService } from '../service/DataService';
import { AnnouncementItem, FormValues, FormPayload } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { FormDialog, RowActionBar, FormField, confirmDelete } from '../common/FormDialog';
import { PagerBar } from '../common/ResourceViews';
import { t } from '../i18n/Translations';

/**
 * 公告字段描述 —— 值域取自 AnnouncementController 的 validator 与 game_announcement 列注释：
 *   title 必填 ≤255；content 必填（TEXT）；target_lang 空串 = 全语言，≤10；status: 0 草稿 / 1 已发布。
 *   type 真值 = system / game / payment，出处：game_announcement.type 列注释
 *   （install/install.sql:391「system=系统公告 game=游戏公告 payment=支付公告」）
 *   + 存量数据 install/test-data.sql:90-92（system 与 payment 各一条）。
 *   控制器 create/update 现同口径 `sometimes|required|in:system,game,payment`；
 *   早期后端曾误写 `in:system,event`（与列注释、存量数据都不符），已修正 ——
 *   值域一律以后端 validator 为准，改这里之前先去读控制器。
 *   start_at/end_at 未纳入表单（要日期控件；不传即不动这两列，改期请走 Web 端）。
 */
/** 各文案字段存**键名**（FormDialog 对 label/hint/placeholder 各调一次 t()）—— 顶层调 t() 会固化启动语言 */
const ANNOUNCEMENT_FIELDS: FormField[] = [
  { name: 'title', label: 'ann.f.title', type: 'text', required: true, placeholder: 'risk.cheat.note_hint' },
  { name: 'content', label: 'ann.f.content', type: 'textarea', required: true, placeholder: 'ann.f.content_placeholder' },
  {
    name: 'type', label: 'ann.f.type', type: 'select', required: true, options: [
      { label: 'ann.type_system', value: 'system' },
      { label: 'ann.type_game', value: 'game' },
      { label: 'ann.type_payment', value: 'payment' }
    ]
  },
  { name: 'target_lang', label: 'ann.f.target_lang', type: 'text', placeholder: 'ann.f.target_lang_placeholder' },
  { name: 'status', label: 'ann.listed', type: 'switch' }
];

/**
 * 新建态缺省值：type/status 取列默认值（system / 0 草稿），与后端 create 一致
 * 逐键赋值而非对象字面量 —— ArkTS 不允许把对象字面量赋给 Record（arkts-no-untyped-obj-literals）
 */
function announcementCreateInitial(): FormValues {
  const values: FormValues = {};
  values.title = '';
  values.content = '';
  values.type = 'system';
  values.target_lang = '';
  values.status = '0';
  return values;
}

/**
 * 公告管理（增删改 + 上下架）—— /admin/v1/announcement/*
 * 状态切换有独立端点 POST /announcement/toggle（body 里 id 是 hashid）。
 */
@Entry
@Component
struct AnnouncementManagePage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State announcements: AnnouncementItem[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  /** 弹框由 formOpen 控制挂载：挂载时才读 formInitial，故每次开框都是新快照 */
  @State formOpen: boolean = false;
  @State formEdit: boolean = false;
  @State formInitial: FormValues = {};
  /** 正在做行内操作的行 id：禁用该行按钮，防连点重复提交 */
  @State busyId: string = '';
  /** 编辑目标的 hashid（新建态为空串）；不进 @State —— 它不驱动渲染 */
  private editingId: string = '';
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
      const result = await AnnouncementService.getList({ page: this.page, limit: this.limit });
      this.announcements = result.list;
      this.total = result.total;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
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

  private openCreate(): void {
    this.editingId = '';
    this.formEdit = false;
    this.formInitial = announcementCreateInitial();
    this.formOpen = true;
  }

  private openEdit(item: AnnouncementItem): void {
    this.editingId = item.id;
    this.formEdit = true;
    const initial: FormValues = {};
    initial.title = item.title;
    initial.content = item.content;
    initial.type = item.type;
    initial.target_lang = item.target_lang ?? '';
    initial.status = item.status === 1 ? '1' : '0';
    this.formInitial = initial;
    this.formOpen = true;
  }

  /** 提交表单；非 UNAUTHORIZED 的错原样上抛，由弹框显示服务端 message 且不关框 */
  private async submitForm(body: FormPayload): Promise<void> {
    try {
      if (this.formEdit) {
        await AnnouncementService.update(this.editingId, body);
        promptAction.showToast({ message: t('res.updated') });
      } else {
        await AnnouncementService.create(body);
        promptAction.showToast({ message: t('res.created') });
      }
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // ArkTS 只许 throw Error 及其子类（arkts-limited-throw），此处把原错误原样转出给弹框
      throw e as Error;
    }
  }

  private async toggleStatus(item: AnnouncementItem): Promise<void> {
    const next = item.status === 1 ? 0 : 1;
    this.busyId = item.id;
    try {
      await AnnouncementService.toggle(item.id, next);
      promptAction.showToast({ message: next === 1 ? t('ann.published_toast') : t('ann.unpublished_toast') });
      this.loadData(true);
    } catch (e) {
      this.reportRowError(e);
    } finally {
      this.busyId = '';
    }
  }

  private removeItem(item: AnnouncementItem): void {
    confirmDelete(item.title, () => {
      this.doRemove(item);
    });
  }

  private async doRemove(item: AnnouncementItem): Promise<void> {
    this.busyId = item.id;
    try {
      await AnnouncementService.remove(item.id);
      promptAction.showToast({ message: t('res.deleted') });
      this.loadData(true);
    } catch (e) {
      this.reportRowError(e);
    } finally {
      this.busyId = '';
    }
  }

  /** 行内操作失败：提示服务端 message（鉴权失效则回登录页） */
  private reportRowError(e: Object): void {
    if (e instanceof Error && e.message === 'UNAUTHORIZED') {
      router.replaceUrl({ url: 'pages/LoginPage' });
      return;
    }
    promptAction.showToast({ message: e instanceof Error && e.message.length > 0 ? e.message : t('res.op_failed') });
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      // 表单弹框：遮罩与卡片是 Stack 里的兄弟节点，
      // 卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      if (this.formOpen) {
        FormDialog({
          title: this.formEdit ? t('ann.edit_title') : t('ann.create_title'),
          fields: ANNOUNCEMENT_FIELDS,
          initial: this.formInitial,
          onSubmit: (body: FormPayload) => this.submitForm(body),
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

        Text(t('ann.manage_title'))
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        Button(t('res.create'))
          .fontSize(13)
          .fontColor('#FFFFFF')
          .backgroundColor('#1677FF')
          .borderRadius(4)
          .height(32)
          .onClick(() => {
            this.openCreate();
          })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      // 内容
      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadData() });
      } else if (this.announcements.length === 0) {
        EmptyView({ message: t('ann.empty') });
      } else {
        List() {
          ForEach(this.announcements, (item: AnnouncementItem) => {
            ListItem() {
              Column() {
                Row() {
                  Text(item.title)
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .layoutWeight(1)

                  Text(item.status === 1 ? t('ann.listed') : t('ann.draft'))
                    .fontSize(10)
                    .fontColor(item.status === 1 ? '#52C41A' : '#999999')
                    .backgroundColor(item.status === 1 ? '#E6FFF0' : '#F0F0F0')
                    .borderRadius(2)
                    .padding({ left: 6, right: 6, top: 2, bottom: 2 })
                }
                .width('100%')

                Row() {
                  Text(`${item.type} · ${item.created_at}`)
                    .fontSize(12)
                    .fontColor('#999999')
                    .layoutWeight(1)

                  RowActionBar({
                    statusText: item.status === 1 ? t('ann.action_unpublish') : t('ann.action_publish'),
                    busy: this.busyId === item.id,
                    onEdit: () => {
                      this.openEdit(item);
                    },
                    onToggle: () => {
                      this.toggleStatus(item);
                    },
                    onDelete: () => {
                      this.removeItem(item);
                    }
                  })
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
          }, (item: AnnouncementItem) => item.id)
        }
        .width('100%')
        .layoutWeight(1)
        .edgeEffect(EdgeEffect.Spring)
      }

      // 分页（共用 PagerBar，与资源管理页同一套；整表返回的模块不挂）
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
}
