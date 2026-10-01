/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useState } from 'react';
import { asRows } from '../components/AutoView';
import { RowBrowser, type CrudConfig } from '../components/RowBrowser';
import { Section } from '../components/Section';
import { Card, ErrorNote, Loading, PageHead, Tabs } from '../components/ui';
import { t, useI18n, type MessageKey } from '../i18n/index.ts';
import { pick } from '../lib/format';
import { useApi } from '../lib/hooks';
// 「我的账号」单独一个文件：只读信息 + 改资料/改密码两组表单（见 account.tsx 的说明）
import { AccountCard } from './account';
import { DashboardView } from './dashboard';
import { CommunityGroups, ShareStats } from './community';
import { WithdrawLimits, WithdrawOrders, WithdrawSwitch } from './funds';
// 平台用户（C 端玩家）单独一个文件：那一屏有页面级导出 + 行内注销回读，本文件只负责挂载
import { PlatformUsers } from './platform-users';
import { ReportPanel } from './reports';
import { RiskClusters, RiskDevices, RiskIps } from './risk';
import { RiskStats } from './risk-stats';
// 管理员（后台账号）单独一个文件：本文件只负责挂载，配置在 adminUsers.ts
import { ADMIN_USER_CRUD } from './adminUsers';
import {
  ACHIEVEMENT_CRUD,
  ACTIVITY_CRUD,
  ANNOUNCEMENT_CRUD,
  ANTICHEAT_CRUD,
  CATEGORY_CRUD,
  CDN_CRUD,
  CONFIG_CRUD,
  COUNTRY_CRUD,
  COUPON_CRUD,
  GAME_CRUD,
  IDENTITY_CRUD,
  LEADERBOARD_CRUD,
  PAYMENT_CRUD,
  PERMISSION_CRUD,
  RISK_EVENT_CRUD,
  RISK_RULE_CRUD,
  RISK_USER_CRUD,
  ROLE_CRUD,
  TICKET_CRUD,
  VIP_CRUD,
  serverCrud,
} from './modules';

/**
 * 一个标签页 = 一个后端端点。list=true 走 RowBrowser（列表 + 首选列），其余交给 Section/AutoView。
 * platformUsers=true 的那一个换成 PlatformUsers —— 它要带行内动作，只读的 RowBrowser 撑不住。
 * crud 给了就长出「新建 / 编辑 / 删除 / 启用·停用」四类动作（字段描述见文件下半部分）；
 * 动作型模块（身份审核、工单）只给 actions —— 后端没有增改删端点，就一个按钮都不多长。
 */
type Group = {
  label: MessageKey;
  path?: string;
  list?: boolean;
  preferred?: string[];
  platformUsers?: boolean;
  /** 区服列表必填 game_id ⇒ 这个标签页先选游戏再渲染列表（见 GameServers） */
  gameServers?: boolean;
  /** 资金页的三块非行列表页面（全局开关 / 阶梯限额 / 提现订单的批量审核，见 pages/funds.tsx） */
  withdrawSwitch?: boolean;
  withdrawLimits?: boolean;
  withdrawOrders?: boolean;
  /** 风控页的三块（IP 的行没有 id 与原文 IP、团伙检测没有行上下文，见 pages/risk.tsx） */
  riskDevices?: boolean;
  riskIps?: boolean;
  riskClusters?: boolean;
  /** 风控统计页（三条图表端点一次取齐，见 pages/risk-stats.tsx）；path 只是页签身份 */
  riskStats?: boolean;
  /** 仪表盘（`{stats, trends, distribution, recent_logs}` 四块，见 pages/dashboard.tsx） */
  dashboard?: boolean;
  /** 树形列表的 children 键（权限树：子节点也要成为可操作的行，缩进 + 展开箭头） */
  tree?: string;
  /**
   * 行点击详情的路径前缀（`GET {detailBase}/{hashid}`），缺省不带行点击。
   * 只在**有单条详情端点**时给：给了而没有那条路由，点一下就是 404 弹框
   * （风控用户因此走 views 的时间线视图，见 modules.ts）。
   */
  detailBase?: string;
  /** 报表面板（汇总 + 逐日 + 日期范围 + 导出，见 pages/reports.tsx）：三个端点共用一对日期参数 */
  report?: boolean;
  /** 社群页的两块（群组带筛选与审计弹框、分享漏斗只有日期范围，见 pages/community.tsx） */
  communityGroups?: boolean;
  shareStats?: boolean;
  /**
   * 是否分页，缺省 true。整表端点（无 total，且是别处的下拉选项源）与裸数组/树传 false：
   * 见 RowBrowser 的 paged 说明，逐项列在下面各组的注释里。
   */
  paged?: boolean;
  crud?: CrudConfig;
};
type PageDef = { title: MessageKey; sub?: MessageKey; groups: Group[]; account?: boolean };

