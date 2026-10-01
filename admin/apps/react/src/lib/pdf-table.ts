/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 表格的**纯文本口径** + PDF 导出载荷。
 *
 * 放 `.ts` 不放组件里只为一个理由：`npm test` 走 `node --test` 且只收 **.ts 的测试**，
 * 加载不了 `.tsx` ⇒ 这两段纯逻辑想有回归，就得待在 `.ts` 里（同 lib/trend.ts）。
 */
import { t, type MessageKey } from '../i18n/index.ts';

/**
 * 任意单元格值 → 纯文本（屏幕上那张表与 PDF **共用**同一口径）。
 * 空 → 「—」、布尔 → 是/否、数组 → 「n 项」、对象 → 「{…}」，未知结构降级而不是白屏。
 *
 * 从 DataTable 提出来是因为 PDF 的 `data.rows` 只收字符串数组 —— 把渲染分支的返回值
 * 直接塞进去，长文本那一支是个 `<span>` 元素，进 PDF 会变成 `[object Object]`。
 */
export function cellText(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'boolean') return value ? t('app.yes') : t('app.no');
  if (typeof value === 'number' || typeof value === 'string') return String(value);
  if (Array.isArray(value)) return value.length === 0 ? '—' : t('table.items', { count: value.length });
  return '{…}';
}

/** 只存在于屏幕上的两列（行尾动作 / 批量勾选），任何导出都不该带上它们 */
const UI_ONLY = ['__actions', '__pick'];

/** POST /admin/v1/export/pdf 的 `type: 'table'` 请求体（见 ExportController::buildPdfHtml） */
export type PdfTable = {
  type: 'table';
  title: string;
  data: { columns: string[]; rows: string[][] };
};

/**
 * 组装导出请求体。列与单元格**照屏幕上那几列**取（含模块自定义的列顺序与 `hide`）——
 * 于是界面上看不见的字段（设备页的 `fp_hash` 之类）不会从 PDF 这条路上漏出去。
 *
 * 只此一种 `type`：另一个分支是 `dashboard`，入参是 `data.stats`，与表格互不相通
 * （塞表格数据进去出来的是**空白 PDF**）⇒ 干脆不把它做成可配的旋钮。
 */
export function buildPdfTable(
  title: string,
  columns: { key: string; label: string }[],
  rows: Record<string, unknown>[],
): PdfTable {
  const visible = columns.filter((column) => !UI_ONLY.includes(column.key));
  return {
    type: 'table',
    title,
    data: {
      columns: visible.map((column) => t(column.label as MessageKey)),
      rows: rows.map((row) => visible.map((column) => cellText(row[column.key]))),
    },
  };
}
