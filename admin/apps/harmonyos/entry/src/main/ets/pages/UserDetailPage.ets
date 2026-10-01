/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { UserService } from '../service/DataService';
import { User, FormPayload, FormValues } from '../model/DataModels';
import { LoadingView, ErrorView } from '../common/Components';
import { FormDialog, FormField, FieldOption } from '../common/FormDialog';
import { TokenManager } from '../utils/TokenManager';
// 本文件所有 t() 都在 build()/事件回调里求值，模块顶层常量只存**键名** ⇒ 直接调 t() 安全
import { t } from '../i18n/Translations';

/**
 * 删除二次确认：`DELETE /admin/v1/user/{hashid}` 要带**当前操作者自己的密码**
 * （`UserController::destroy` → `confirmPassword`，空密码直接 422
 * 「This sensitive operation requires password confirmation」）。
 * 复用 FormDialog 的单字段形态（与 ResourceManagePage 的删除密码框同款）：**弹框本身就是那次二次确认**，
 * 标题里带上被删账号。此前的 AlertDialog 只问「确定吗」不带密码，那条删除请求从来没成功过。
 */
const DELETE_FIELDS: FormField[] = [
  { name: 'password', label: 'res.password_label', type: 'password', required: true, placeholder: 'res.password_placeholder' }
];

/**
 * 重置密码（走 `PUT /admin/v1/user/{hashid}`）：**两个字段名不能对调** ——
 * `password` 是被改账号的**新密码**，`admin_password` 是**操作者自己的密码**
 * （`UserController::update` 里 confirmPassword 读的是后者）。只发 `password` 必 422。
 */
const RESET_FIELDS: FormField[] = [
  { name: 'password', label: 'user.reset_new_label', type: 'password', required: true, hint: 'user.reset_new_hint' },
  { name: 'admin_password', label: 'user.reset_admin_label', type: 'password', required: true, hint: 'user.reset_admin_hint' }
];

/** 空预填值（FormDialog 会拷一份再改，共享一个常量安全）—— 密码框不能带上表单里的残留快照 */
const EMPTY_VALUES: FormValues = {};

/**
 * 「标签 + 控件」表单行。
 *
 * **这里不能用 `@Builder 函数 + content: () => void 形参`**（原来是那种写法）：实参 lambda
 * 的内容按普通箭头函数体解析，`Row() { … }` 这种带块体的容器直接判 `';' expected`；而
 * `content().layoutWeight(1)` 也拿不到组件类型（`void` 上没有 layoutWeight）。
 * 控件槽改走 `@BuilderParam` 的拖尾闭包 —— 闭包体是正常 UI 语法，容器随便嵌。
 *
 * 布局与原写法等价：标签定宽 80，控件自己带 `.layoutWeight(1)` 占满余下宽度
 * （原来是 `content().layoutWeight(1)`，该属性最终也是落在控件上）。
 */
@Component
struct FormRow {
  label: string = '';
  @BuilderParam content: () => void = this.empty;

  @Builder
  empty() {}

  build() {
    Row() {
      Text(this.label)
        .fontSize(15)
        .fontColor('#333333')
        .width(80)

      this.content()
    }
    .width('100%')
    .height(56)
    .padding({ left: 16, right: 16 })
    .backgroundColor('#FFFFFF')
    .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
    .alignItems(VerticalAlign.Center)
  }
}

