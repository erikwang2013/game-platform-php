/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 数据分析页词条（analytics.ts）。
 *
 * 与 `dict/frame.ts` 同一套约定：`键: [英文, 中文]`、占位符 `{name}`。**中文一侧逐字等于抽取前的
 * 界面原文**。
 *
 * ⚠ 这一域在 `admin/apps/flutter` 里**没有对应页面**（translations.dart 里 `analytics.*` 一个键都没有，
 * 那边是 dashboard/reports 两个页面顶着的），所以英文侧没有可沿用的说法 —— 是按本页语义新写的，
 * 键名也自成一组（`analytics.tab.*` 是那 12 个标签页）。
 */
export const ANALYTICS: Record<string, [string, string]> = {
  'analytics.title': ['Analytics', '数据分析'],
  'analytics.subtitle': ['Traffic / Revenue / Retention / Economy', '流量 / 收入 / 留存 / 经济'],
  /** 天数下拉（值仍是 7/14/30，只是文案带参数） */
  'analytics.last_days': ['Last {n} days', '近 {n} 天'],

  // ---- 12 个标签页（顺序 = tabs 数组顺序）----
  'analytics.tab.overview': ['Overview', '总览'],
  'analytics.tab.dau': ['DAU trend', 'DAU 趋势'],
  'analytics.tab.rank': ['Game ranking', '游戏排行'],
  'analytics.tab.funnel': ['Conversion funnel', '转化漏斗'],
  'analytics.tab.retention': ['Retention', '留存'],
  'analytics.tab.arpu': ['ARPU', 'ARPU'],
  'analytics.tab.revenue': ['Revenue', '营收'],
  'analytics.tab.conversion': ['Conversion', '转化'],
  'analytics.tab.hourly': ['Hourly', '分时'],
  'analytics.tab.action': ['Action distribution', '行为分布'],
  'analytics.tab.probability': ['Probability', '概率'],
  'analytics.tab.economy': ['Economy', '经济'],

  // ---- 各图形种类的空态（四种图形 + 表格兜底）----
  'analytics.no_scalars': ['No scalar metrics on this endpoint', '该接口暂无标量指标'],
  'analytics.no_series': ['No time-series data yet', '暂无时间序列数据'],
  'analytics.no_rank': ['No ranking data yet', '暂无排行数据'],
  'analytics.no_funnel': ['No funnel data yet', '暂无漏斗数据'],
  'analytics.no_data': ['No data on this endpoint', '该接口暂无数据'],
};
