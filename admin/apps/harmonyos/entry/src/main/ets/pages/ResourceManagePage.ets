/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { GameService } from '../service/DataService';
import { ResourceService } from '../service/ResourceService';
import { GameItem, FormValues, FormPayload, ResourceRow } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { FormDialog, FormField, confirmDelete } from '../common/FormDialog';
import { ResourceModule } from '../common/ResourceSchema';
import { moduleByKey } from '../common/ResourceModules';
import { ResourceRowItem, PagerBar, GamePickerBar, ExtraActionDialog } from '../common/ResourceViews';

/**
 * 删除二次确认（系统配置专用）：DELETE /admin/v1/config/{id} 要带管理员密码，
 * 控制器 confirmPassword() 没拿到密码会 422「敏感操作需要输入密码确认」。
 * 复用 FormDialog（单字段），不再造第二个弹框。
 */
const PASSWORD_FIELDS: FormField[] = [
  { name: 'password', label: '管理员密码', type: 'password', required: true, placeholder: '敏感操作需二次确认' }
];

/** 空预填值（FormDialog 会拷一份再改，共享一个常量安全）——密码框不能带上编辑表单的残留快照 */
const EMPTY_VALUES: FormValues = {};

/**
 * 「内容 / 配置 / 用户与权限 / 资金 / 风控」通用管理页 —— 列表 + 新建/编辑/删除 + 状态变更，
 * 18 个模块共用本页。
 *
 * 模块差异（端点、字段、状态变更方式、列表形状）全部在 common/ResourceModules.ets 里描述，
 * 本页不出现任何模块名分支：路由参数 module=<key> 进来，未知 key 直接报错而不是渲染空列表。
 *
 * 列表行 id 一律当 hashid 用（各模块的路径段都是列表返回的 id）。
 * 提现订单不在本页：行上挂的是四个资金动作（审核/复核/打款/同步），不是 CRUD，见 WithdrawManagePage。
 * 风控的设备/IP/异常用户/团伙同理，见 RiskIndexPage。
 */
