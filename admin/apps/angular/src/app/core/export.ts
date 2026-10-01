/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Api, Row } from './api.service';
import { dash } from './util';

const A = '/admin/v1/';

/** POST /admin/v1/export/pdf 的 `type: 'table'` 请求体（形状见 ExportController::buildPdfHtml） */
export interface PdfTable {
  type: 'table';
  title: string;
  data: { columns: string[]; rows: string[][] };
}

/** 屏幕上那张表的列（`colsOf` 的产物：键 + 已查表的表头） */
export interface PdfColumn {
  key: string;
  label: string;
}

/**
 * 组装 PDF 导出请求体。列与单元格**照屏幕上那张表**取（列由 `colsOf` 推、单元格走 `dash`，
 * 与 ui-table 同一对函数）—— 于是界面上看不见的字段不会从 PDF 这条路漏出去。
 *
 * 只此一种 `type`：另一个分支是 `dashboard`，入参是 `data.stats`，与表格互不相通
 * （塞表格数据进去出来的是**空白 PDF**）⇒ 不做成可配的旋钮（与 react 树 lib/pdf-table.ts 同款判断）。
 */
export function buildPdfTable(title: string, cols: PdfColumn[], rows: Row[]): PdfTable {
  return {
    type: 'table',
    title,
    data: {
      columns: cols.map((c) => c.label),
      rows: rows.map((r) => cols.map((c) => dash(r[c.key]))),
    },
  };
}

/**
 * 导出本页 PDF。该端点**不取数**：data 就是传进去的这几行 ——
 * 界面上第 2 页就导出第 2 页，不是全量（按钮名里带行数，别让人以为是「全部」）。
 */
export const downloadPdf = (api: Api, body: PdfTable, fallback = 'export.pdf'): Promise<string> =>
  api.download('POST', A + 'export/pdf', body, fallback);

/**
 * 导出整张服务端表（最多 10000 行，**不认屏幕上的筛选** —— 参数里的 `conditions` 是精确 where，
 * 页面上的关键词/日期都表达不了，故一律发空对象）。
 *
 * `table` 必须落在 ExportController::getExportColumns 的**白名单**里
 * （admin_user / operation_log / admin_role / system_config），否则服务端一栏都不给、
 * 也不报错，照样回一个空文件。`columns: []` = 用服务端自己的字段映射（导出全部列）。
 */
export const downloadExcel = (api: Api, table: string): Promise<string> =>
  api.download(
    'POST',
    A + 'export/excel',
    { table, columns: [], conditions: {} },
    `export_${table}.xlsx`,
  );

/**
 * 导出全平台流水（POST /export/transactions）。
 * 只认一个可选的 `type`，而它的值域全仓没有能列出来的端点 ⇒ 不摆 type 下拉
 * （硬编一张表就是「看着能筛、筛出来是空」的假控件）；也没有日期范围 ——
 * 服务端是 `orderBy created_at desc limit 10000`。
 */
export const downloadTransactions = (api: Api): Promise<string> =>
  api.download('POST', A + 'export/transactions', undefined, 'transactions.xlsx');
