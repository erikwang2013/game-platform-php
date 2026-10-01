/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 仪表盘（`GET /admin/v1/dashboard`）—— `{stats, trends, distribution, recent_logs}` 四块。
 *
 * 为什么单开一个渲染器，而不是继续走 `Section` → `AutoView`：通用兜底认不出这个形状，
 * 会把它摊成一张调试表 —— `stats` 的 `icon`（`people`）与 `color`（`#1677FF`）当文本显示，
 * `trends` / `distribution` 只剩一句「另有 N 个嵌套字段未展开」。后端发 icon + color + trend
 * 就是让前端画卡片的，而这一屏是登录后的落地页，摊平了等于没渲染。
 * `AutoView` 的通用兜底**一个字节都没动**（几十个页面共用它）。
 *
 * 四条分支（形状回落 / 累计判定 / 图标回落 / 列序）的**纯逻辑**在 `lib/dashboard.ts`：
 * 本树没有 DOM 底座，`.tsx` 进不了 `node --test` 的 import 图 ⇒ 留在文件里就只能读源码猜，
 * 搬出去才测得到行为。这里只剩取数与排版。形状真值照 `DashboardController.php` 抄。
 */
import { asRows, AutoView } from '../components/AutoView';
import { BarRows, LineChart, type Series } from '../components/Chart';
import { DataTable } from '../components/DataTable';
import { Card, Empty, ErrorNote, Loading, Stat } from '../components/ui';
import { fieldLabelKey, t, useI18n, type MessageKey } from '../i18n/index.ts';
import { hasDashboardShape, iconFor, isCumulative, LOG_COLUMNS, type Dashboard, type StatItem } from '../lib/dashboard.ts';
import { dash } from '../lib/format';
import { useApi } from '../lib/hooks';

/** 与 `AutoView` 的 `labelOf` 同一条兜底链：同名 `f.<键>` 有译文就用，没有原样回键名。 */
const fieldLabel = (key: string): string => t(fieldLabelKey(key) ?? (key as MessageKey));

/** 统计块：后端给 icon/color 就是给卡片用的 —— 图标吃 `color` 当强调色，不铺大面积底色。 */
function Stats({ items }: { items: StatItem[] }) {
  if (items.length === 0) return <Empty />;
  return (
    <div className="grid grid-4">
      {items.map((item, index) => {
        const trend = item.trend === null || item.trend === undefined ? NaN : Number(item.trend);
        const color = typeof item.color === 'string' && item.color !== '' ? item.color : undefined;
        return (
          <Stat
            key={index}
            label={dash(item.label)}
            value={dash(item.value)}
            icon={iconFor(item.icon)}
            color={color}
            hint={
              Number.isFinite(trend) ? (
                // 方向用字形 + 语义色两重编码；`%` 是符号不是词，13 种语言里都一样写
                <span className={trend < 0 ? 'delta down' : 'delta up'}>
                  {trend < 0 ? '▼' : '▲'} {Math.abs(trend)}%
                </span>
              ) : undefined
            }
          />
        );
      })}
    </div>
  );
}

/**
 * 趋势：`dates` 当横轴、`series[].data` 当纵轴，喂 `LineChart`（无依赖手搓 SVG）。
 *
 * **一条线一张图**，不把 series 堆进同一张：这一屏的两条线差两个数量级 —— 累计用户 ~48000、
 * 每日日志 ~300。共用一根轴时，无论零基还是取数据极值，总有一条被压成贴着边的直线。
 * 各画各的，各自吃自己的量程与自己的轴口径（`zeroBased={!cumulative}`，判据见 isCumulative）。
 * （风控那三条曲线量级相当，继续共用一张，见 risk-stats.tsx）
 */
function Trends({ data }: { data: Dashboard['trends'] }) {
  const labels = (data?.dates ?? []).map((date) => String(date));
  const series: Series[] = (data?.series ?? []).map((line) => ({
    name: String(line.name ?? ''),
    values: Array.isArray(line.data) ? line.data.map(Number) : [],
    color: typeof line.color === 'string' && line.color !== '' ? line.color : undefined,
  }));
  if (series.length === 0) return <Empty text={t('chart.no_data')} />;
  return (
    <>
      {series.map((line) => {
        // 累计判据在 lib/dashboard.ts 的 isCumulative（纯函数，行为有用例钉着）
        const cumulative = isCumulative(line.values);
        return (
          // 高度压到 120：viewBox 是 640 宽而卡内容宽度 1100+，等比放大 1.8 倍，
          // 默认 200 会画成 ~360px 高 —— 两条线就是 700px 的空白
          <div key={line.name} className="subblock">
            <LineChart labels={labels} series={[line]} height={120} zeroBased={!cumulative} />
          </div>
        );
      })}
    </>
  );
}

/**
 * 分布：`{维度: [{name, value}]}`。当前维度只有 `user_status`，但不写死键名 ——
 * 后端往 `getDistribution()` 里加第二个维度时这里自动多一组，不用改前端。
 */
function Distribution({ data }: { data: Dashboard['distribution'] }) {
  const groups = Object.entries(data ?? {});
  if (groups.length === 0) return <Empty />;
  return (
    <>
      {groups.map(([key, rows]) => (
        <div key={key} className="subblock">
          <p className="muted">{fieldLabel(key)}</p>
          <BarRows
            items={(rows ?? []).map((row) => ({
              label: String(row.name ?? ''),
              value: Number(row.value ?? 0),
            }))}
          />
        </div>
      ))}
    </>
  );
}

export function DashboardView() {
  // 本页自己订阅语言：切语言时页面子树要重绘（`label` 是后端译的，但四个卡标题不是）
  useI18n();
  const { data, loading, error, reload } = useApi<Dashboard>('/admin/v1/dashboard');

  if (loading) {
    return (
      <Card>
        <Loading />
      </Card>
    );
  }
  if (error) {
    return (
      <Card>
        <ErrorNote message={error} onRetry={reload} />
      </Card>
    );
  }
  // 形状不认识（真契约变了、或端点回的不是这个对象）⇒ 退回通用兜底：宁可摊平，不白屏
  const known = hasDashboardShape(data);
  if (!known) return <AutoView data={data} />;

  // 契约是数组；万一回的是 {list:[…]} 那种信封，也认（`asRows` 就是干这个的）
  const logs = Array.isArray(data.recent_logs) ? data.recent_logs : (asRows(data.recent_logs) ?? []);

  return (
    <div className="stack">
      <Card title={t('f.stats')}>
        <Stats items={data.stats ?? []} />
      </Card>
      <Card title={t('f.trends')}>
        <Trends data={data.trends} />
      </Card>
      <Card title={t('f.distribution')}>
        <Distribution data={data.distribution} />
      </Card>
      <Card title={t('f.recent_logs')}>
        <DataTable columns={LOG_COLUMNS} rows={logs} />
      </Card>
    </div>
  );
}
