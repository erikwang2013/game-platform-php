/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 仪表盘页词条（dashboard.ts）。同一套约定：`键: [英文, 中文]`，中文一侧**逐字等于抽取前的界面原文**。
 *
 * 键名尽量沿用 `admin/apps/flutter` 的 translations.dart（那里有 `dashboard.*` 一族）：
 * `dashboard.title` 与那边的同名同义。标签页用 `dashboard.tab.*`（flutter 没有标签页，是本树自定）。
 */
export const DASHBOARD: Record<string, [string, string]> = {
  'dashboard.title': ['Dashboard', '仪表盘'],
  'dashboard.subtitle': ['Live stats / Platform overview / Health & logs', '实时统计 / 平台概览 / 健康与日志'],

  // ---- 六个标签页（顺序 = tabs 数组顺序）----
  'dashboard.tab.overview': ['Overview', '总览'],
  'dashboard.tab.platform': ['Platform', '平台'],
  'dashboard.tab.health': ['Health', '健康'],
  'dashboard.tab.metrics': ['Metrics', '指标'],
  'dashboard.tab.log': ['Operation logs', '操作日志'],
  'dashboard.tab.search': ['Global search', '全局检索'],

  // ---- 正文 ----
  'dashboard.metrics_raw': ['Metrics (Prometheus text format)', '指标原文（Prometheus text format）'],
  'dashboard.search_hint': ['Keyword', '关键词'],
  'dashboard.empty_tip': ['No data on this endpoint', '该接口暂无数据'],
};
