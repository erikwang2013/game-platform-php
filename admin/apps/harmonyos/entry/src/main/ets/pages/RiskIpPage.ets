/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { RiskService } from '../service/RiskService';
import { RiskIpItem } from '../model/RiskModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { FormField } from '../common/FormDialog';
import { FormPayload } from '../model/DataModels';
import { PagerBar, ExtraActionDialog } from '../common/ResourceViews';

/**
 * IP 信誉页 —— 列表（只读）+ 四个页面级动作：加入黑名单 / 加入白名单 / 申诉放行 / 重查信誉。
 *
 * 行上没有按钮，理由与设备页同款：列表只回 `substr(ip_hash, 0, 8) . '****'`，
 * 四个端点收的是**原文 IP**（`filter_var(..., FILTER_VALIDATE_IP)`），掩码串过不了校验。
 * 四个动作都是 `ip` 在**请求体**里。
 *
 * **没有「解封」端点**：黑名单写入（source=internal_blacklist、score=0）之后写不回「中性」态，
 * 只能靠白名单/申诉覆盖（两者在服务端是**同一段代码、同一对 source/score**，
 * RiskIpController 的 whitelist 与 appeal 除了注解文案没有差别）—— 故这里保留两条路只为不吞端点，
 * 结果文案会如实说明它们是同一件事。
 *
 * 端点：GET  /admin/v1/risk/ip/list?page=&size=（响应 {total, items}）
 *       POST /admin/v1/risk/ip/{block|whitelist|appeal|recheck} {ip}
 */

const IP_FIELDS: FormField[] = [
  {
    name: 'ip', label: '完整 IP 地址', type: 'text', required: true,
    hint: '要原文 IP（IPv4/IPv6 均可）。列表只显示掩码，掩码串过不了服务端的 filter_var 校验。',
    placeholder: '如 203.0.113.7'
  }
];

/** 动作值 → 按钮与弹框文案（四个动作共用一张表单，只有 IP 一个入参） */
function ipActionTitle(mode: string): string {
  if (mode === 'block') {
    return '加入黑名单';
  }
  if (mode === 'whitelist') {
    return '加入白名单';
  }
  if (mode === 'appeal') {
    return '申诉放行';
  }
  return '重查信誉';
}

async function submitIp(mode: string, body: FormPayload): Promise<string[]> {
  const ip = `${body['ip'] ?? ''}`.trim();
  if (mode === 'recheck') {
    return RiskService.recheckIp(ip);
  }
  return RiskService.writeIpReputation(ip, mode);
}

@Entry
@Component
struct RiskIpPage {
  @State rows: RiskIpItem[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State actionOpen: boolean = false;
  @State actionMode: string = 'block';
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
      const result = await RiskService.listIps(this.page, this.limit);
      this.rows = result.items ?? [];
      this.total = result.total ?? 0;
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

  private redirectIfUnauthorized(e: Object): boolean {
    if (e instanceof Error && e.message === 'UNAUTHORIZED') {
      router.replaceUrl({ url: 'pages/LoginPage' });
      return true;
    }
    return false;
  }

  private openAction(mode: string): void {
    this.actionMode = mode;
    this.actionOpen = true;
  }

  /** 来源 → 徽标；外部来源直接把 source 摆出来（那是判定依据，不该被抹成「其它」） */
  private sourceText(item: RiskIpItem): string {
    if (item.source === 'internal_blacklist') {
      return '黑名单';
    }
    if (item.source === 'internal_whitelist') {
      return '白名单';
    }
    return item.source.length > 0 ? item.source : '未知来源';
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

      if (this.actionOpen) {
        ExtraActionDialog({
          title: ipActionTitle(this.actionMode),
          fields: IP_FIELDS,
          actionId: this.actionMode,
          submit: submitIp,
          onClose: () => {
            this.actionOpen = false;
            this.loadData(true);
          }
        })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }

  @Builder
  smallButton(text: string, color: string, bg: string, action: () => void) {
    Button(text)
      .fontSize(12)
      .fontColor(color)
      .backgroundColor(bg)
      .borderRadius(4)
      .height(28)
      .padding({ left: 8, right: 8 })
      .margin({ left: 6 })
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

        Text('IP 信誉')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
          .layoutWeight(1)
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      // 页面级动作条：四个动作都要人工粘完整 IP，行上放不下（行里也没有 IP 原文）
      Row() {
        Text('信誉操作')
          .fontSize(13)
          .fontColor('#666666')

        Blank()

        this.smallButton('拉黑', '#FF4D4F', '#FFE6E6', () => {
          this.openAction('block');
        });
        this.smallButton('加白', '#52C41A', '#E6FFF0', () => {
          this.openAction('whitelist');
        });
        this.smallButton('申诉放行', '#1677FF', '#E6F0FF', () => {
          this.openAction('appeal');
        });
        this.smallButton('重查', '#FA8C16', '#FFF2E6', () => {
          this.openAction('recheck');
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
      } else if (this.rows.length === 0) {
        EmptyView({ message: '暂无 IP 信誉记录' });
      } else {
        List() {
          ForEach(this.rows, (item: RiskIpItem) => {
            ListItem() {
              this.ipRow(item)
            }
          }, (item: RiskIpItem, index: number) => `${index}:${item.ip_masked}`)
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

  /** 一行 IP 信誉：掩码 + 来源徽标 + 分值/命中数。没有 id、没有原文 IP ⇒ 不给行内动作 */
  @Builder
  ipRow(item: RiskIpItem) {
    Column() {
      Row() {
        Text(item.ip_masked)
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        Text(this.sourceText(item))
          .fontSize(10)
          .fontColor(item.reputation_score >= 60 ? '#52C41A' : '#FF4D4F')
          .backgroundColor(item.reputation_score >= 60 ? '#E6FFF0' : '#FFE6E6')
          .borderRadius(2)
          .padding({ left: 6, right: 6, top: 2, bottom: 2 })
      }
      .width('100%')

      Text(`信誉分 ${item.reputation_score} · 命中 ${item.hit_count} 次 · 首次 ${item.first_seen_at} · 最近 ${item.last_seen_at}`)
        .fontSize(12)
        .fontColor('#999999')
        .maxLines(2)
        .textOverflow({ overflow: TextOverflow.Ellipsis })
        .width('100%')
        .margin({ top: 6 })
    }
    .width('100%')
    .padding({ left: 16, right: 16, top: 10, bottom: 10 })
    .alignItems(HorizontalAlign.Start)
    .backgroundColor('#FFFFFF')
    .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
  }
}