@Entry
@Component
struct ResourceManagePage {
  /** 路由参数：模块 key */
  @State moduleKey: string = '';
  @State title: string = '管理';
  /** 行内额外动作文案（空串 = 不渲染），取自模块描述 */
  @State extraText: string = '';
  /** 是否要分页：模块描述里 paginated=false 的整表返回，不画分页条 */
  @State paginated: boolean = false;
  /** 需要先选游戏的模块（区服） */
  @State needsGame: boolean = false;
  /** 模块有没有新建端点（平台用户没有）⇒ 决定顶栏画不画「+ 新增」 */
  @State canCreate: boolean = true;
  /** 模块有没有删除端点（提现阶梯限额、风控规则没有）⇒ 决定行内画不画删除按钮 */
  @State canDelete: boolean = true;
  /** 模块有没有更新端点（风控事件/反作弊事件没有）⇒ 决定行内画不画「编辑」 */
  @State canEdit: boolean = true;
  /** 编辑是不是全量语义（风控规则 update 与 create 共用 fill()）⇒ 决定表单的 partial */
  @State fullEdit: boolean = false;
  /** 「新建」的按钮/表单标题文案；空串 = 缺省的「+ 新增 / 新建<标题>」 */
  @State createLabel: string = '';
  @State rows: ResourceRow[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  /** 表单弹框由 formOpen 控制挂载：挂载时才读 formInitial，故每次开框都是新快照 */
  @State formOpen: boolean = false;
  @State formEdit: boolean = false;
  @State formFields: FormField[] = [];
  @State formInitial: FormValues = {};
  /** 删除密码弹框（仅 deletePassword 模块） */
  @State pwdOpen: boolean = false;
  @State pwdTitle: string = '';
  /** 要带参数的行内动作弹框（模块配了 extraForm 才有；试算 / 处置 / 审核） */
  @State extraOpen: boolean = false;
  @State extraTitle: string = '';
  @State extraFields: FormField[] = [];
  /** 正在做行内操作的行 id：禁用该行按钮，防连点重复提交 */
  @State busyId: string = '';
  /** 正在跑 prepareForm（不进 @State：不驱动渲染，只用来挡住连点开框） */
  private formPreparing: boolean = false;
  /** 游戏选择器（needsGame 模块） */
  @State games: GameItem[] = [];
  @State gameIndex: number = -1;

  /** 不进 @State：不驱动渲染 */
  private module: ResourceModule | null = null;
  /** 当前选中的游戏 hashid（needsGame 模块的列表与新建都挂在它下面） */
  private gameId: string = '';
  /** 编辑/删除目标的 hashid（新建态为空串） */
  private editingId: string = '';
  private deletingId: string = '';
  /** 要带参数的行内动作的目标行 id */
  private extraId: string = '';
  private readonly limit: number = 15;

  aboutToAppear(): void {
    const params = (router.getParams() ?? {}) as Record<string, Object>;
    this.moduleKey = (params['module'] as string) ?? '';
    const found = moduleByKey(this.moduleKey);
    this.module = found;
    if (found === null) {
      this.isLoading = false;
      this.hasError = true;
      return;
    }
    this.title = found.title;
    this.extraText = found.extraText;
    this.paginated = found.paginated;
    this.needsGame = found.needsGame;
    this.canCreate = found.canCreate !== false;
    this.canDelete = found.canDelete !== false;
    this.canEdit = found.canEdit !== false;
    this.fullEdit = found.fullEdit === true;
    this.createLabel = found.createLabel ?? '';
    if (found.needsGame) {
      this.loadGames();
    } else {
      this.loadData(true);
    }
  }

  /** needsGame 模块：先取游戏列表，默认选第一个（没游戏就只剩空态，不给建区服） */
  private async loadGames(): Promise<void> {
    this.isLoading = true;
    this.hasError = false;
    try {
      const result = await GameService.getList(1, 100, '');
      this.games = result.list;
      if (this.games.length > 0) {
        this.gameIndex = 0;
        this.gameId = this.games[0].id;
        this.loadData(true);
        return;
      }
      this.isLoading = false;
    } catch (e) {
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      this.isLoading = false;
      this.hasError = true;
      promptAction.showToast({ message: '加载游戏列表失败' });
    }
  }

  async loadData(reset: boolean = false): Promise<void> {
    const m = this.module;
    if (m === null) {
      return;
    }
    if (reset) {
      this.page = 1;
    }
    // 没选到游戏就没有 game_id，列表接口会 422 —— 本地先短断，别拿服务端错误当加载失败
    if (m.needsGame && this.gameId === '') {
      this.isLoading = false;
      this.hasError = false;
      this.rows = [];
      this.total = 0;
      return;
    }
    this.isLoading = true;
    this.hasError = false;
    try {
      const result = await ResourceService.list(m, this.page, this.limit, this.gameId);
      this.rows = result.rows;
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

  private gameOptions(): SelectOption[] {
    const options: SelectOption[] = [];
    for (let i = 0; i < this.games.length; i++) {
      options.push({ value: this.games[i].name });
    }
    return options;
  }

  /**
   * 开框前的取数钩子：候选不在本模块列表里的字段靠它补齐（如角色的权限多选要拉权限树）。
   * 失败/重入一律返回 false ⇒ 不开框：空候选的表单点确定只会误清数据。
   */
  private async prepareForm(m: ResourceModule): Promise<boolean> {
    if (m.prepareForm === undefined) {
      return true;
    }
    if (this.formPreparing) {
      return false;
    }
    this.formPreparing = true;
    try {
      await m.prepareForm();
      return true;
    } catch (e) {
      this.reportRowError(e);
      return false;
    } finally {
      this.formPreparing = false;
    }
  }

  private async openCreate(): Promise<void> {
    const m = this.module;
    if (m === null) {
      return;
    }
    if (!await this.prepareForm(m)) {
      return;
    }
    this.editingId = '';
    this.formEdit = false;
    this.formFields = m.fields;
    this.formInitial = m.createInitial();
    this.formOpen = true;
  }

  private async openEdit(row: ResourceRow): Promise<void> {
    const m = this.module;
    if (m === null) {
      return;
    }
    if (!await this.prepareForm(m)) {
      return;
    }
    this.editingId = row.id;
    this.formEdit = true;
    this.formFields = m.editFields;
    // values 是列表加载时算好的快照（键与 editFields 对齐），FormDialog 会拷一份再改
    this.formInitial = row.values;
    this.formOpen = true;
  }

  /** 提交表单；非 UNAUTHORIZED 的错原样上抛，由弹框显示服务端 message 且不关框 */
  private async submitForm(body: FormPayload): Promise<void> {
    const m = this.module;
    if (m === null) {
      return;
    }
    try {
      if (this.formEdit) {
        await ResourceService.update(m, this.editingId, body);
        promptAction.showToast({ message: '更新成功' });
      } else {
        // 区服的新建要挂在选中的游戏下：game_id 是 hashid，由页面注入（表单里没有这个字段）
        if (m.needsGame) {
          body.game_id = this.gameId;
        }
        await ResourceService.create(m, body);
        promptAction.showToast({ message: '创建成功' });
      }
    } catch (e) {
      if (this.redirectIfUnauthorized(e)) {
        return;
      }
      // ArkTS 只许 throw Error 及其子类（arkts-limited-throw），此处把原错误原样转出给弹框
      throw e as Error;
    }
  }

  private statusText(row: ResourceRow): string {
    const m = this.module;
    if (m === null || m.statusMode === '') {
      return '';
    }
    return row.status === 1 ? '停用' : '启用';
  }

  /** 状态变更：有 toggle 端点走端点，否则局部 PUT {status}（描述见 ResourceModules.ets） */
  private async toggleRow(row: ResourceRow): Promise<void> {
    const m = this.module;
    if (m === null) {
      return;
    }
    const next = row.status === 1 ? 0 : 1;
    const flipped = next === 1 ? '已启用' : '已停用';
    this.busyId = row.id;
    try {
      let said = flipped;
      if (m.statusMode === 'toggle') {
        await ResourceService.toggle(m.togglePath, row.id, next);
      } else if (m.statusMode === 'toggle_bare') {
        // 无请求体的 toggle：服务端自己翻转，**目标态以回执为准**（本地翻转对不上并发改动）
        const status = await ResourceService.toggleBare(m.togglePath, row.id);
        said = status === -1 ? '已切换状态' : flipped;
      } else {
        const body: FormPayload = {};
        body.status = next;
        await ResourceService.update(m, row.id, body);
      }
      promptAction.showToast({ message: said });
      this.loadData(true);
    } catch (e) {
      this.reportRowError(e);
    } finally {
      this.busyId = '';
    }
  }

  /**
   * 行内额外动作：排行榜刷新缓存 / 优惠券统计 / CDN 连通测试（模块没配 extraPath 时服务层是 no-op）。
   * 文案：模块给了 extraMessage 就用它格式化服务端 data，否则**原样用服务端 message**
   * （连通正常 / 刷新成功都是后端写的话，不在这里另编一句）。
   */
  private async extraAction(row: ResourceRow): Promise<void> {
    const m = this.module;
    if (m === null) {
      return;
    }
    // 要填参数的动作（沙箱试算 / 人工处置 / 反作弊审核）改走两步弹框：参数框 → 结果框
    if (m.extraForm !== undefined) {
      this.extraId = row.id;
      this.extraTitle = `${m.extraText}「${row.title}」`;
      this.extraFields = m.extraForm;
      this.extraOpen = true;
      return;
    }
    this.busyId = row.id;
    try {
      const result = await ResourceService.extra(m, row.id);
      const text = m.extraMessage !== undefined
        ? m.extraMessage(result.data, result.message)
        : result.message;
      promptAction.showToast({ message: text.length > 0 ? text : '操作成功' });
    } catch (e) {
      this.reportRowError(e);
    } finally {
      this.busyId = '';
    }
  }

  private removeRow(row: ResourceRow): void {
    const m = this.module;
    if (m === null) {
      return;
    }
    if (m.deletePassword) {
      // 密码不能预填，也不能拿名字当确认——开个单字段弹框，服务端拒绝时错误原文进框内
      this.deletingId = row.id;
      this.pwdTitle = row.title;
      this.pwdOpen = true;
      return;
    }
    confirmDelete(row.title, () => {
      this.doRemove(row.id, '');
    });
  }

  private async doRemove(id: string, password: string): Promise<void> {
    const m = this.module;
    if (m === null) {
      return;
    }
    this.busyId = id;
    try {
      await ResourceService.remove(m, id, password);
      promptAction.showToast({ message: '删除成功' });
      this.loadData(true);
    } catch (e) {
      if (this.pwdOpen) {
        // 密码弹框里的失败：错误原文留在框内（不关框），由 FormDialog 显示
        if (this.redirectIfUnauthorized(e)) {
          return;
        }
        throw e as Error;
      }
      this.reportRowError(e);
    } finally {
      this.busyId = '';
    }
  }

  private async submitPassword(body: FormPayload): Promise<void> {
    await this.doRemove(this.deletingId, `${body['password'] ?? ''}`);
  }

  /**
   * 带参数的行内动作的提交函数：类型上 extraSubmit 可缺，而弹框要的是必定给得出的那个 ——
   * 配了 extraForm 却没配 extraSubmit 是描述写错了，返回一个必抛错的提交（弹框里看得见），
   * 不返回 undefined（那会被弹框当成「成功」直接关掉，是最坏的一种静默）。
   */
  private extraSubmitter(): (id: string, body: FormPayload) => Promise<string[]> {
    const m = this.module;
    if (m === null || m.extraSubmit === undefined) {
      return async (_id: string, _body: FormPayload): Promise<string[]> => {
        throw new Error('该动作没有配提交实现');
      };
    }
    return m.extraSubmit;
  }

  /** 行内操作失败：提示服务端 message（鉴权失效则回登录页） */
  private reportRowError(e: Object): void {
    if (this.redirectIfUnauthorized(e)) {
      return;
    }
    promptAction.showToast({ message: e instanceof Error && e.message.length > 0 ? e.message : '操作失败' });
  }

  private emptyMessage(): string {
    if (this.needsGame) {
      return this.games.length === 0 ? '暂无游戏，请先在游戏管理里新建' : '请先选择游戏';
    }
    return '暂无数据';
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      // 表单弹框：遮罩与卡片是 Stack 里的兄弟节点，
      // 卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      if (this.formOpen) {
        FormDialog({
          title: this.formEdit ? `编辑${this.title}`
            : (this.createLabel.length > 0 ? this.createLabel : `新建${this.title}`),
          fields: this.formFields,
          initial: this.formInitial,
          // 编辑态只发改动过的字段（局部更新）；新建态必须关闭，否则缺省值会被当成「没改」漏发。
          // fullEdit 的模块（风控规则）例外：update 与 create 共用 fill()，缺字段直接 422 ⇒ 发整张表单
          partial: this.formEdit && !this.fullEdit,
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

      // 要带参数的行内动作（试算 / 处置 / 审核）：参数框 → 结果框都在这个组件里，页面只管关框刷新
      if (this.extraOpen) {
        ExtraActionDialog({
          title: this.extraTitle,
          fields: this.extraFields,
          actionId: this.extraId,
          submit: this.extraSubmitter(),
          onClose: () => {
            this.extraOpen = false;
            this.loadData(true);
          }
        })
      }

      // 删除二次确认（密码）
      if (this.pwdOpen) {
        FormDialog({
          title: `删除「${this.pwdTitle}」`,
          fields: PASSWORD_FIELDS,
          initial: EMPTY_VALUES,
          onSubmit: (body: FormPayload) => this.submitPassword(body),
          onDone: () => {
            this.pwdOpen = false;
            this.loadData(true);
          },
          onCancel: () => {
            this.pwdOpen = false;
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

        Text(this.title)
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        // 没有新建端点的模块（平台用户）不画这个按钮，而不是画一个点下去必然 404 的
        if (this.canCreate) {
          Button(this.createLabel.length > 0 ? this.createLabel : '+ 新增')
            .fontSize(13)
            .fontColor('#FFFFFF')
            .backgroundColor('#1677FF')
            .borderRadius(4)
            .height(32)
            .enabled(!this.needsGame || this.games.length > 0)
            .onClick(() => {
              this.openCreate();
            })
        }
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      // 区服这类挂在游戏下的模块，先选游戏（列表与新建都要 game_id）
      if (this.needsGame && this.games.length > 0) {
        GamePickerBar({
          options: this.gameOptions(),
          selected: this.gameIndex,
          onPick: (index: number) => {
            this.gameIndex = index;
            this.gameId = this.games[index].id;
            this.loadData(true);
          }
        })
      }

      // 内容
      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadData() });
      } else if (this.rows.length === 0) {
        EmptyView({ message: this.emptyMessage() });
      } else {
        List() {
          ForEach(this.rows, (row: ResourceRow) => {
            ListItem() {
              // 行内动作的文案由页面按当前模块算好传进去，行组件不认识任何模块
              ResourceRowItem({
                title: row.title,
                subtitle: row.subtitle,
                badge: row.badge,
                badgeOk: row.badgeOk,
                statusText: this.statusText(row),
                extraText: this.extraText,
                busy: this.busyId === row.id,
                canEdit: this.canEdit,
                canDelete: this.canDelete,
                onEdit: () => {
                  this.openEdit(row);
                },
                onToggle: () => {
                  this.toggleRow(row);
                },
                onExtra: () => {
                  this.extraAction(row);
                },
                onDelete: () => {
                  this.removeRow(row);
                }
              })
            }
          }, (row: ResourceRow) => row.id)
        }
        .width('100%')
        .layoutWeight(1)
        .edgeEffect(EdgeEffect.Spring)
      }

      // 分页（整表返回的模块不画）
      if (this.paginated && !this.isLoading && !this.hasError && this.total > this.limit) {
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
