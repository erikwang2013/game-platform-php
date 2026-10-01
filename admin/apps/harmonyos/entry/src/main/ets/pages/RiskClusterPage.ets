/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
// 本文件按 kit 形态引入（同树 GameDetailPage 的写法）：`from '@ohos/promptAction'` 在本树其余 17 个文件里
// 解析正常，只在本文件被真编译器报成 10505001「Cannot find module」（clean build 复现、非缓存），换此形态即消。
import { promptAction } from '@kit.ArkUI';
import { RiskService } from '../service/RiskService';
import {
  RiskClusterItem,
  RiskClusterCandidate,
  RiskClusterConfirmRequest,
  RiskClusterMembersResult,
  RiskClusterMember
} from '../model/RiskModels';
import { LoadingView, ErrorView, EmptyView, ResultDialog } from '../common/Components';
import { FormField, FieldOption } from '../common/FormDialog';
import { FormValues, FormPayload } from '../model/DataModels';
import { PagerBar, ExtraActionDialog } from '../common/ResourceViews';
import { t, t1 } from '../i18n/Translations';

/**
 * 关联团伙页 —— 列表 + 页面级「聚类检测 / 人工确认」+ 行内「成员 / 状态」。
 *
 * 三处端点特性决定了这一页的形状：
 *  1. 列表路径是 **`/admin/v1/risk/clusters`**（没有 `/list` 段），响应 `{total, items}`、分页参数是 `size`。
 *  2. **status 是三态**（1 观察中 / 2 已处置 / 0 误判）⇒ 不能用 0/1 翻转控件（会把 2 静默压成 0），
 *     改成一个三选一的小表单（值原样发，界面能表达全部三种状态）。
 *  3. **`detect` 是全局动作、没有行上下文**（近 7 天 IP≥5 账户 / 设备≥3 账户，只出候选不落库）
 *     ⇒ 页面级按钮；候选就地铺在列表上方，每条「确认」把 type/fingerprint/user_count 带进表单。
 *
 * 确认团伙时 **member_ids 仍不上送**（控制器现在收 hashid，`decodeId` 非法值 400，不再是「发了会被
 * 静默丢成 0」那回事）。真正的理由有两条：
 *  · **此刻没有 id 可用**：confirm 是**创建**动作，表单开出时还没有 cluster id，成员 hashid 只能从
 *    已存在团伙的 members 端点取；detect 候选也只带 type/fingerprint/user_count ⇒ 没有可发的 id。
 *  · **别造第二真值源**：服务端 `resolveMemberIds()` 对 same_ip/same_device 按指纹现查（与 members
 *    端点同一函数），确认前后名单必然一致；若让运营从别处抄 hashid 手输，同一份名单就有了两个来源。
 *
 * 端点：GET  /admin/v1/risk/clusters?page=&size=
 *       POST /admin/v1/risk/clusters/detect
 *       POST /admin/v1/risk/clusters/confirm {type, fingerprint, name, user_count}
 *       PUT  /admin/v1/risk/clusters/{hashid}/status {status} + GET /clusters/{hashid}/members
 */

/** label / hint / placeholder 一律存**键名**（FormDialog 渲染时各调一次 t()）—— 顶层调 t() 会固化启动语言 */
const CLUSTER_TYPE_OPTIONS: FieldOption[] = [
  { label: 'risk.cluster.type_same_ip', value: 'same_ip' },
  { label: 'risk.cluster.type_same_device', value: 'same_device' },
  { label: 'risk.cluster.type_same_pay', value: 'same_pay_account' },
  { label: 'risk.cluster.type_manual', value: 'manual' }
];

const CLUSTER_STATUS_OPTIONS: FieldOption[] = [
  { label: 'risk.cluster.status_watching', value: '1' },
  { label: 'risk.cluster.status_handled', value: '2' },
  { label: 'risk.cluster.status_false_positive', value: '0' }
];

