/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import type { ExportData } from './api.service';
import { t } from './i18n/i18n';

/**
 * 「导出我的数据」的落盘半截（`GET /user/export-data`）。语义与 react 树 `lib/exportData.ts`
 * 逐条对齐 —— 同一端点两个客户端，不许漂移。
 *
 * 与 admin 树 `lib/download.ts` 的**形状不同**：那条链路的成功响应是二进制附件
 * （`response()->download()` + `Content-Disposition`，失败才回信封），所以要在传输层按
 * content-type 分流。本端点**成功也回普通信封**（`UserController::exportData` 的
 * `$this->success($data, …)`）⇒ 用普通的 `api.exportData()` 取 JSON，在**这一层**自己
 * 捏一个 Blob 落盘。别把 admin 那套 content-type 分流搬过来，那会给信封凭空加一条分支。
 *
 * 纯逻辑（文件名 / 序列化 / 计数）与 DOM 分开，前者由 export-data.spec.ts 直接覆盖。
 */

/**
 * 落盘文件名。只认服务端的 `exported_at`（`2026-10-01 10:00:00`）——
 * 拿本机时钟当第二真值源会出现「文件名的时刻 ≠ 服务端说的导出时刻」。
 *
 * 取不出数字（字段缺失/格式变了）时回 `unknown`，**不退回本机时间**：文件名难看是小事，
 * 印一个和服务端对不上的时刻是假信息。顺带把非数字全部剥掉，落盘名里不会有路径成分。
 */
export function exportName(exportedAt: string): string {
  const digits = (exportedAt ?? '').replace(/\D/g, '');
  return `game-platform-export-${digits || 'unknown'}.json`;
}

/** 缩进两格：导出的东西是给人读的，压缩成一行的 JSON 等于没导出 */
export function exportBlob(data: ExportData): Blob {
  return new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
}

/**
 * 屏幕上那行「共 N 条」的读数：四类明细各自计数，**别相加成一个数**
 * （相加看不出缺哪一类，且四类上限都是 100，加起来只会误导成"共 400 条"）。
 */
export function exportCounts(d: ExportData): string {
  return t('me.export_counts', {
    transactions: d.transactions.length,
    exchanges: d.exchange_records.length,
    deposits: d.deposit_orders.length,
    withdrawals: d.withdraw_orders.length,
  });
}

/**
 * 触发保存。落盘动作照 admin 树 `lib/download.ts` 的口径：同一个 `<a download>` 序列，
 * **同样延迟一轮再 revoke**（立刻 revoke 会让部分浏览器取消下载）。
 */
export function saveBlob(blob: Blob, name: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  a.style.display = 'none';
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 0);
}
