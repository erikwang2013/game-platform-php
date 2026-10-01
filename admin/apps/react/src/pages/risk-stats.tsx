/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 风控统计（图表页）：`GET /risk/hit-trend`、`/risk/action-distribution`、`/risk/rule-performance`。
 *
 * 三个端点此前**全树零引用**，而它们的形状刚好是「做图表」要的那种：
 * - `hit-trend` → 逐日 × 规则类型的命中数 → 折线（`components/Chart.tsx` 的 `LineChart`）
 * - `action-distribution` → 各处置动作的条数与占比 → 横条（`BarRows`）
 * - `rule-performance` → 每条规则的命中/拦截/拦截率/误报率 → 表格（列序显式给，见 `RulePerformance`）
 *
 * 用**已有的** `Chart.tsx`：它此前**零消费者**（只有定义、没有调用点），而它的 CSS
 * （`index.css` 的 `.chart` / `.bars` / `.legend` 一族）一直在，是名副其实的「画好了没接线」。
 *
 * 分工与 `pages/reports.tsx` 一致：三块各自取数、三态渲染；日期范围交给服务端默认
 * （近 7 天），这一屏不摆日期控件 —— 端点也收 `from/to/rule_type`，但没有第二个消费者要它，
 * 先按默认口径出图，需要加筛选时这里就是唯一的落点。
 */
import { asRows } from '../components/AutoView';
import { columnsFrom, labelsFor } from '../lib/columns.ts';
import { BarRows, LineChart } from '../components/Chart';
import { DataTable, type Row } from '../components/DataTable';
import { Card, ErrorNote, Loading } from '../components/ui';
import { t, useI18n } from '../i18n/index.ts';
import { useApi } from '../lib/hooks';
import { alignTrend, type TrendPoint } from '../lib/trend.ts';

/** 命中趋势：`series` 是「规则类型 → 该类型逐日命中」的映射（不是数组，见 lib/trend.ts） */
type Trend = { from?: string; to?: string; rule_type?: string; series?: Record<string, TrendPoint[]> };
/** 处置分布：`ratio` 是服务端按 BcMath::percent 算好的百分数（前端不重算） */
type Distribution = { from?: string; to?: string; total?: number; items?: { action?: unknown; count?: unknown; ratio?: unknown }[] };

/** 逐日折线卡：取数 + 对齐（`alignTrend`）+ 空态，交给 LineChart。 */
function HitTrend() {
  const { data, loading, error, reload } = useApi<Trend>('/admin/v1/risk/hit-trend');
  const { labels, lines } = alignTrend(data?.series ?? {});

  return (
    <Card title={t('risk.stats.trend')} sub={data ? `${data.from ?? ''} ~ ${data.to ?? ''}` : undefined}>
      {loading ? <Loading /> : error ? <ErrorNote message={error} onRetry={reload} /> : <LineChart labels={labels} series={lines} />}
    </Card>
  );
}

/**
 * 处置分布卡：按条数降序（服务端按 group by 回来，顺序不稳定）。
 * 占比原样用服务端算好的 `ratio`，与漏斗同口径 —— 前端再除一次就是第二个真值源。
 */
function ActionDistribution() {
  const { data, loading, error, reload } = useApi<Distribution>('/admin/v1/risk/action-distribution');
  const items = [...(data?.items ?? [])]
    .sort((a, b) => Number(b.count ?? 0) - Number(a.count ?? 0))
    .map((item) => ({
      label: String(item.action ?? ''),
      value: Number(item.count ?? 0),
      display: `${item.count ?? 0} · ${item.ratio ?? 0}%`,
    }));

  return (
    <Card
      title={t('risk.stats.actions')}
      sub={data ? t('risk.stats.total', { count: data.total ?? 0 }) : undefined}
    >
      {loading ? <Loading /> : error ? <ErrorNote message={error} onRetry={reload} /> : <BarRows items={items} />}
    </Card>
  );
}

/** 规则效果：11 个字段（命中/拦截/拦截率/误报率…）用图表达会丢信息，交给表格。 */
type RulePerf = { from?: string; to?: string; items?: Row[] };

/**
 * 规则效果表。**不用 `Section`**：`AutoView` 走 `columnsFrom(rows)` 的裸顺序 + 上限 8 列，
 * 而服务端的键序是 `id,name,type,action,priority,status,hits,blocked,block_rate,…` ——
 * 前 8 个正好切在 `blocked` 上，**两个比率列（拦截率 / 误报率）全被截掉**。
 * 这张表叫「规则效果」，被截掉的恰好是结论本身。
 * 故这里显式给 `preferred`：8 列全给业务字段，id/priority/status 让位。
 */
function RulePerformance() {
  const { data, loading, error, reload } = useApi<RulePerf>('/admin/v1/risk/rule-performance');
  const rows = asRows(data) ?? [];
  const columns = columnsFrom(
    rows,
    ['name', 'type', 'action', 'hits', 'blocked', 'block_rate', 'manual_review', 'manual_review_rate'],
    undefined,
    undefined,
    labelsFor(rows),
  );

  return (
    <Card title={t('risk.stats.rules')} sub={data ? `${data.from ?? ''} ~ ${data.to ?? ''}` : undefined}>
      <DataTable columns={columns} rows={rows} loading={loading} error={error} onRetry={reload} />
    </Card>
  );
}

export function RiskStats() {
  useI18n();
  return (
    <>
      <HitTrend />
      <ActionDistribution />
      <RulePerformance />
    </>
  );
}
