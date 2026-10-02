/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 状态列的**后端编码 → 当前语言文案**（列表里的 `status` / `payout_status`）。
 *
 * 问题：这两列一直把库里那个编码原样摆给运营看（`1` / `0` / `pending` / `rejected`…），
 * 运营得自己背下来「1=启用」。而且**不是一个枚举**：游戏是 0/1 上下架、区服是 0..3 四态、
 * 工单与提现是字符串枚举，各自的 0/1 含义还不一样（C 端用户 1=正常、群组 1=正常但要跟 0=解散 一组看）。
 *
 * 修法与同仓 angular 树同形（`core/util.ts` 的 `enabledLabel` + `status_label` 影子列）：
 * **映射结果落到兄弟列**（`<列名>_label`），**原值一个字节都不动**。原值不是给人看的显示值，
 * 它同时是**行为输入**：行内动作按 `status === 'pending'` 决定摆不摆按钮、启停按 0/1 发请求、
 * 批量勾选与二次确认文案也读它、导出载荷同样读它 —— 就地改写 = 顺手改了行为，不只是改了显示。
 *
 * 三个刻意选择：
 * 1. **按端点选枚举**（`statusEnumsFor`），不给每个调用点加参数：二十来个列表都走同一批底座
 *    （RowBrowser / PlatformUsers），把映射挂在端点上，新增列表页时只有一处要对。
 * 2. **认不出的值原样透出**（走 `cellText`，空值仍是占位符 `—`）：后端加了新状态而这张表没跟上，
 *    宁可让界面露出 `pending_refund` 这样的原键，也不替后端编一个不存在的状态名 ——
 *    把认不出的值说成「已完成」比露编码更糟，运营会照着假状态做决定。
 * 3. **译文在渲染期现取**（影子列的 `render` 里 `t()`），不是取数时算一次存进行里：本树换语言
 *    不重新取数（`useApi` 缓存着响应），存进数组里的译文会冻在换语言之前的那一刻。
 *
 * 枚举**值域**的出处逐条写在下面（DDL 注释 / 控制器的 `in:` 校验），改后端值域时两处一起改。
 * 表单侧的同一批枚举另有 `f.n_*` 选项键（`pages/modules.ts` 的 options）—— 那族文案带着编码
 * （`'1 Normal'`），是给下拉框挑选项用的；列表里要的是干净的状态名，故不共用。
 */
import { t, type MessageKey } from '../i18n/index.ts';
import { cellText } from './pdf-table.ts';
// 只 `import type`：类型擦除后运行时不解析 DataTable.tsx（`node --test` 收不了 .tsx，见 lib/columns.ts）
import type { Column, Row } from '../components/DataTable.tsx';

/** 一个枚举：后端编码 → 文案键。键一律**字符串** —— TINYINT 经 JSON 回来可能是 0 / '0' 两种写法。 */
export type StatusEnum = Record<string, MessageKey>;

/**
 * 全部状态枚举。**值域出处**（2026-10-01 逐条核过 DDL 与写入方）：
 *
 * - `enabled`   `0=禁用 1=启用`（后台账号/角色/支付方式/风控规则/分类/券/国家）
 * - `listing`   `game_game.status`：`0=下架 1=上架`（**不是启停**，前端字段标签也是 `f.listed`）
 * - `publish`   `game_announcement.status`：`0=草稿 1=已发布`（**不是启停**）
 * - `user_state` `game_user.status`：`0=禁用 1=启用`，但平台用户的语义是 `1=正常 0=封禁`
 *                （`platform-users.tsx` 的字段 hint `f.on_active_1_off_banned` 与 `PlatformUserController.php:179` 的 in_array 是准）
 * - `group_state` `game_group.status`：`1=正常 0=解散`（**不是启停**，0 是「已解散」这个存续状态）
 * - `server`    `game_game_server.status`：`0=维护 1=正常 2=火爆 3=新服`；校验 `GameServerController.php:112` 的 `in:0,1,2,3`
 * - `activity`  `game_activity.status`：`0=禁用 1=启用 2=已结束`
 * - `cluster`   `game_risk_cluster.status`：`1=观察中 2=已处置 0=误判`（⚠ 顺序是 1/2/0，0 不是「关闭」）
 * - `review`    `game_user_identity.status`：`pending/approved/rejected`
 * - `ticket`    `game_ticket.status` 注释只写了 `open/closed`，**两个字面值漏在注释外**：实际 4 值
 *                （`service/app/api/v1/controller/TicketController.php:165` 写 `waiting`、`admin/.../TicketController.php:97` 写 `replied`）
 * - `withdraw`  `game_withdraw_order.status` 注释列了 4 值，**漏了 `processing`**：`WithdrawController.php:291` 提交打款时写它
 * - `payout`    `game_withdraw_order.payout_status`：`''(空)/processing/success/failed`
 *                （空值走 `cellText` 的 `—`，与今天一致）
 * - `anticheat` `game_anticheat_event.status`：`open/confirmed/whitelisted/closed`
 *                （校验 `AntiCheatController.php:76` 的 in_array）
 */
