/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { dash } from '../lib/format';
import { t } from '../i18n/index.ts';
import { Empty } from './ui';

// 分类色是**回落**，不是统一入口：`series[].color` 有值就用它（系列色的真源在后端 ——
// /dashboard 的两条线由控制器发 #1677FF / #52C41A），只有后端不给时才轮到这里的令牌
// （/risk/hit-trend 就是这样：控制器里没有 color 字段，alignTrend 造出来的线也没有）。
// 令牌而不是写死的十六进制：暗色下深青绿在暗底上几乎不可见，折线等于消失 —— 令牌随
// prefers-color-scheme 整体提亮，写死的常量做不到（也不必为此引一个 JS 主题状态机）。
const COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)'];

export type Series = { name: string; values: number[]; color?: string };

/**
 * 手搓 SVG 折线图：无依赖，够用。数值只做几何映射，不参与业务计算。
 *
 * `zeroBased`（缺省 true）决定纵轴要不要含 0：
 * - **计数/比率类必须含**（缺省口径，也是风控那三条曲线的口径）—— 截断的轴会把 300→400 画成暴涨；
 * - **累计量不含**：累计用户 47000→48200 在 `[0, 48200]` 上是一条贴着顶的直线，等于没画。
 * 两种口径都是纯展示，不改变任何数值。
 */
export function LineChart({
  labels,
  series,
  height = 200,
  zeroBased = true,
}: {
  labels: string[];
  series: Series[];
  height?: number;
  zeroBased?: boolean;
}) {
  const points = series.flatMap((item) => item.values).filter((value) => Number.isFinite(value));
  if (labels.length === 0 || points.length === 0) return <Empty text={t('chart.no_data')} />;

  const W = 640;
  const H = height;
  const PAD = 24;
  // points 非空（上面已 return），故 -Infinity/+Infinity 只是"这一侧取数据极值"的写法
  const max = Math.max(...points, zeroBased ? 0 : -Infinity);
  const min = Math.min(...points, zeroBased ? 0 : Infinity);
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
            // viewBox 固定 640 宽、容器实测 1100+ ⇒ 等比放大约 1.7 倍，2px 线宽会画成 3.4px
            // 且各页放大倍数不同（线粗细不一致）。锁定成"屏幕像素"后 .line 的 stroke-width 才是真值。
            vectorEffect="non-scaling-stroke"
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