const CONFIRM_FIELDS: FormField[] = [
  { name: 'name', label: 'risk.cluster.field_name', type: 'text', required: true, placeholder: 'risk.cluster.name_placeholder' },
  { name: 'type', label: 'risk.cluster.field_type', type: 'select', required: true, options: CLUSTER_TYPE_OPTIONS },
  { name: 'fingerprint', label: 'risk.cluster.field_fingerprint', type: 'text', hint: 'risk.cluster.fingerprint_hint',
    placeholder: 'risk.cluster.fingerprint_placeholder' },
  { name: 'user_count', label: 'risk.cluster.field_user_count', type: 'number', placeholder: 'risk.cluster.user_count_placeholder' }
];

const STATUS_FIELDS: FormField[] = [
  { name: 'status', label: 'risk.cluster.field_status', type: 'select', required: true, options: CLUSTER_STATUS_OPTIONS,
    hint: 'risk.cluster.status_hint' }
];

/** 两个查表函数都在 build() 里调用 ⇒ 每次重渲染重新查表，语言切换即时生效 */
function clusterStatusText(status: number): string {
  if (status === 1) {
    return t('risk.cluster.status_watching');
  }
  return status === 2 ? t('risk.cluster.status_handled') : t('risk.cluster.status_false_positive');
}

function clusterTypeText(type: string): string {
  for (let i = 0; i < CLUSTER_TYPE_OPTIONS.length; i++) {
    if (CLUSTER_TYPE_OPTIONS[i].value === type) {
      return t(CLUSTER_TYPE_OPTIONS[i].label);
    }
  }
  return type;
}

/** 确认团伙：只送 type/fingerprint/name/user_count（member_ids 见文件头注释） */
async function submitConfirm(_id: string, body: FormPayload): Promise<string[]> {
  const name = `${body['name'] ?? ''}`;
  const type = `${body['type'] ?? ''}`;
  const rawCount = body['user_count'];
  const req: RiskClusterConfirmRequest = {
    type: type,
    fingerprint: `${body['fingerprint'] ?? ''}`,
    name: name,
    user_count: typeof rawCount === 'number' ? rawCount : 0
  };
  await RiskService.confirmCluster(req);
  const lines: string[] = [];
  lines.push(t('risk.cluster.confirm_ok').replace('{name}', name).replace('{type}', clusterTypeText(type)));
  lines.push(t('risk.cluster.members_not_sent'));
  return lines;
}

