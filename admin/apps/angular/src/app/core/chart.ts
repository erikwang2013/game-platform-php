/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
// ponytail: 这里的浮点只算绘图几何（0..100 归一化坐标），不参与金额/业务计算

/** 序列 → SVG polyline points（viewBox 0 0 100 100，配 preserveAspectRatio="none"） */
export function linePoints(values: number[], min?: number, max?: number): string {
  if (values.length < 2) return '';
  const lo = min ?? Math.min(...values);
  const hi = max ?? Math.max(...values);
  const span = hi - lo || 1;
  const step = 100 / (values.length - 1);
  return values
    .map((v, i) => `${(i * step).toFixed(2)},${(100 - ((v - lo) / span) * 100).toFixed(2)}`)
    .join(' ');
}

/** 同一组点闭合成填充区域的 points */
export function areaPoints(values: number[], min?: number, max?: number): string {
  const line = linePoints(values, min, max);
  return line ? `0,100 ${line} 100,100` : '';
}

/** 多序列共用纵轴范围，避免两条线各画各的无法对比 */
export function bounds(series: number[][]): [number, number] {
  const all = series.flat();
  return all.length ? [Math.min(...all), Math.max(...all)] : [0, 1];
}
