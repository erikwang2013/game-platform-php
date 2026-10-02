/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 显示层格式化。只做呈现，绝不参与金额/比率运算 —— 所有金额与百分比均由
 * 服务端 bcmath 计算，前端原样透传字符串。
 */
import { currentCode } from '../i18n/index.ts';

/** 空值统一显示为 "—"。 */
export const dash = (value: unknown): string =>
  value === null || value === undefined || value === '' ? '—' : String(value);

/**
 * 千分位。仅对真正的 number 生效；字符串原样返回，
 * 因为 bcmath 金额串（如 "12345678901234567890.12"）转 number 会丢精度。
 *
 * 区域设置取 `currentCode()` 而**不是**写死 'zh-CN'：界面有 13 种语言，写死等于数字分组
 * 永远是中文口径（de 的 `1.234,5`、hi 的印度分组法都拿不到）。服务端金额串走不到这里。
 */
export const num = (value: unknown): string =>
  typeof value === 'number' && Number.isFinite(value) ? value.toLocaleString(currentCode()) : dash(value);

/**
 * 金额方向 → 着色类（`.delta.up` 绿 / `.delta.down` 红，见 index.css）。
 *
 * **只看字符串，不 `parseFloat`**：流水里的金额是 bcmath 精度串，转成 number 再比较就是
 * 让 float 参与金额判断（本仓铁律：金额运算禁止 float），而且 `"12345678901234567890.12"`
 * 这种串转 number 直接丢精度。
 *
 * 三态而不是二态：**零不着色**。0 既不是收入也不是支出，染成绿色是个假信号。
 * 判零用「串里有没有非 0 数字」而不是 `Number()` —— 后者又回到 float 那条路，
 * 且 `"0.00000000"`/`"-0.00000000"`/`"abc"` 三种形状都能正确落进「不着色」。
 */
export function amountClass(value: unknown): string {
  const text = String(value ?? '');
  if (!/[1-9]/.test(text)) return '';
  return text.startsWith('-') ? 'delta down' : 'delta up';
}

/** hashid / 主键候选键，一律按字符串透传，不做 parseInt。 */
export const ID_KEYS = ['game_id', 'user_id', 'order_id', 'event_id', 'id', 'hashid'];

/** 从行对象按候选键取第一个有值的字段。 */
export function pick(row: Record<string, unknown>, keys: string[]): unknown {
  for (const key of keys) {
    const value = row[key];
    if (value !== null && value !== undefined && value !== '') return value;
  }
  return undefined;
}

const TIME_KEY = /(_at|_time|time|date|created|updated|expire)/i;

export const isTimeKey = (key: string): boolean => TIME_KEY.test(key);

/** 时间戳/日期串统一展示；10 位按秒、13 位按毫秒。区域设置随界面语言（同 `num`）。 */
export function when(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'number' && Number.isFinite(value)) {
    // ponytail: 秒/毫秒启发式判定，够用；若后端统一为 ISO 串可删掉此分支
    const ms = value > 1e12 ? value : value * 1000;
    return new Date(ms).toLocaleString(currentCode());
  }
  return String(value);
}

/**
 * 服务端时区（小时偏移）。**这不是「显示时区偏好」，是一条回传契约。**
 *
 * 本仓 PHP 的 `date_default_timezone_get()` 是 `PRC`、MySQL session 是 `SYSTEM`，
 * 两树实测同在 +8；而 Eloquent 对带 datetime cast 的列序列化成 **UTC**
 * （库内 `2026-01-01 00:00:00` → 响应 `2025-12-31T16:00:00.000000Z`），
 * 不带 cast 的列却原样吐 +8 墙钟串 —— 同一屏两种形状（实测 19 个时间列 11 ISO / 8 原样）。
 *
 * ⚠ **改这个常量就是改全局**：`lib/api.ts` 的响应归一化吃它。
 * ⚠ **绝不能改成浏览器本地时区**：这些值是要**回传**的（编辑表单预填后原样提交，
 *    而 Eloquent/MySQL 都会丢掉 ISO 里的 `Z` 且不做时区换算），后端按 +8 墙钟再读一次
 *    ⇒ 换个时区的管理员会「显示正确、保存位移 8 小时」。
 * ponytail: 固定偏移，不上 IANA 时区库 —— 服务端就一个固定偏移；真要跨时区部署再换。
 */
const SERVER_TZ_OFFSET = 8;

/** 只认**整串就是一个 UTC 时间戳**的形状；别的一律不碰（所以 `2026-01-02` 日期串不动）。 */
const ISO_UTC = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/;

/**
 * UTC ISO 串 → 服务端墙钟串（`Y-m-d H:i:s`）。与后端**不带 cast** 的列同形，
 * 于是表格 / 详情面板 / PDF / 编辑框预填 / 筛选拿到的是同一种值。
 *
 * 非 ISO 输入（含本来就是墙钟的 `2026-01-01 00:00:00`）**原样返回**，不做二次偏移 ——
 * 那 8 列不能被误伤。
 */
export function toWallClock(value: string): string {
  if (!ISO_UTC.test(value)) return value;
  const ms = Date.parse(value);
  if (Number.isNaN(ms)) return value; // 形似但非法（2026-13-45T…）不吞，原样交出去
  // 加偏移后用 **getUTC\*** 取值：结果与宿主时区无关。用 getHours() 会让同一个值
  // 随浏览器时区漂 —— 那正是上面 ⚠ 说的那个 bug 的入口。
  const d = new Date(ms + SERVER_TZ_OFFSET * 3600_000);
  const p = (n: number): string => String(n).padStart(2, '0');
  return `${d.getUTCFullYear()}-${p(d.getUTCMonth() + 1)}-${p(d.getUTCDate())}` +
    ` ${p(d.getUTCHours())}:${p(d.getUTCMinutes())}:${p(d.getUTCSeconds())}`;
}
