/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 列表分页的纯逻辑：请求参数扇出 + 总数读取。不 import React，供 node --test 直接覆盖。
 */
import type { Query } from './api';

/** 每页条数：与 angular 那棵一致（后端各族默认 15/20，别名全发时以本值为准）。 */
export const PAGE_SIZE = 20;

/**
 * 分页参数。后端读「每页条数」的键有三套，同一族内才统一：
 * - `limit`：15 个控制器（默认 15，如 RoleController::index）
 * - `size`：7 个风控类（默认 20，如 RiskRuleController.php:100）
 * - `per_page`：SearchController.php:31（默认 20）
 * 只发一个别名，另外两族就退回各自默认值，而界面按 PAGE_SIZE 算页数 ⇒ 尾页永远取不到
 * （total=100 时第 76~100 条不可达）。故三个别名一起发：各控制器只读自己认识的那个，多发的被忽略。
 * `page` 三族同形（都读 page），不需要别名。
 *
 * 调用方的筛选条件原样带上 —— 翻页不该把筛选甩掉（甩掉了就是「第二页是另一个列表」）。
 */
export function pageQuery(query: Query | undefined, page: number, pageSize = PAGE_SIZE): Query {
  return { ...query, page, page_size: pageSize, limit: pageSize, size: pageSize, per_page: pageSize };
}

/**
 * 响应里的总数。两种响应形状（`{list,total}` 与 `{total,items}`）的总数都叫 `total`。
 * 读不出来（整表端点、裸数组、树）退回 fallback（调用方给本页行数 ⇒ 页数算成 1，不画分页条），
 * 绝不拿本页行数硬充总数去画一条会翻出空页的分页条。
 */
export function totalOf(data: unknown, fallback = 0): number {
  if (data && typeof data === 'object' && !Array.isArray(data)) {
    const raw = (data as Record<string, unknown>).total;
    const value = Number(raw);
    if (raw !== null && raw !== undefined && Number.isFinite(value) && value >= 0) return value;
  }
  return fallback;
}
