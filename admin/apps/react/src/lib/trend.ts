/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * `/admin/v1/risk/hit-trend` 的 series 是**映射**（规则类型 → 该类型自己的逐日序列）：
 * `{device: [{bucket, hits}, …], ip: [{bucket, hits}, …]}`，各类型的 bucket 集合**可以不等**
 * （某天某类型零命中 ⇒ 服务端那条 SQL 不会给这类型造出那天的行）。
 *
 * 为什么不能直接交给 `AutoView`：它的 `firstArray` 只取**第一个数组**，于是
 * `{series: {device: […], ip: […]}}` 除第一个类型外的数据会被**静默丢掉** ——
 * 图看着完全正常，只是少画了几条线（零命中与丢数据同形）。
 *
 * 故这里先取各类型 bucket 的**并集**当共享横轴、缺的补 0，再交给 `LineChart`。
 * 纯逻辑放 `.ts` 是为了能被 `node --test` 加载（本树的 `--experimental-strip-types` 不认 `.tsx`）。
 */
export type TrendPoint = { bucket?: unknown; hits?: unknown };

/** 横轴按**字符串**排序：服务端的 bucket 是 `DATE_FORMAT(…, '%Y-%m-%d')`，字典序即时间序。 */
export function alignTrend(series: Record<string, TrendPoint[]>): {
  labels: string[];
  lines: { name: string; values: number[] }[];
} {
  // 空 bucket 不进横轴：并进来会凭空多出一个没有日期的刻度（实测：不带 bucket 的点会让 x 轴多一格）
  const buckets = Object.values(series)
    .flat()
    .map((point) => String(point?.bucket ?? ''))
    .filter((bucket) => bucket !== '');
  const labels = [...new Set(buckets)].sort();
  const at = new Map(labels.map((bucket, index) => [bucket, index]));

  const lines = Object.entries(series).map(([name, points]) => {
    const values = new Array<number>(labels.length).fill(0);
    for (const point of points) {
      const index = at.get(String(point?.bucket ?? ''));
      // 认不出的 bucket 直接丢：往 0 号位塞会把某类型的数据挪到别的日期上，比缺一条线更难发现
      if (index === undefined) continue;
      values[index] = Number(point?.hits) || 0;
    }
    return { name, values };
  });

  return { labels, lines };
}
