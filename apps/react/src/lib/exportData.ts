/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 「导出我的数据」的落盘半截（`GET /user/export-data`）。
 *
 * 与 admin 树 `lib/download.ts` 的**形状不同**：那条链路的成功响应是二进制附件
 * （`response()->download()` + `Content-Disposition`，失败才回信封），所以要在传输层按
 * content-type 分流。本端点**成功也回普通信封**（`UserController::exportData:161` 的
 * `$this->success($data, …)`）⇒ 用普通的 `api.exportData()` 取 JSON，在**这一层**自己
 * 捏一个 Blob 落盘。别把 admin 那套 content-type 分流搬过来，那会给信封凭空加一条分支。
 *
 * 纯逻辑（文件名、序列化）与 DOM 分开：前者供 `node --test` 直接覆盖（本树无 DOM 底座）。
 */
import { t } from '../i18n/index.ts';
import type { ExportData } from './types.ts';

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
 * 触发保存。DOM 那半截在本树无单测（`node --test` 没有 DOM），真机 e2e 里验。
 * 落盘动作照 admin 树 `lib/download.ts:64-74` 的口径：同一个 `<a download>` 序列，
 * **同样延迟一轮再 revoke**（立刻 revoke 会让部分浏览器取消下载）。
 */
export function saveBlob(blob: Blob, name: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  link.style.display = 'none';
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

/** 屏幕上那行「共 N 条」的读数：四类明细各自计数，别相加成一个数（相加看不出缺哪一类） */
export function exportCounts(d: ExportData): string {
  return [
    t('export.count_transactions', { count: d.transactions.length }),
    t('export.count_exchange', { count: d.exchange_records.length }),
    t('export.count_deposit', { count: d.deposit_orders.length }),
    t('export.count_withdraw', { count: d.withdraw_orders.length }),
  ].join(' · ');
}