/** 端点多于一个的页面：标签切换，省掉每个端点一个页面文件。标题与标签写的是**文案键**
 * （`page.*` / `tab.*`）—— 本对象是模块级常量，这里求 `t()` 会把文案冻在首次求值的语言上。 */
export const PAGES = {
  dashboard: {
    title: 'page.dashboard.title',
    sub: 'page.dashboard.sub',
    groups: [
      // 三组的形状互不相同（见 pages/dashboard.tsx 顶部的形状清单）：只有第一组单开渲染器，
      // 平台总览（七个裸标量）与健康检查（六个标量）本来就适合 AutoView 的统计块
      { label: 'tab.dashboard', path: '/admin/v1/dashboard', dashboard: true },
      { label: 'tab.dashboard.platform', path: '/admin/v1/dashboard/platform' },
      { label: 'tab.health', path: '/health' },
      // /metrics 是 Prometheus text 格式，非信封 JSON，不在此渲染
    ],
  },

  analytics: {
    title: 'page.analytics.title',
    sub: 'page.analytics.sub',
    groups: [
      { label: 'tab.analytics.overview', path: '/admin/v1/analytics/overview' },
      { label: 'tab.analytics.game_ranking', path: '/admin/v1/analytics/game-ranking' },
      { label: 'tab.analytics.dau_trend', path: '/admin/v1/analytics/dau-trend' },
      { label: 'tab.analytics.hourly_trend', path: '/admin/v1/analytics/hourly-trend' },
      { label: 'tab.analytics.action_distribution', path: '/admin/v1/analytics/action-distribution' },
      { label: 'tab.analytics.revenue', path: '/admin/v1/analytics/revenue' },
      { label: 'tab.analytics.conversion', path: '/admin/v1/analytics/conversion' },
      { label: 'tab.analytics.probability', path: '/admin/v1/analytics/probability' },
      { label: 'tab.analytics.retention', path: '/admin/v1/analytics/retention' },
      { label: 'tab.analytics.funnel', path: '/admin/v1/analytics/funnel' },
      { label: 'tab.analytics.arpu', path: '/admin/v1/analytics/arpu' },
      { label: 'tab.analytics.economy', path: '/admin/v1/analytics/economy' },
      // 报表：原来只有 /report/daily 一个裸标签（没有日期范围、没有汇总、没有导出），
      // 换成一整块面板把 summary + daily + export 收在一起（见 pages/reports.tsx）
      { label: 'tab.report', report: true },
    ],
  },

  community: {
    title: 'page.community.title',
    sub: 'page.community.sub',
    groups: [
      // 群组只读（后端没有增删改端点）⇒ 配置里只给 views，一个新建/编辑/删除按钮都不长
      { label: 'tab.groups', path: '/admin/v1/groups', list: true, communityGroups: true },
      { label: 'tab.share_stats', path: '/admin/v1/share/stats', shareStats: true },
    ],
  },

  games: {
    title: 'page.games.title',
    sub: 'page.games.sub',
    groups: [
      { label: 'tab.game.list', path: '/admin/v1/game/list', list: true, preferred: ['game_id', 'id', 'name', 'game_name', 'status', 'created_at'], crud: GAME_CRUD },
      // 区服是**裸数组**（game_id 必填、一次回全量）：不加 pager
      { label: 'tab.game.server', path: '/admin/v1/game/server/list', list: true, gameServers: true, preferred: ['id', 'name', 'region', 'status', 'sort'] },
      // 分类：端点整表返回（没有 total），且是全平台的下拉选项源，故不分页
      { label: 'tab.game.category', path: '/admin/v1/game/category/list', list: true, paged: false, preferred: ['id', 'name', 'slug', 'sort', 'status'], crud: CATEGORY_CRUD },
      { label: 'tab.leaderboard', path: '/admin/v1/leaderboard/list', list: true, preferred: ['id', 'name', 'type', 'metric', 'game_id', 'status'], crud: LEADERBOARD_CRUD },
      // 成就：端点整表返回（没有 total），且是全平台的下拉选项源，故不分页
      { label: 'tab.achievement', path: '/admin/v1/achievement/list', list: true, paged: false, preferred: ['id', 'key', 'name', 'points', 'status'], crud: ACHIEVEMENT_CRUD },
      { label: 'tab.activity', path: '/admin/v1/activities/list', list: true, preferred: ['id', 'name', 'type', 'status', 'start_at'], crud: ACTIVITY_CRUD },
      { label: 'tab.announcement', path: '/admin/v1/announcement/list', list: true, preferred: ['id', 'title', 'type', 'status', 'start_at', 'created_at'], crud: ANNOUNCEMENT_CRUD },
    ],
  },

  users: {
    title: 'page.users.title',
    sub: 'page.users.sub',
    groups: [
      { label: 'tab.platform_user', path: '/admin/v1/platform/user/list', list: true, platformUsers: true, preferred: ['user_id', 'id', 'username', 'nickname', 'status', 'vip_level', 'created_at'] },
      // 状态列必须看得见（已审过的记录再点「通过」会被 422 挡下）：默认前 8 列会把 status 挤掉
      { label: 'tab.identity', path: '/admin/v1/identity/list', list: true, preferred: ['id', 'real_name', 'id_type', 'status', 'reviewed_at'], crud: IDENTITY_CRUD },
      // VIP 等级：端点整表返回（没有 total），且是全平台的下拉选项源，故不分页
      { label: 'tab.vip', path: '/admin/v1/vip/level/list', list: true, paged: false, preferred: ['id', 'level', 'name', 'required_exp', 'benefits'], crud: VIP_CRUD },
      { label: 'tab.ticket', path: '/admin/v1/ticket/list', list: true, preferred: ['id', 'subject', 'type', 'status', 'priority', 'assigned_to', 'user_name', 'created_at'], crud: TICKET_CRUD },
    ],
  },

  admins: {
    title: 'page.admins.title',
    sub: 'page.admins.sub',
    groups: [
      // 端点 /admin/v1/user 是**后台账号**，与「用户」页的 /admin/v1/platform/user/list（C 端玩家）不是一回事
      {
        label: 'tab.admin_user',
        path: '/admin/v1/user',
        list: true,
        preferred: ['id', 'username', 'real_name', 'phone', 'email', 'status', 'last_login_at'],
        // 行点击 → GET /user/{hashid}（Route::resource 的 show，回包里带 role_ids 且已抹掉 password/id_card）
        detailBase: '/admin/v1/user',
        crud: ADMIN_USER_CRUD,
      },
    ],
  },

  withdrawals: {
    title: 'page.withdrawals.title',
    sub: 'page.withdrawals.sub',
    groups: [
      // 提现订单要走自定义页签：批量审核端点没有行上下文（见 funds.tsx）
      { label: 'tab.withdraw.order', path: '/admin/v1/withdraw/orders', list: true, withdrawOrders: true },
      { label: 'tab.withdraw.switch', path: '/admin/v1/withdraw/switch', withdrawSwitch: true },
      { label: 'tab.withdraw.limits', path: '/admin/v1/withdraw/limits/list', list: true, withdrawLimits: true },
      {
        label: 'tab.payment',
        path: '/admin/v1/payment/method/list',
        list: true,
        // 端点整表返回（没有 total），且是全平台的下拉选项源，故不分页
        paged: false,
        preferred: ['id', 'name', 'type', 'provider', 'currency', 'min_amount', 'max_amount', 'status', 'sort'],
        crud: PAYMENT_CRUD,
      },
      {
        label: 'tab.coupon',
        path: '/admin/v1/coupon/list',
        list: true,
        preferred: ['id', 'name', 'type', 'value', 'min_amount', 'total_qty', 'used_qty', 'status', 'end_at'],
        crud: COUPON_CRUD,
      },
    ],
  },

  risk: {
    title: 'page.risk.title',
    sub: 'page.risk.sub',
    groups: [
      { label: 'tab.risk.overview', path: '/admin/v1/risk/overview' },
      { label: 'tab.risk.dashboard', path: '/admin/v1/risk/dashboard' },
      // 冻结/解冻两个资金动作挂在行上；列表把 hashid 放在 user_id 列（crud.rowKey 认它）
      {
        label: 'tab.risk.users',
        path: '/admin/v1/risk/users',
        list: true,
        preferred: ['user_id', 'username', 'score', 'band', 'hit_count', 'last_hit_at', 'whitelisted'],
        crud: RISK_USER_CRUD,
      },
      // 状态列必须看得见：处置只记操作审计、列表无处置态，判断依据就是 type/action/result 这几列
      {
        label: 'tab.risk.event',
        path: '/admin/v1/risk/event/list',
        list: true,
        preferred: ['id', 'rule_name', 'type', 'action', 'result', 'user_id', 'created_at'],
        crud: RISK_EVENT_CRUD,
      },
      {
        label: 'tab.risk.rule',
        path: '/admin/v1/risk/rule/list',
        list: true,
        preferred: ['id', 'name', 'type', 'action', 'scope', 'priority', 'status'],
        crud: RISK_RULE_CRUD,
      },
      // 设备/IP 各是一整页（不是纯列表）：设备页要藏 fp_hash 列 + 挂行内拉黑/解封，
      // IP 页的行只有 ip_masked（封禁端点要原文 IP）⇒ 整页交给 risk.tsx
      { label: 'tab.risk.device', path: '/admin/v1/risk/device/list', list: true, riskDevices: true },
      { label: 'tab.risk.ip', path: '/admin/v1/risk/ip/list', list: true, riskIps: true },
      // 团伙在本批之前**没有页签**（端点 /risk/clusters 不带 /list 段）；检测与确认都没有行上下文
      { label: 'tab.risk.clusters', path: '/admin/v1/risk/clusters', list: true, riskClusters: true },
      // 统计页一次取三条端点（命中趋势 / 处置分布 / 规则效果），故 path 只是页签的身份、不是取数地址（见 pages/risk-stats.tsx）
      { label: 'tab.risk.stats', path: '/admin/v1/risk/hit-trend', riskStats: true },
      // 设备团伙（GET /risk/graph/clusters）：**另一条**路由，与上面 /risk/clusters 不是一回事
      // （那个是已确认团伙的 CRUD，这个是「同设备 TOP + 关联类型统计」的聚合视图）。
      // 形状未确认 ⇒ 交给 Section → AutoView（两张卡：device_clusters 表 + link_type_stats 计数）。
      { label: 'tab.risk.graph', path: '/admin/v1/risk/graph/clusters' },
      {
        label: 'tab.anticheat',
        path: '/admin/v1/anticheat/events',
        list: true,
        preferred: ['id', 'rule_name', 'severity', 'action', 'status', 'user_id', 'created_at'],
        crud: ANTICHEAT_CRUD,
      },
    ],
  },

  profile: {
    title: 'page.profile.title',
    sub: 'page.profile.sub',
    account: true,
    groups: [
      { label: 'tab.config', path: '/admin/v1/config', list: true, preferred: ['id', 'group', 'key', 'value', 'type'], crud: CONFIG_CRUD },
      // 角色/权限的 index 挂在 /role、/permission 本身（Route::resource，**不是** /role/list）
      { label: 'tab.role', path: '/admin/v1/role', list: true, preferred: ['id', 'name', 'slug', 'description', 'status', 'users_count'], crud: ROLE_CRUD },
      // 权限是树：tree 让 RowBrowser 把 children 摊成带缩进/箭头的表行（子节点也是可操作的行），
      // 否则子节点只是顶层行里的一个「n 项」，改不到。端点是**树**（裸节点数组、没有 total）故不分页。
      // preferred 把 name 排第一：树标记挂在首列，箭头要挨着名字
      { label: 'tab.permission', path: '/admin/v1/permission', list: true, tree: 'children', paged: false, preferred: ['name', 'slug', 'id', 'type', 'path', 'sort', 'parent_name'], crud: PERMISSION_CRUD },
      // CDN 列表不回传 config（凭据），故编辑表单里它是空的：留空 = 不改（见 CDN_FIELDS 的 hint）
      {
        label: 'tab.cdn',
        path: '/admin/v1/cdn/provider/list',
        list: true,
        // 端点整表返回（没有 total），且是全平台的下拉选项源，故不分页
        paged: false,
        preferred: ['id', 'name', 'provider', 'status', 'sort'],
        crud: CDN_CRUD,
      },
      { label: 'tab.country', path: '/admin/v1/country/config/list', list: true, preferred: ['id', 'country_code', 'currency', 'min_deposit', 'status'], crud: COUNTRY_CRUD },
    ],
  },
} satisfies Record<string, PageDef>;