@Entry
@Component
struct UserDetailPage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State user: User | null = null;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State isSubmitting: boolean = false;
  @State mode: string = 'view';
  @State userId: string = '';

  // 表单状态
  @State formUsername: string = '';
  @State formRealName: string = '';
  @State formPhone: string = '';
  @State formEmail: string = '';
  @State formPassword: string = '';
  @State formStatus: number = 1;

  /** 已分配角色的 **hashid** 数组（详情接口回传），供「分配角色」弹框回填 */
  @State roleIds: string[] = [];
  /** 本行是不是当前登录的管理员自己 —— 自我保护（停用/删除挡在发请求之前） */
  @State isSelf: boolean = false;
  /** 弹框形态：'' 不开框 / 'delete' 删除 / 'roles' 分配角色 / 'reset' 重置密码 */
  @State dialogMode: string = '';
  @State dialogTitle: string = '';
  @State dialogFields: FormField[] = [];
  @State dialogInitial: FormValues = {};

  aboutToAppear(): void {
    const params = (router.getParams() ?? {}) as Record<string, Object>;
    this.mode = (params?.['mode'] as string) ?? 'view';
    this.userId = (params?.['userId'] as string) ?? '';

    if (this.mode !== 'create') {
      this.loadDetail();
    } else {
      this.isLoading = false;
    }
  }

  async loadDetail(): Promise<void> {
    this.isLoading = true;
    try {
      this.user = await UserService.getDetail(this.userId);
      this.formUsername = this.user.username;
      this.formRealName = this.user.real_name;
      this.formPhone = this.user.phone ?? '';
      this.formEmail = this.user.email ?? '';
      this.formStatus = this.user.status;
      this.roleIds = this.user.role_ids ?? [];
      const me = TokenManager.getAdminUsername();
      this.isSelf = me.length > 0 && this.user.username === me;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.hasError = true;
    } finally {
      this.isLoading = false;
    }
  }

  async save(): Promise<void> {
    if (!this.formUsername.trim()) {
      promptAction.showToast({ message: t('user.username_required') });
      return;
    }
    if (this.mode === 'create' && !this.formPassword) {
      promptAction.showToast({ message: t('user.password_required') });
      return;
    }
    // 自我保护：把自己停用＝把自己关在门外（后端不禁止自伤，只验操作者密码）
    if (this.isSelf && this.formStatus === 0) {
      promptAction.showToast({ message: t('user.self_block_disable') });
      return;
    }

    this.isSubmitting = true;
    try {
      if (this.mode === 'create') {
        await UserService.create({
          username: this.formUsername,
          real_name: this.formRealName,
          password: this.formPassword,
          phone: this.formPhone,
          email: this.formEmail,
          status: this.formStatus
        });
        promptAction.showToast({ message: t('res.created') });
      } else {
        await UserService.update(this.userId, {
          real_name: this.formRealName,
          phone: this.formPhone,
          email: this.formEmail,
          status: this.formStatus
        });
        promptAction.showToast({ message: t('res.updated') });
      }
      router.back();
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: t('res.save_failed') });
    } finally {
      this.isSubmitting = false;
    }
  }

  /** 弹框开框：字段/预填/标题一次性算好（FormDialog 挂载时才读 initial，故每次开框都是新快照） */
  private openDialog(mode: string, title: string, fields: FormField[], initial: FormValues): void {
    this.dialogMode = mode;
    this.dialogTitle = title;
    this.dialogFields = fields;
    this.dialogInitial = initial;
  }

  private openDelete(): void {
    // 自我保护：删掉自己＝再没人能把自己加回来（后端不禁止自伤，只验操作者密码）
    if (this.isSelf) {
      promptAction.showToast({ message: t('user.self_block_delete') });
      return;
    }
    this.openDialog('delete', t('res.delete_titled').replace('{name}', this.formUsername), DELETE_FIELDS, EMPTY_VALUES);
  }

  /** 分配角色：候选**每次开框都重拉**（角色随时会新增/停用，缓存只会让人勾到过期项） */
  private async openRoles(): Promise<void> {
    try {
      const roles = await UserService.getRoles();
      const options: FieldOption[] = [];
      for (let i = 0; i < roles.length; i++) {
        options.push({ label: t('user.roles_option').replace('{name}', roles[i].name).replace('{slug}', roles[i].slug), value: roles[i].id });
      }
      const field: FormField = { name: 'role_ids', label: 'user.f.role', type: 'multi', options: options };
      const initial: FormValues = {};
      initial.role_ids = this.roleIds.join(',');
      this.openDialog('roles', t('user.roles_titled').replace('{name}', this.formUsername), [field], initial);
    } catch (e) {
      this.reportError(e);
    }
  }

  private openReset(): void {
    this.openDialog('reset', t('user.reset_titled').replace('{name}', this.formUsername), RESET_FIELDS, EMPTY_VALUES);
  }

  /**
   * 弹框提交：按 dialogMode 分派（三个动作端点/字段各异，字段名收口在服务层）。
   * 抛错**原样上抛**：错误原文显示在框内且不关框，用户改完可重试。
   */
  private async submitDialog(body: FormPayload): Promise<void> {
    if (this.dialogMode === 'delete') {
      await UserService.delete(this.userId, `${body['password'] ?? ''}`);
      return;
    }
    if (this.dialogMode === 'roles') {
      // multi 字段按类型换算成数组（FormDialog.payload）：**空数组照发** = 取消全部角色。
      // 该弹框只有这一个字段且 partial=false，故这里必定拿到数组。
      await UserService.assignRoles(this.userId, body['role_ids'] as string[]);
      return;
    }
    await UserService.resetPassword(this.userId, `${body['password'] ?? ''}`, `${body['admin_password'] ?? ''}`);
  }

  /** 弹框成功：删完回列表，其余动作留在本页重拉详情（服务端是状态真值，不做乐观更新） */
  private onDialogDone(): void {
    const mode = this.dialogMode;
    this.dialogMode = '';
    if (mode === 'delete') {
      promptAction.showToast({ message: t('res.deleted') });
      router.back();
      return;
    }
    promptAction.showToast({ message: mode === 'roles' ? t('user.roles_updated') : t('user.reset_done') });
    this.loadDetail();
  }

  /** 非 UNAUTHORIZED 的错原样提示服务端 message */
  private reportError(e: Object): void {
    if (e instanceof Error && e.message === 'UNAUTHORIZED') {
      router.replaceUrl({ url: 'pages/LoginPage' });
      return;
    }
    promptAction.showToast({ message: e instanceof Error && e.message.length > 0 ? e.message : t('res.op_failed') });
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      // 弹框挂在 Stack 里（遮罩与卡片是兄弟节点，卡片上的点击不会冒泡到遮罩）
      if (this.dialogMode.length > 0) {
        FormDialog({
          title: this.dialogTitle,
          fields: this.dialogFields,
          initial: this.dialogInitial,
          onSubmit: (body: FormPayload) => this.submitDialog(body),
          onDone: () => this.onDialogDone(),
          onCancel: () => {
            this.dialogMode = '';
          }
        })
      }
    }
    .width('100%')
    .height('100%')
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

        Text(this.mode === 'create' ? t('user.create_title') : t('user.detail_title'))
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        if (this.mode !== 'create') {
          Button(t('user.f.role'))
            .fontSize(13)
            .fontColor('#1677FF')
            .backgroundColor('#E6F0FF')
            .borderRadius(4)
            .height(32)
            .margin({ right: 8 })
            .onClick(() => { this.openRoles(); })

          Button(t('user.action_reset'))
            .fontSize(13)
            .fontColor('#1677FF')
            .backgroundColor('#E6F0FF')
            .borderRadius(4)
            .height(32)
            .margin({ right: 8 })
            .onClick(() => { this.openReset(); })

          Button(t('common.delete'))
            .fontSize(13)
            .fontColor('#FF4D4F')
            .backgroundColor('#FFE6E6')
            .borderRadius(4)
            .height(32)
            .onClick(() => this.openDelete())
        }
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadDetail() });
      } else {
        Scroll() {
          Column() {
            // 头像
            Circle({ width: 72, height: 72 })
              .fill('#E6F0FF')
              .margin({ top: 24, bottom: 24 })

            // 表单
            FormRow({ label: t('user.f.username') }) {
              TextInput({ placeholder: t('user.f.username_placeholder'), text: this.formUsername })
                .fontSize(15)
                .layoutWeight(1)
                .enabled(this.mode === 'create')
                .onChange((v: string) => { this.formUsername = v; })
            }

            if (this.mode === 'create') {
              FormRow({ label: t('user.f.password') }) {
                TextInput({ placeholder: t('user.f.password_placeholder'), text: this.formPassword })
                  .fontSize(15)
                  .layoutWeight(1)
                  .type(InputType.Password)
                  .onChange((v: string) => { this.formPassword = v; })
              }
            }

            FormRow({ label: t('user.f.real_name') }) {
              TextInput({ placeholder: t('user.f.real_name_placeholder'), text: this.formRealName })
                .fontSize(15)
                .layoutWeight(1)
                .onChange((v: string) => { this.formRealName = v; })
            }

            FormRow({ label: t('user.f.phone') }) {
              TextInput({ placeholder: t('user.f.phone_placeholder'), text: this.formPhone })
                .fontSize(15)
                .layoutWeight(1)
                .type(InputType.PhoneNumber)
                .onChange((v: string) => { this.formPhone = v; })
            }

            FormRow({ label: t('user.f.email') }) {
              TextInput({ placeholder: t('user.f.email_placeholder'), text: this.formEmail })
                .fontSize(15)
                .layoutWeight(1)
                .type(InputType.Email)
                .onChange((v: string) => { this.formEmail = v; })
            }

            FormRow({ label: t('user.f.status') }) {
              Row() {
                Button(t('user.status_enabled'))
                  .fontSize(13)
                  .fontColor(this.formStatus === 1 ? '#FFFFFF' : '#666666')
                  .backgroundColor(this.formStatus === 1 ? '#52C41A' : '#F5F5F5')
                  .borderRadius(4)
                  .height(32)
                  .onClick(() => { this.formStatus = 1; })

                Button(t('user.status_disabled'))
                  .fontSize(13)
                  .fontColor(this.formStatus === 0 ? '#FFFFFF' : '#666666')
                  .backgroundColor(this.formStatus === 0 ? '#FF4D4F' : '#F5F5F5')
                  .borderRadius(4)
                  .height(32)
                  .margin({ left: 12 })
                  .onClick(() => { this.formStatus = 0; })
              }
              .layoutWeight(1)
            }

            // 提交按钮
            Button(t('common.save'))
              .width('100%')
              .height(48)
              .fontSize(16)
              .fontColor('#FFFFFF')
              .backgroundColor(this.isSubmitting ? '#99BBFF' : '#1677FF')
              .borderRadius(8)
              .margin({ top: 32, left: 16, right: 16, bottom: 32 })
              .enabled(!this.isSubmitting)
              .onClick(() => { this.save(); })
          }
          .width('100%')
        }
        .width('100%')
        .layoutWeight(1)
        .backgroundColor('#F5F5F5')
      }
    }
    .width('100%')
    .height('100%')
  }
}