export const STATUS_ENUMS: Record<string, StatusEnum> = {
  enabled: { '1': 'st.enabled', '0': 'st.disabled' },
  listing: { '1': 'st.listed', '0': 'st.unlisted' },
  publish: { '1': 'st.published', '0': 'st.draft' },
  user_state: { '1': 'st.active', '0': 'st.banned' },
  group_state: { '1': 'st.group_active', '0': 'st.group_dissolved' },
  server: {
    '0': 'st.server_maintenance',
    '1': 'st.server_normal',
    '2': 'st.server_hot',
    '3': 'st.server_new',
  },
  activity: { '0': 'st.disabled', '1': 'st.enabled', '2': 'st.ended' },
  cluster: {
    '1': 'st.cluster_watching',
    '2': 'st.cluster_actioned',
    '0': 'st.cluster_false_positive',
  },
  review: { pending: 'st.pending', approved: 'st.approved', rejected: 'st.rejected' },
  ticket: {
    open: 'st.ticket_open',
    waiting: 'st.ticket_waiting',
    replied: 'st.ticket_replied',
    closed: 'st.ticket_closed',
  },
  withdraw: {
    pending: 'st.pending',
    approved: 'st.approved',
    processing: 'st.processing',
    rejected: 'st.rejected',
    completed: 'st.completed',
  },
  payout: { processing: 'st.processing', success: 'st.payout_success', failed: 'st.payout_failed' },
  anticheat: {
    open: 'st.ac_open',
    confirmed: 'st.ac_confirmed',
    whitelisted: 'st.ac_whitelisted',
    closed: 'st.ac_closed',
  },
};

/**
 * 端点 → 该列表上要摊平的状态列（`列名 → 枚举 id`）。
 *
 * 键是**列表端点**（不是页面）：一个页面挂好几个端点，各是各的枚举。没登记的端点返回 null，
 * 影子列一个都不加 —— 宁可不做，也不要拿别的模块的枚举去套（「认不出的别编」的同一条）。
 */
const ENDPOINT_ENUMS: Record<string, Record<string, string>> = {
  '/admin/v1/game/list': { status: 'listing' },
  '/admin/v1/game/server/list': { status: 'server' },
  '/admin/v1/game/category/list': { status: 'enabled' },
  '/admin/v1/leaderboard/list': { status: 'enabled' },
  '/admin/v1/achievement/list': { status: 'enabled' },
  '/admin/v1/activities/list': { status: 'activity' },
  '/admin/v1/announcement/list': { status: 'publish' },
  '/admin/v1/platform/user/list': { status: 'user_state' },
  '/admin/v1/identity/list': { status: 'review' },
  '/admin/v1/ticket/list': { status: 'ticket' },
  // Route::resource 的 index 挂在集合路径本身（后台账号 / 角色），不是 /list
  '/admin/v1/user': { status: 'enabled' },
  '/admin/v1/role': { status: 'enabled' },
  '/admin/v1/payment/method/list': { status: 'enabled' },
  '/admin/v1/coupon/list': { status: 'enabled' },
  '/admin/v1/risk/rule/list': { status: 'enabled' },
  '/admin/v1/anticheat/events': { status: 'anticheat' },
  '/admin/v1/risk/clusters': { status: 'cluster' },
  '/admin/v1/cdn/provider/list': { status: 'enabled' },
  '/admin/v1/country/config/list': { status: 'enabled' },
  '/admin/v1/groups': { status: 'group_state' },
  // 提现订单有两列编码：审核状态与打款状态（后者空值是「还没发起打款」）
  '/admin/v1/withdraw/orders': { status: 'withdraw', payout_status: 'payout' },
};

/**
 * 该端点的状态枚举。返回 null = 这个端点没有要摊平的状态列。
 *
 * `search` 单列一条：它一个 URL 两种目标（`type=game` 上下架 / `type=user` 正常-封禁），
 * 端点路径分不出来，得看查询参数。
 */
export function statusEnumsFor(path: string, query?: Record<string, unknown>): Record<string, string> | null {
  if (path === '/admin/v1/search') {
    return { status: query?.['type'] === 'user' ? 'user_state' : 'listing' };
  }
  return ENDPOINT_ENUMS[path] ?? null;
}

/**
 * 一个状态值 → 当前语言的文案。**认不出的值与空值都回落 `cellText`**（空 → `—`，
 * 认不出 → 原样那个串），即与今天屏幕上看到的一致，只是认识的那些换成了人话。
 */
export function statusText(enumId: string, value: unknown): string {
  const key = STATUS_ENUMS[enumId]?.[String(value ?? '')];
  return key === undefined ? cellText(value) : t(key);
}

/**
 * 把列里的状态列换成**影子列**：列名改成 `<原列名>_label`（与 angular 树的命名接得上），
 * 渲染走 `statusText`，**原列名与行里的原值都不动**。
 *
 * 列名换成 `_label` 而不是就地加 `render`，是为了让「这一格显示的不是字段原值」在列本身、在 PDF
 * 载荷、在将来任何按列名分派的代码里都**看得出来**；相应的 PDF 侧按 render 的字符串取（见
 * lib/pdf-table.ts），屏幕上与导出的读数才是同一个。
 *
 * 值列不受影响：这里只 `map` 已经算好的列，不碰 `columnsFrom` 的取列与排序（列的上限是 8，
 * 多塞一个键会挤掉尾列 —— 社群那组就吃过这个亏）。
 */
export function withStatusLabels(columns: Column[], enums: Record<string, string> | null): Column[] {
  if (enums === null) return columns;
  return columns.map((column) => {
    const enumId = enums[column.key];
    if (enumId === undefined) return column;
    return {
      ...column,
      key: `${column.key}_label`,
      render: (row: Row) => statusText(enumId, row[column.key]),
    };
  });
}
