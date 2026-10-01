/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { RiskService } from '../service/RiskService';
import { RiskDeviceItem } from '../model/RiskModels';
import { LoadingView, ErrorView, EmptyView, ResultDialog } from '../common/Components';
import { confirmAction } from '../common/FormDialog';
import { PagerBar } from '../common/ResourceViews';
import { t } from '../i18n/Translations';

/**
 * 设备指纹页 —— 列表 + **行内**拉黑 / 解封。
 *
 * 列表每行回**完整 `fp_hash`**（RiskDeviceController::list 同时给 fp_hash 与 fp_masked）：
 * block/unblock 的入参就是这串 64 位十六进制（`preg_match('/^[a-f0-9]{64}$/i')`），
 * 掩码提交不上去 ⇒ 两个动作都挂在行上，运营不必再手抄哈希。展示仍用掩码（fp_masked）。
 *
 * **拉黑是真阻断**：`RiskService::check()` 对人工拉黑短路成 `block`，与任何规则是否启用无关
 * （install.sql 里那条 device 规则 status=0，早先按「等规则命中」实现等于拉黑了也不挡）——
 * 该设备上的账号在登录/充值/提现都会被拒。标记存在 Redis，**TTL 30 天，到期自然解封**；
 * Redis 不可用时按未拉黑处理（fail-open），一次缓存故障不把全站挡掉。
 *
 * 端点：GET  /admin/v1/risk/device/list?page=&size=（响应 {total, items}）
 *       POST /admin/v1/risk/device/block   {fp_hash}
 *       POST /admin/v1/risk/device/unblock {fp_hash}
 */

@Entry
@Component
struct RiskDevicePage {
  @StorageLink('app_locale') localeCode: string = 'en';
  @State rows: RiskDeviceItem[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  /** 正在提交的指纹：同一行防连点，别的行也一起置灰（避免两个动作交叉） */
  @State busyKey: string = '';
  @State resultOpen: boolean = false;
  @State resultTitle: string = '';
  @State resultLines: string[] = [];
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
      const result = await RiskService.listDevices(this.page, this.limit);
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

  /** 有完整指纹、且没有行在提交时才可点 */
  private canAct(item: RiskDeviceItem): boolean {
    return this.busyKey === '' && (item.fp_hash ?? '').length > 0;
  }

  /** 拉黑：不可轻率 ⇒ 先二次确认（文案带掩码，指名道姓），确认后才发 */
  private async blockRow(item: RiskDeviceItem): Promise<void> {
    const fp = item.fp_hash ?? '';
    if (fp.length === 0) {
      promptAction.showToast({ message: t('risk.device.no_hash') });
      return;
    }
    const ok = await confirmAction(t('risk.device.confirm_block_title'),
      t('risk.device.confirm_block').replace('{masked}', item.fp_masked),
      t('risk.device.action_block'), '#FF4D4F');
    if (!ok) {
      return;
    }
    await this.runDevice(fp, 'block', item.fp_masked);
  }

  /** 解封不做二次确认：它是恢复动作，误点的代价远小于拉黑 */
  private async unblockRow(item: RiskDeviceItem): Promise<void> {
    const fp = item.fp_hash ?? '';
    if (fp.length === 0) {
      promptAction.showToast({ message: t('risk.device.no_hash_unblock') });
      return;
    }
    await this.runDevice(fp, 'unblock', item.fp_masked);
  }

  /** 两个动作的公共收口：置忙 → 发请求 → 结果弹框 → 重载（blocked 徽标要跟着变） */
  private async runDevice(fp: string, mode: string, masked: string): Promise<void> {
    this.busyKey = fp;
    try {
      const lines = mode === 'block' ? await RiskService.blockDevice(fp) : await RiskService.unblockDevice(fp);
      const action = mode === 'block' ? t('risk.device.action_block') : t('risk.device.action_unblock');
      this.resultTitle = t('risk.device.result_titled').replace('{action}', action).replace('{masked}', masked);
      this.resultLines = lines;
      this.resultOpen = true;
      this.loadData(true);
    } catch (e) {
      this.reportError(e);
    } finally {
      this.busyKey = '';
    }
  }

  build() {
    Stack({ alignContent: Alignment.Center }) {
      this.mainView()

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

        Text(t('risk.index.device'))
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
          .layoutWeight(1)
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
        EmptyView({ message: t('risk.device.empty') });
      } else {
        List() {
          ForEach(this.rows, (item: RiskDeviceItem) => {
            ListItem() {
              this.deviceRow(item)
            }
            // 行里没有 id ⇒ 键用下标补唯一性（掩码前 8 位可能撞；fp_hash 缺字段时也保证唯一）
          }, (item: RiskDeviceItem, index: number) => `${index}:${item.fp_hash ?? item.fp_masked}`)
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

  /** 一行设备：掩码 + 标记 + 行内动作（按 blocked 只出一个：未拉黑给「拉黑」，已拉黑给「解封」） */
  @Builder
  deviceRow(item: RiskDeviceItem) {
    Column() {
      Row() {
        Text(item.fp_masked)
          .fontSize(15)
          .fontWeight(FontWeight.Medium)
          .layoutWeight(1)

        Text(item.blocked ? t('risk.device.blocked') : t('risk.device.not_blocked'))
          .fontSize(10)
          .fontColor(item.blocked ? '#FF4D4F' : '#999999')
          .backgroundColor(item.blocked ? '#FFE6E6' : '#F5F5F5')
          .borderRadius(2)
          .padding({ left: 6, right: 6, top: 2, bottom: 2 })
      }
      .width('100%')

      Row() {
        Text(t('risk.device.summary').replace('{cseg}', item.ip_c_segment)
          .replace('{accounts}', `${item.account_count}`)
          .replace('{first}', item.first_seen_at).replace('{last}', item.last_seen_at))
          .fontSize(12)
          .fontColor('#999999')
          .maxLines(2)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .layoutWeight(1)

        if (item.blocked) {
          this.smallButton(t('risk.device.action_unblock'), '#52C41A', '#E6FFF0', this.canAct(item), () => {
            this.unblockRow(item);
          });
        } else {
          this.smallButton(t('risk.device.action_block'), '#FF4D4F', '#FFE6E6', this.canAct(item), () => {
            this.blockRow(item);
          });
        }
      }
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