/**
 * 区服挂在游戏下：列表端点 `/admin/v1/game/server/list` 的 game_id 是**必填**，
 * 所以先选游戏再渲染该游戏的区服，并把选中项传给新建表单（新建也要 game_id）。
 * 游戏下拉取首页 200 条 —— 这是后台的游戏基数（当前个位数），够用；
 * ponytail: 不做服务端搜索，游戏上千时换成带 keyword 的远程搜索。
 */
function GameServers({ path }: { path: string }) {
  const { data, loading, error } = useApi<unknown>('/admin/v1/game/list', { limit: 200 });
  const [gameId, setGameId] = useState('');
  const games = asRows(data) ?? [];

  if (loading) return <Loading />;

  return (
    <>
      <div className="toolbar">
        <label className="label">
          {t('nav.games')}
          <select className="input" value={gameId} onChange={(event) => setGameId(event.target.value)}>
            <option value="">{t('browser.pick_game')}</option>
            {games.map((game) => {
              const id = String(pick(game, ['id', 'game_id']) ?? '');
              return (
                <option key={id} value={id}>
                  {String(game.name ?? game.game_name ?? id)}
                </option>
              );
            })}
          </select>
        </label>
      </div>
      {error ? <ErrorNote message={error} /> : null}
      {gameId === '' ? (
        <p className="muted">{t('tab.game_server_pick')}</p>
      ) : (
        // 区服端点是裸数组（game_id 必填、一次回全量）：不加分页
        <RowBrowser
          path={path}
          query={{ game_id: gameId }}
          preferred={['id', 'name', 'region', 'status', 'sort']}
          paged={false}
          crud={serverCrud(gameId)}
        />
      )}
    </>
  );
}

