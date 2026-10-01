/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
// 纯逻辑，放 lib/ 才能被 `node --test`（--experimental-strip-types，**不认 .tsx**）直接加载。
// 这里只 `import type`，类型擦除后运行时不解析 DataTable.tsx。
import type { Column, Row } from '../components/DataTable.tsx';

/**
 * 按首行字段推导列。优先展示 preferred 里的业务字段，其余按响应顺序补足，
 * 最多 max 列。字段名不存在时不会凭空造列。
 * hide 里的键一律不进列（如设备列表的 fp_hash：它是行内动作的**提交参数**，不是给人看的列）。
 */
export function columnsFrom(
  rows: Row[],
  preferred: string[] = [],
  max = 8,
  hide: string[] = [],
  /**
   * 字段名 → **词条键**（模块在 `CrudConfig.fields` 里已经为每个字段声明过 `f.*` 标签）。
   * 缺省空表 ⇒ 列标题退化成字段名本身（如 `real_name`）——与 angular 的
   * `heads()[c] ?? c` 同款兜底：**宁可露字段名，也不许凭空造一个查不到的键**。
   */
  labels: Record<string, string> = {},
): Column[] {
  // __ 前缀是内部标记（树行的 __depth/__kids/__lineage、操作列的 __actions），不是响应里的数据字段：
  // 摆成列就是「__depth 0 / __lineage […]」，还会把 preferred 没占满的列位挤掉
  const keys = Object.keys(rows[0] ?? {}).filter((key) => !hide.includes(key) && !key.startsWith('__'));
  const ordered = [
    ...preferred.filter((key) => keys.includes(key)),
    ...keys.filter((key) => !preferred.includes(key)),
  ].slice(0, max);
  return ordered.map((key) => ({ key, label: labels[key] ?? key }));
}
