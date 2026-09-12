/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { dash } from '../lib/format';
import { Empty } from './ui';

const COLORS = ['#0f766e', '#d97706', '#15803d', '#b91c1c'];

export type Series = { name: string; values: number[]; color?: string };

/** 手搓 SVG 折线图：无依赖，够用。数值只做几何映射，不参与业务计算。 */
export function LineChart({ labels, series, height = 200 }: { labels: string[]; series: Series[]; height?: number }) {
  const points = series.flatMap((item) => item.values).filter((value) => Number.isFinite(value));
  if (labels.length === 0 || points.length === 0) return <Empty text="无可绘制的数据" />;

  const W = 640;
  const H = height;
  const PAD = 24;
  const max = Math.max(...points, 0);
  const min = Math.min(...points, 0);
  const span = max - min || 1;
  const count = labels.length;

  const x = (index: number) => PAD + (count <= 1 ? 0 : (index * (W - PAD * 2)) / (count - 1));
  const y = (value: number) => H - PAD - ((value - min) / span) * (H - PAD * 2);

  return (
    <div className="chart">
      <svg viewBox={`0 0 ${W} ${H}`} className="chart-svg" role="img" aria-label={series.map((s) => s.name).join('、')}>
        {[0, 0.5, 1].map((t) => (
          <line key={t} className="gridline" x1={PAD} x2={W - PAD} y1={PAD + t * (H - PAD * 2)} y2={PAD + t * (H - PAD * 2)} />
        ))}
        {series.map((item, index) => (
          <polyline
            key={item.name}
            className="line"
            style={{ stroke: item.color ?? COLORS[index % COLORS.length] }}
            points={item.values.map((value, i) => `${x(i)},${y(value)}`).join(' ')}
          />
        ))}
      </svg>
      <div className="chart-x">
        <span className="muted">{dash(labels[0])}</span>
        <span className="muted">{dash(labels[count - 1])}</span>
      </div>
      <div className="legend">
        {series.map((item, index) => (
          <span key={item.name} className="lg">
            <i style={{ background: item.color ?? COLORS[index % COLORS.length] }} />
            {item.name}
          </span>
        ))}
      </div>
    </div>
  );
}

/**
 * 横向条形列表。display 原样展示（例如漏斗的 "87%" 由服务端算好，
 * 前端不重算）；这里的宽度百分比只用于排版。
 */
export function BarRows({ items }: { items: { label: string; value: number; display?: string }[] }) {
  if (items.length === 0) return <Empty />;
  const max = Math.max(...items.map((item) => item.value), 0) || 1;
  return (
    <div className="bars">
      {items.map((item) => (
        <div className="bar" key={item.label}>
          <span className="bar-l" title={item.label}>
            {item.label}
          </span>
          <span className="bar-t">
            {/* ponytail: 纯排版宽度，非业务比率；不涉及金额精度 */}
            <i style={{ width: `${Math.max((item.value / max) * 100, 2)}%` }} />
          </span>
          <span className="bar-v">{item.display ?? item.value}</span>
        </div>
      ))}
    </div>
  );
}
