/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { GameService } from '../service/DataService';
import { GameItem, FormValues, FormPayload } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { FormDialog, RowActionBar, FormField, confirmDelete } from '../common/FormDialog';

/**
 * 游戏字段描述 —— 值域取自 GameController 的 validator 与 game_game 列注释：
 *   name 必填 ≤100；slug 必填 ≤50 且 ^[a-z0-9_-]+$（**仅创建**：update 的 only() 白名单里没有 slug）；
 *   type: self/embedded/third_party；platform: h5/unity/web/native；status: 0 下架 / 1 上架；
 *   sort 非负整数；cover_image/api_endpoint ≤255；sdk_version ≤20；region ≤10
 *   api_key/api_secret 刻意不在表单里：列表被 $hidden 遮住回显不出来，空串提交会把正在用的密钥清走
 *   （自研/内嵌由服务端生成，第三方由对方提供），要换密钥得另开一条专用路径。
 */
const GAME_FIELDS: FormField[] = [
  { name: 'name', label: '游戏名称', type: 'text', required: true, placeholder: '最长 100 字' },
  { name: 'slug', label: '游戏标识', type: 'text', required: true, placeholder: '小写字母/数字/_/-，最长 50' },
  {
    name: 'type', label: '游戏类型', type: 'select', required: true, options: [
      { label: '自研', value: 'self' },
      { label: '内嵌', value: 'embedded' },
      { label: '第三方', value: 'third_party' }
    ]
  },
  {
    name: 'platform', label: '客户端平台', type: 'select', options: [
      { label: 'H5', value: 'h5' },
      { label: 'Unity', value: 'unity' },
      { label: 'Web', value: 'web' },
      { label: '原生', value: 'native' }
    ]
  },
  { name: 'region', label: '运营区域', type: 'text', placeholder: 'global / CN / US…，最长 10' },
  { name: 'sdk_version', label: 'SDK 版本', type: 'text', placeholder: '自研/内嵌游戏用，最长 20' },
  { name: 'cover_image', label: '封面图', type: 'text', placeholder: '图片地址，最长 255' },
  { name: 'api_endpoint', label: 'API 端点', type: 'text', placeholder: '第三方游戏地址，最长 255' },
  { name: 'description', label: '游戏简介', type: 'textarea', placeholder: '游戏描述' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前，缺省 0' },
  { name: 'status', label: '上架', type: 'switch' }
];

/** 编辑态把 slug 换成只读回显：后端 update 收不了它，仍标必填会拦下提交 */
function gameFormFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < GAME_FIELDS.length; i++) {
    const field = GAME_FIELDS[i];
    if (isEdit && field.name === 'slug') {
      fields.push({ name: 'slug', label: '游戏标识', type: 'text', placeholder: '创建后不可修改', disabled: true });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

/**
 * 新建态缺省值：type/platform/region 取数据库列默认值，status 与后端 create 缺省一致（0 下架）
 * 逐键赋值而非对象字面量 —— ArkTS 不允许把对象字面量赋给 Record（arkts-no-untyped-obj-literals）
 */
function gameCreateInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.slug = '';
  values.type = 'self';
  values.platform = 'h5';
  values.region = 'global';
  values.sdk_version = '';
  values.cover_image = '';
  values.api_endpoint = '';
  values.description = '';
  values.sort = '0';
  values.status = '0';
  return values;
}

/**
 * 游戏管理（增删改 + 上架/下架）—— /admin/v1/game/*
 * 列表返回的 id 是 hashid，{hashid} 路径与 update 的 body 都用它。
 */
@Entry
@Component
struct GameManagePage {
  @State games: GameItem[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State keyword: string = '';
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
      const result = await GameService.getList(this.page, this.limit, this.keyword);
      this.games = result.list;
      this.total = result.total;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
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

  private openCreate(): void {
    this.editingId = '';
    this.formEdit = false;
    this.formInitial = gameCreateInitial();
    this.formOpen = true;
  }

  private openEdit(game: GameItem): void {
    this.editingId = game.id;
    this.formEdit = true;
    const initial: FormValues = {};
    initial.name = game.name;
    initial.slug = game.slug;
    initial.type = game.type;
    initial.platform = game.platform;
    initial.region = game.region;
    initial.sdk_version = game.sdk_version ?? '';
    initial.cover_image = game.cover_image ?? '';
    initial.api_endpoint = game.api_endpoint ?? '';
    initial.description = game.description ?? '';
    initial.sort = `${game.sort}`;
    initial.status = game.status === 1 ? '1' : '0';
    this.formInitial = initial;
    this.formOpen = true;
  }

  /** 提交表单；非 UNAUTHORIZED 的错原样上抛，由弹框显示服务端 message 且不关框 */
  private async submitForm(body: FormPayload): Promise<void> {
    try {
      if (this.formEdit) {
        await GameService.update(this.editingId, body);
        promptAction.showToast({ message: '更新成功' });
      } else {
        await GameService.create(body);
        promptAction.showToast({ message: '创建成功' });
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

  /** 游戏没有独立 toggle 端点 ⇒ 走 update 传 status（status 收 0/1） */
  private async toggleStatus(game: GameItem): Promise<void> {
    const next = game.status === 1 ? 0 : 1;
    this.busyId = game.id;
    try {
      const body: FormPayload = {};
      body.status = next;
      await GameService.update(game.id, body);
      promptAction.showToast({ message: next === 1 ? '已上架' : '已下架' });
      this.loadData(true);
    } catch (e) {
      this.reportRowError(e);
    } finally {
      this.busyId = '';
    }
  }

  private removeItem(game: GameItem): void {
    confirmDelete(game.name, () => {
      this.doRemove(game);
    });
  }

  private async doRemove(game: GameItem): Promise<void> {
    this.busyId = game.id;
    try {
      await GameService.remove(game.id);
      promptAction.showToast({ message: '删除成功' });
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
    promptAction.showToast({ message: e instanceof Error && e.message.length > 0 ? e.message : '操作失败' });
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      // 表单弹框：遮罩与卡片是 Stack 里的兄弟节点，
      // 卡片上的点击不会冒泡到遮罩（无需 stopPropagation）
      if (this.formOpen) {
        FormDialog({
          title: this.formEdit ? '编辑游戏' : '新建游戏',
          fields: gameFormFields(this.formEdit),
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

        Text('游戏管理')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        Button('+ 新增')
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

      // 搜索栏
      Row() {
        TextInput({ placeholder: '搜索游戏名称', text: this.keyword })
          .height(40)
          .fontSize(14)
          .backgroundColor('#F5F5F5')
          .borderRadius(8)
          .layoutWeight(1)
          .padding({ left: 12, right: 12 })
          .onChange((value: string) => {
            this.keyword = value;
          })
          .onSubmit(() => {
            this.loadData(true);
          })

        Button('搜索')
          .fontSize(13)
          .fontColor('#FFFFFF')
          .backgroundColor('#1677FF')
          .borderRadius(4)
          .width(60)
          .height(40)
          .margin({ left: 8 })
          .onClick(() => {
            this.loadData(true);
          })
      }
      .width('100%')
      .padding({ left: 16, right: 16, top: 8, bottom: 8 })
      .backgroundColor('#FFFFFF')

      // 内容
      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadData() });
      } else if (this.games.length === 0) {
        EmptyView({ message: '暂无游戏数据' });
      } else {
        List() {
          ForEach(this.games, (game: GameItem) => {
            ListItem() {
              Column() {
                Row() {
                  Text(game.name)
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .layoutWeight(1)

                  Text(game.status === 1 ? '上架' : '下架')
                    .fontSize(10)
                    .fontColor(game.status === 1 ? '#52C41A' : '#FF4D4F')
                    .backgroundColor(game.status === 1 ? '#E6FFF0' : '#FFE6E6')
                    .borderRadius(2)
                    .padding({ left: 6, right: 6, top: 2, bottom: 2 })
                }
                .width('100%')

                Row() {
                  Text(`${game.type} · ${game.platform} · ${game.slug}`)
                    .fontSize(12)
                    .fontColor('#999999')
                    .layoutWeight(1)

                  RowActionBar({
                    statusText: game.status === 1 ? '下架' : '上架',
                    busy: this.busyId === game.id,
                    onEdit: () => {
                      this.openEdit(game);
                    },
                    onToggle: () => {
                      this.toggleStatus(game);
                    },
                    onDelete: () => {
                      this.removeItem(game);
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
          }, (game: GameItem) => game.id)
        }
        .width('100%')
        .layoutWeight(1)
        .edgeEffect(EdgeEffect.Spring)
      }

      // 分页
      if (!this.isLoading && !this.hasError && this.total > this.limit) {
        Row() {
          Button('上一页')
            .fontSize(13)
            .fontColor(this.page > 1 ? '#1677FF' : '#CCCCCC')
            .backgroundColor(Color.Transparent)
            .enabled(this.page > 1)
            .onClick(() => {
              this.goPage(-1);
            })

          Text(`${this.page} / ${this.pageCount()}`)
            .fontSize(13)
            .fontColor('#666666')

          Button('下一页')
            .fontSize(13)
            .fontColor(this.page < this.pageCount() ? '#1677FF' : '#CCCCCC')
            .backgroundColor(Color.Transparent)
            .enabled(this.page < this.pageCount())
            .onClick(() => {
              this.goPage(1);
            })
        }
        .width('100%')
        .height(48)
        .justifyContent(FlexAlign.Center)
        .backgroundColor('#FFFFFF')
        .border({ width: { top: 0.5 }, color: '#EEEEEE' })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }
}
