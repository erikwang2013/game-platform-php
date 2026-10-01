/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
// 纯逻辑，放 lib/ 才能被 `node --test`（--experimental-strip-types，**不认 .tsx**）直接加载 ——
// 形状判定/累计判定/图标回落/列序这四条都是分支，只有搬出 .tsx 才测得到行为而不是读源码猜。
// 这里只 `import type`，类型擦除后运行时不解析 DataTable.tsx。
import type { Column, Row } from '../components/DataTable.tsx';

/**
 * 仪表盘（`GET /admin/v1/dashboard`）的响应形状，照 `app/admin/v1/controller/DashboardController.php` 抄。
 * - `stats[]` = `{label, value, icon, color, trend?}` —— `label` 后端已经 `trans()` 过了
 * - `trends` = `{dates[], series[{name, data[], color}]}`，`name` 同样已译
 * - `distribution` = `{<维度>: [{name, value}]}`（当前只有 `user_status`），`name` 已译
 * - `recent_logs[]` = 操作日志行 + `user_name`（`user` / `user_id` 后端已 unset）
 */
export type StatItem = { label?: unknown; value?: unknown; icon?: unknown; color?: unknown; trend?: unknown };
export type TrendLine = { name?: unknown; data?: unknown; color?: unknown };
export type Dashboard = {
  stats?: StatItem[];
  trends?: { dates?: unknown[]; series?: TrendLine[] };
  distribution?: Record<string, { name?: unknown; value?: unknown }[]>;
  recent_logs?: Row[];
};

/** 认识这个形状的四个容器键（页面标题/图例也照它们取词条）。 */
export const DASHBOARD_KEYS = ['stats', 'trends', 'distribution', 'recent_logs'] as const;

/**
 * 认不认识这个响应 —— 判定为假时页面**退回通用兜底**（宁可摊平，不白屏）。
 *
 * 用 `some` 不用 `every`：四个块里任何一个在，就说明后端确实在回这个对象，缺的块按空渲染；
 * 反过来，`{ok:true}`（e2e 那条罐头）或任何退化体一个键都不带 ⇒ 交给 `AutoView`。
 * `typeof` 那半句不是防御性冗余：`in` 运算符对字符串/数字**直接抛 TypeError**，
 * 端点哪天回个裸标量就是整页白屏——而「不白屏」正是这条兜底存在的全部理由。
 */
export function hasDashboardShape(data: unknown): data is Dashboard {
  return typeof data === 'object' && data !== null && DASHBOARD_KEYS.some((key) => key in data);
}

/**
 * 非递减 ⇒ 累计量。**判据取自数据本身、不看 series 名字**：名字是后端 `trans()` 译过的，
 * 认名字等于把 13 种译文当契约。累计量的轴不能含 0（47000→48200 在零基轴上是一条贴顶的
 * 直线，等于没画）；有起有落的计数仍走含 0 的轴（不把 300→400 画成暴涨）。
 * 空数组算累计（`every` 的空真值）：没有点就没有斜率，两条轴都画不出东西，不必特判。
 */
export function isCumulative(values: number[]): boolean {
  return values.every((value, index) => index === 0 || value >= values[index - 1]);
}

/**
 * 后端图标名 → 字形。本树不引图标库（侧栏那套就是几何字形，见 `Shell.tsx` 的 NAV），
 * 所以这里也是同一族的单色字形：`◉` 与侧栏「用户」同款，保持同一套视觉语汇。
 * 名字认不出就落 `FALLBACK_ICON` —— 后端加一个新图标时是「换个形状」，不是「空一块」。
 */
export const ICONS: Record<string, string> = {
  people: '◉',
  person_add: '⊕',
  bolt: '◆',
  description: '▤',
};
export const FALLBACK_ICON = '◇';

export function iconFor(name: unknown): string {
  return ICONS[String(name ?? '')] ?? FALLBACK_ICON;
}

/**
 * 最近日志的列。**不用 `columnsFrom` 的裸顺序 + 上限 8 列**：那个口径按响应键序切，
 * 而键序是 `id, action, method, path, ip, source, input, created_at, user_name` ——
 * 第 8 列正好切在 `user_name` 上，**「谁干的」被截掉**，一张审计表没了他就没了意义。
 * 故显式给列序，并把 `input`（一整段 JSON 请求参数，`cell` 会截成 48 字）挪出摘要表。
 */
export const LOG_COLUMNS: Column[] = [
  { key: 'id', label: 'f.id' },
  { key: 'user_name', label: 'f.user_name' },
  { key: 'action', label: 'f.action' },
  { key: 'method', label: 'f.method' },
  { key: 'path', label: 'f.path' },
  { key: 'ip', label: 'f.ip' },
  { key: 'source', label: 'f.source' },
  { key: 'created_at', label: 'f.created_at' },
];
