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
 * 空块（`null` / 缺省）算「有」：页面各处都写成 `data.x ?? []`，空块按空渲染。
 * 这是**契约的一部分**（`dashboard.test.ts` 逐个键钉着 `{ [key]: null }` 必须判定为真）。
 */
const absent = (value: unknown): boolean => value === null || value === undefined;

/** 行数组：渲染期逐行取字段，非数组（`.map` 直接抛）与含 `null` 行（取 `.name` 抛）都不算。 */
const rowList = (value: unknown): boolean =>
  absent(value) || (Array.isArray(value) && value.every((row) => typeof row === 'object' && row !== null));

/** 非数组对象（`trends.{dates,series}`、`distribution.{维度: 行[]}`）。 */
const bag = (value: unknown): boolean => absent(value) || (typeof value === 'object' && !Array.isArray(value));

/**
 * 标签数组（`trends.dates`）：**只判「是不是数组」**，元素类型不逐个卡。
 *
 * 后端的 dates 是 `date('Y-m-d', …)` 的**字符串**数组（`DashboardController::getTrends`），
 * 渲染方是 `(data?.dates ?? []).map((date) => String(date))` —— `String()` 对任何元素都不抛，
 * 唯一的崩法是它不是数组（`{}` 上 `.map` 直接抛）。
 *
 * 这条**踩过**：一开始跟着 `rowList` 要求「元素得是非 null 对象」，于是**真实响应被判成
 * 「不认识」**，整页退到通用兜底 —— 不崩、不报错、控制台干净，只是真渲染器再也不出现
 * （右上角 .subblock/.delta 一个都没有）。形状判定收得比真实契约紧，坏法比崩更安静。
 */
const labelList = (value: unknown): boolean => absent(value) || Array.isArray(value);

/** 对象上的取字段：**空块与裸标量都回 undefined**。（`bag` 把空块算作「可以」，这里不能跟着它走，
 *  否则 `field(null, 'dates')` 就是一次空指针 —— 本文件存在的理由正是这类崩溃。） */
const field = (value: unknown, name: string): unknown =>
  absent(value) || typeof value !== 'object' ? undefined : (value as Record<string, unknown>)[name];

/**
 * 每个容器键**自己的**形状判据。
 *
 * 为什么不能只判「键在不在」：`{stats:{}}` 这种「键在、类型错」的退化体会判定为真，
 * 于是 `Stats` 拿着一个对象去 `.map` ⇒ **整站白屏**（导航、顶栏全没，控制台一条
 * `items.map is not a function`）。真机实测复现过一次，形状其余部分当时一个字节都不校验。
 * 反过来也不能一律收紧：`{stats:null}` 必须是真（上面那条契约），故空块先放行。
 */
const BLOCK_OK: Record<(typeof DASHBOARD_KEYS)[number], (value: unknown) => boolean> = {
  stats: rowList,
  trends: (value) => bag(value) && labelList(field(value, 'dates')) && rowList(field(value, 'series')),
  distribution: (value) => bag(value) && (absent(value) || Object.values(value as object).every(rowList)),
  // 单独放宽：页面用 `asRows` 收它（数组、`{list:[…]}` 都认），非数组时回 `[]`，这里怎么都不炸
  recent_logs: (value) => absent(value) || typeof value === 'object',
};

/**
 * 认不认识这个响应 —— 判定为假时页面**退回通用兜底**（宁可摊平，不白屏）。
 *
 * 四个块里任何一个在、且**形状对**，就说明后端确实在回这个对象，缺的块按空渲染；
 * 反过来 `{ok:true}`（e2e 那条罐头）或任何退化体都交给 `AutoView`。
 * `typeof` 那半句不是防御性冗余：`in` 运算符对字符串/数字**直接抛 TypeError**，
 * 端点哪天回个裸标量就是整页白屏——而「不白屏」正是这条兜底存在的全部理由。
 */
export function hasDashboardShape(data: unknown): data is Dashboard {
  if (typeof data !== 'object' || data === null) return false;
  const record = data as Record<string, unknown>;
  const present = DASHBOARD_KEYS.filter((key) => key in record);
  return present.length > 0 && present.every((key) => BLOCK_OK[key](record[key]));
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