@Entry
@Component
struct RiskClusterPage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State rows: RiskClusterItem[] = [];
  /** 检测候选（不落库）：铺在列表上方，每条可一键带进确认表单 */
  @State candidates: RiskClusterCandidate[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State detecting: boolean = false;
  @State busyId: string = '';
  @State confirmOpen: boolean = false;
  @State confirmInitial: FormValues = {};
  @State statusOpen: boolean = false;
  @State statusWho: string = '';
  @State statusInitial: FormValues = {};
  @State resultOpen: boolean = false;
  @State resultTitle: string = '';
  @State resultLines: string[] = [];
  private statusId: string = '';
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
      const result = await RiskService.listClusters(this.page, this.limit);
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

  private reportError(e: Object): void {
    if (this.redirectIfUnauthorized(e)) {
      return;
    }
    promptAction.showToast({ message: e instanceof Error && e.message.length > 0 ? e.message : t('res.op_failed') });
  }

  /** 聚类检测：全局动作、无行上下文，只出候选不落库 */
  private async detect(): Promise<void> {
    if (this.detecting) {
      return;
    }
    this.detecting = true;
    try {
      const result = await RiskService.detectClusters();
      this.candidates = result.candidates ?? [];
      promptAction.showToast({
        message: this.candidates.length > 0
          ? t('risk.cluster.detect_found').replace('{count}', `${this.candidates.length}`).replace('{days}', `${result.window_days}`)
          : t1('risk.cluster.detect_none', 'days', `${result.window_days}`)
      });
    } catch (e) {
      this.reportError(e);
    } finally {
      this.detecting = false;
    }
  }

  /** 空表单的人工确认（type 给个缺省，避免空选） */
  private openManualConfirm(): void {
    const initial: FormValues = {};
    initial.name = '';
    initial.type = 'manual';
    initial.fingerprint = '';
    initial.user_count = '0';
    this.confirmInitial = initial;
    this.confirmOpen = true;
  }

  /** 候选 → 确认表单：把完整指纹带过去（列表里显示的是掩码，手抄不了） */
  private openCandidateConfirm(candidate: RiskClusterCandidate): void {
    const initial: FormValues = {};
    initial.name = '';
    initial.type = candidate.type;
    initial.fingerprint = candidate.fingerprint;
    initial.user_count = `${candidate.user_count}`;
    this.confirmInitial = initial;
    this.confirmOpen = true;
  }

  private openStatus(item: RiskClusterItem): void {
    const initial: FormValues = {};
    initial.status = `${item.status}`;
    this.statusInitial = initial;
    this.statusWho = item.name;
    this.statusId = item.id;
    this.statusOpen = true;
  }

  /** 状态写入：三选一，值原样发（不翻转、不推断目标态）；刷新由弹框关闭时的 onClose 统一做 */
  private async submitStatus(id: string, body: FormPayload): Promise<string[]> {
    const status = Number(`${body['status'] ?? ''}`);
    await RiskService.setClusterStatus(id, status);
    const lines: string[] = [];
    lines.push(t('risk.cluster.status_changed').replace('{name}', this.statusWho).replace('{status}', clusterStatusText(status)));
    return lines;
  }

  /** 成员（只读）：member_ids 为空时服务端按 type + fingerprint 现查 */
  private async membersRow(item: RiskClusterItem): Promise<void> {
    this.busyId = item.id;
    try {
      const result = await RiskService.clusterMembers(item.id);
      this.resultTitle = t1('risk.cluster.members_titled', 'name', item.name);
      this.resultLines = this.memberLines(result);
      this.resultOpen = true;
    } catch (e) {
      this.reportError(e);
    } finally {
      this.busyId = '';
    }
  }

  private memberLines(result: RiskClusterMembersResult): string[] {
    const lines: string[] = [];
    const members = result.members ?? [];
    lines.push(t('risk.cluster.members_head')
      .replace('{type}', clusterTypeText(result.cluster.type)).replace('{fp}', result.cluster.fingerprint_masked)
      .replace('{count}', `${result.cluster.user_count}`).replace('{status}', clusterStatusText(result.cluster.status)));
    lines.push(t1('risk.cluster.members_listed', 'n', `${members.length}`));
    for (let i = 0; i < members.length; i++) {
      const member: RiskClusterMember = members[i];
      lines.push(t('risk.cluster.member_line')
        .replace('{n}', `${i + 1}`).replace('{name}', member.username).replace('{id}', member.id));
    }
    if (members.length === 0) {
      lines.push(t('risk.cluster.members_empty'));
    }
    return lines;
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      if (this.confirmOpen) {
        ExtraActionDialog({
          title: t('risk.cluster.confirm_titled'),
          fields: CONFIRM_FIELDS,
          initial: this.confirmInitial,
          submit: submitConfirm,
          onClose: () => {
            this.confirmOpen = false;
            this.loadData(true);
          }
        })
      }

      if (this.statusOpen) {
        ExtraActionDialog({
          title: t1('risk.cluster.status_titled', 'name', this.statusWho),
          fields: STATUS_FIELDS,
          initial: this.statusInitial,
          actionId: this.statusId,
          submit: (id: string, body: FormPayload): Promise<string[]> => this.submitStatus(id, body),
          onClose: () => {
            this.statusOpen = false;
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

        Text(t('risk.index.cluster'))
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
          .layoutWeight(1)
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      // 页面级动作：检测是全局的（没有行上下文），确认是人工入口
      Row() {
        Text(this.candidates.length > 0 ? t1('risk.cluster.cand_count', 'count', `${this.candidates.length}`) : t('risk.cluster.cand_recent'))
          .fontSize(13)
          .fontColor('#666666')
          .layoutWeight(1)

        this.smallButton(t('risk.cluster.btn_detect'), '#1677FF', '#E6F0FF', !this.detecting, () => {
          this.detect();
        });
        this.smallButton(t('risk.cluster.btn_manual'), '#52C41A', '#E6FFF0', true, () => {
          this.openManualConfirm();
        });
      }
      .width('100%')
      .height(48)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')
      .border({ width: { top: 0.5 }, color: '#EEEEEE' })

      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ onRetry: () => this.loadData() });
      } else if (this.rows.length === 0 && this.candidates.length === 0) {
        EmptyView({ message: t('risk.cluster.empty') });
      } else {
        List() {
          // 候选段（不落库）：点「确认」把 type/fingerprint/user_count 带进确认表单
          ForEach(this.candidates, (candidate: RiskClusterCandidate) => {
            ListItem() {
              this.candidateRow(candidate)
            }
          }, (candidate: RiskClusterCandidate, index: number) => `c${index}:${candidate.fingerprint}`)

          ForEach(this.rows, (item: RiskClusterItem) => {
            ListItem() {
              this.clusterRow(item)
            }
          }, (item: RiskClusterItem) => item.id)
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

  /** 一条检测候选（未落库）：底色与已确认团伙区分开 */
  @Builder
  candidateRow(candidate: RiskClusterCandidate) {
    Row() {
      Column() {
        Text(t1('risk.cluster.cand_row', 'type', clusterTypeText(candidate.type)))
          .fontSize(14)
          .fontColor('#333333')

        Text(t('risk.cluster.cand_row_sub').replace('{fp}', candidate.fingerprint_masked).replace('{count}', `${candidate.user_count}`))
          .fontSize(12)
          .fontColor('#999999')
          .margin({ top: 2 })
      }
      .alignItems(HorizontalAlign.Start)
      .layoutWeight(1)

      this.smallButton(t('risk.cluster.btn_confirm'), '#52C41A', '#E6FFF0', true, () => {
        this.openCandidateConfirm(candidate);
      });
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 10, bottom: 10 })
    .backgroundColor('#FFFBF0')
    .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
  }

  /** 一行已确认团伙：名称 + 三态徽标 + 摘要 + 成员/状态两个动作 */
  @Builder
  clusterRow(item: RiskClusterItem) {
    Column() {
      Row() {
        Text(item.name)
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        Text(clusterStatusText(item.status))
          .fontSize(10)
          // 三态各有各的颜色：已处置红、观察中橙、误判灰（用二态的红/绿会分不出后两者）
          .fontColor(item.status === 2 ? '#FF4D4F' : (item.status === 0 ? '#999999' : '#FA8C16'))
          .backgroundColor('#F5F5F5')
          .borderRadius(2)
          .padding({ left: 6, right: 6, top: 2, bottom: 2 })
      }
      .width('100%')

      Row() {
        Text(t('risk.cluster.row_summary')
          .replace('{type}', clusterTypeText(item.type)).replace('{fp}', item.fingerprint_masked)
          .replace('{n}', `${item.user_count}`).replace('{updated}', item.updated_at))
          .fontSize(12)
          .fontColor('#999999')
          .maxLines(2)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .layoutWeight(1)

        this.smallButton(t('risk.cluster.btn_members'), '#1677FF', '#E6F0FF', this.busyId !== item.id, () => {
          this.membersRow(item);
        });
        this.smallButton(t('risk.cluster.btn_status'), '#FA8C16', '#FFF2E6', this.busyId !== item.id, () => {
          this.openStatus(item);
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