/** 路由传的页面键（`<TabPage id="dashboard" />`）。传键不传对象：`PAGES` 是本文件的模块级常量，
 *  在 `App.tsx` 里静态引用它会把整个 TabPage 模块图（含 1535 行的 `modules.ts`）拉回首屏，
 *  页面级切分就白做了。 */
export type PageKey = keyof typeof PAGES;

export function TabPage({ id }: { id: PageKey }) {
  // 页面**自己**订阅语言。不能指望布局层的重绘带过来：实测切到中文后，顶栏/侧栏变中文而
  // 页面里的按钮与表格仍是英文（Shell 重绘 ≠ <Outlet/> 子树重绘）。这一行让整棵页面子树重渲。
  useI18n();
  const page: PageDef = PAGES[id];
  // 账号标签无端点，path 缺省即本地渲染
  const groups: Group[] = page.account ? [{ label: 'account.title' }, ...page.groups] : page.groups;
  const [active, setActive] = useState(0);
  const [shownPage, setShownPage] = useState(page);
  // 换页面要回到第一个标签。多个路由共用同一个 TabPage 组件实例 ⇒ 不重置就会把上一个页面的
  // 标签**序号**带过来（真机实测：社群页停在第 2 个标签，切到分析页落在「游戏排行」而不是「总览」）。
  // 渲染期直接改 state 是 React 认可的写法（比 useEffect 少一帧错误内容）。
  if (shownPage !== page) {
    setShownPage(page);
    setActive(0);
  }
  const group: Group = groups[active] ?? groups[0] ?? { label: 'tab.none' };

  return (
    <>
      <PageHead title={t(page.title)} sub={page.sub === undefined ? undefined : t(page.sub)} />
      {groups.length > 1 ? (
        <Tabs
          tabs={groups.map((item, index) => ({ key: String(index), label: t(item.label) }))}
          value={String(active)}
          onChange={(key) => setActive(Number(key))}
        />
      ) : null}
      {/* report 的分支**必须排在 `!group.path` 之前**：报表面板是唯一「没有 path 却要渲染面板」的组
          （它自己把 summary + daily + export 三个端点收在一起），摆在后面会被 path 判定先截走，
          渲染成「我的账号」卡片。真机实测抓到的 —— tsc 与 node 侧用例都看不见这一类。 */}
      {group.report ? (
        <ReportPanel />
      ) : !group.path ? (
        <AccountCard />
      ) : group.list ? (
        <Card title={t(group.label)}>
          {group.platformUsers ? (
            <PlatformUsers path={group.path} preferred={group.preferred} />
          ) : group.gameServers ? (
            <GameServers path={group.path} />
          ) : group.withdrawOrders ? (
            <WithdrawOrders path={group.path} />
          ) : group.withdrawLimits ? (
            <WithdrawLimits path={group.path} />
          ) : group.riskDevices ? (
            <RiskDevices path={group.path} />
          ) : group.riskIps ? (
            <RiskIps path={group.path} />
          ) : group.riskClusters ? (
            <RiskClusters path={group.path} />
          ) : group.communityGroups ? (
            <CommunityGroups />
          ) : (
            <RowBrowser
              path={group.path}
              preferred={group.preferred}
              tree={group.tree}
              paged={group.paged}
              detailBase={group.detailBase}
              crud={group.crud}
            />
          )}
        </Card>
      ) : group.withdrawSwitch ? (
        <Card title={t(group.label)}>
          <WithdrawSwitch />
        </Card>
      ) : group.shareStats ? (
        <ShareStats />
      ) : group.riskStats ? (
        // 不自带 Card（三块各自成卡）：套一层 Card 会变成卡中卡
        <RiskStats />
      ) : group.dashboard ? (
        // 同上：四块各自成卡，这里不再套一层
        <DashboardView />
      ) : (
        <Section title={t(group.label)} path={group.path} />
      )}
    </>
  );
}
