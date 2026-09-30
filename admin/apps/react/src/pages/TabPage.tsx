/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useState } from 'react';
import { asRows, columnsFrom } from '../components/AutoView';
import { DataTable, type Row } from '../components/DataTable';
import { FormModal } from '../components/FormModal';
import { RowBrowser, type CrudConfig } from '../components/RowBrowser';
import { Section } from '../components/Section';
import { Card, ErrorNote, Field, Loading, PageHead, Tabs } from '../components/ui';
import { ApiError, api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { labelOf } from '../lib/crud';
import { ID_KEYS, pick } from '../lib/format';
import { useApi, useSignOut } from '../lib/hooks';
import { WithdrawLimits, WithdrawOrders, WithdrawSwitch } from './funds';
import { RiskClusters, RiskDevices, RiskIps } from './risk';
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
  PLATFORM_USER_FIELDS,
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
  label: string;
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
  /** 树形列表的 children 键（权限树：子节点也要成为可操作的行） */
  tree?: string;
  crud?: CrudConfig;
};
type PageDef = { title: string; sub?: string; groups: Group[]; account?: boolean };

/** 端点多于一个的页面：标签切换，省掉每个端点一个页面文件。 */
export const PAGES = {
  dashboard: {
    title: '概览',
    sub: '平台实时数据',
    groups: [
      { label: '仪表盘', path: '/admin/v1/dashboard' },
      { label: '平台统计', path: '/admin/v1/dashboard/platform' },
      { label: '健康检查', path: '/health' },
      // /metrics 是 Prometheus text 格式，非信封 JSON，不在此渲染
    ],
  },

  analytics: {
    title: '分析',
    sub: '实时聚合指标（服务端 bcmath 计算，前端原样展示）',
    groups: [
      { label: '总览', path: '/admin/v1/analytics/overview' },
      { label: '游戏排行', path: '/admin/v1/analytics/game-ranking' },
      { label: 'DAU 趋势', path: '/admin/v1/analytics/dau-trend' },
      { label: '小时趋势', path: '/admin/v1/analytics/hourly-trend' },
      { label: '行为分布', path: '/admin/v1/analytics/action-distribution' },
      { label: '营收', path: '/admin/v1/analytics/revenue' },
      { label: '转化', path: '/admin/v1/analytics/conversion' },
      { label: '概率', path: '/admin/v1/analytics/probability' },
      { label: '留存', path: '/admin/v1/analytics/retention' },
      { label: '漏斗', path: '/admin/v1/analytics/funnel' },
      { label: 'ARPU', path: '/admin/v1/analytics/arpu' },
      { label: '经济指标', path: '/admin/v1/analytics/economy' },
      { label: '日报', path: '/admin/v1/report/daily' },
    ],
  },

  games: {
    title: '游戏',
    sub: '游戏、分类与运营内容',
    groups: [
      { label: '游戏列表', path: '/admin/v1/game/list', list: true, preferred: ['game_id', 'id', 'name', 'game_name', 'status', 'created_at'], crud: GAME_CRUD },
      { label: '区服', path: '/admin/v1/game/server/list', list: true, gameServers: true, preferred: ['id', 'name', 'region', 'status', 'sort'] },
      { label: '分类', path: '/admin/v1/game/category/list', list: true, preferred: ['id', 'name', 'slug', 'sort', 'status'], crud: CATEGORY_CRUD },
      { label: '排行榜', path: '/admin/v1/leaderboard/list', list: true, preferred: ['id', 'name', 'type', 'metric', 'game_id', 'status'], crud: LEADERBOARD_CRUD },
      { label: '成就', path: '/admin/v1/achievement/list', list: true, preferred: ['id', 'key', 'name', 'points', 'status'], crud: ACHIEVEMENT_CRUD },
      { label: '活动', path: '/admin/v1/activities/list', list: true, preferred: ['id', 'name', 'type', 'status', 'start_at'], crud: ACTIVITY_CRUD },
      { label: '公告', path: '/admin/v1/announcement/list', list: true, preferred: ['id', 'title', 'type', 'status', 'start_at', 'created_at'], crud: ANNOUNCEMENT_CRUD },
    ],
  },

  users: {
    title: '用户',
    sub: '平台用户、身份与工单',
    groups: [
      { label: '平台用户', path: '/admin/v1/platform/user/list', list: true, platformUsers: true, preferred: ['user_id', 'id', 'username', 'nickname', 'status', 'vip_level', 'created_at'] },
      // 状态列必须看得见（已审过的记录再点「通过」会被 422 挡下）：默认前 8 列会把 status 挤掉
      { label: '身份', path: '/admin/v1/identity/list', list: true, preferred: ['id', 'real_name', 'id_type', 'status', 'reviewed_at'], crud: IDENTITY_CRUD },
      { label: 'VIP 等级', path: '/admin/v1/vip/level/list', list: true, preferred: ['id', 'level', 'name', 'required_exp', 'benefits'], crud: VIP_CRUD },
      { label: '工单', path: '/admin/v1/ticket/list', list: true, preferred: ['id', 'subject', 'type', 'status', 'priority', 'assigned_to', 'user_name', 'created_at'], crud: TICKET_CRUD },
    ],
  },

  withdrawals: {
    title: '资金',
    sub: '提现、支付方式与优惠券',
    groups: [
      // 提现订单要走自定义页签：批量审核端点没有行上下文（见 funds.tsx）
      { label: '提现订单', path: '/admin/v1/withdraw/orders', list: true, withdrawOrders: true },
      { label: '提现开关', path: '/admin/v1/withdraw/switch', withdrawSwitch: true },
      { label: '阶梯限额', path: '/admin/v1/withdraw/limits/list', withdrawLimits: true },
      {
        label: '支付方式',
        path: '/admin/v1/payment/method/list',
        list: true,
        preferred: ['id', 'name', 'type', 'provider', 'currency', 'min_amount', 'max_amount', 'status', 'sort'],
        crud: PAYMENT_CRUD,
      },
      {
        label: '优惠券',
        path: '/admin/v1/coupon/list',
        list: true,
        preferred: ['id', 'name', 'type', 'value', 'min_amount', 'total_qty', 'used_qty', 'status', 'end_at'],
        crud: COUPON_CRUD,
      },
    ],
  },

  risk: {
    title: '风控',
    sub: '风险总览、名单与反作弊',
    groups: [
      { label: '总览', path: '/admin/v1/risk/overview' },
      { label: '面板', path: '/admin/v1/risk/dashboard' },
      // 冻结/解冻两个资金动作挂在行上；列表把 hashid 放在 user_id 列（crud.rowKey 认它）
      {
        label: '风险用户',
        path: '/admin/v1/risk/users',
        list: true,
        preferred: ['user_id', 'username', 'score', 'band', 'hit_count', 'last_hit_at', 'whitelisted'],
        crud: RISK_USER_CRUD,
      },
      // 状态列必须看得见：处置只记操作审计、列表无处置态，判断依据就是 type/action/result 这几列
      {
        label: '事件',
        path: '/admin/v1/risk/event/list',
        list: true,
        preferred: ['id', 'rule_name', 'type', 'action', 'result', 'user_id', 'created_at'],
        crud: RISK_EVENT_CRUD,
      },
      {
        label: '规则',
        path: '/admin/v1/risk/rule/list',
        list: true,
        preferred: ['id', 'name', 'type', 'action', 'scope', 'priority', 'status'],
        crud: RISK_RULE_CRUD,
      },
      // 设备/IP 各是一整页（不是纯列表）：设备页要藏 fp_hash 列 + 挂行内拉黑/解封，
      // IP 页的行只有 ip_masked（封禁端点要原文 IP）⇒ 整页交给 risk.tsx
      { label: '设备', path: '/admin/v1/risk/device/list', list: true, riskDevices: true },
      { label: 'IP', path: '/admin/v1/risk/ip/list', list: true, riskIps: true },
      // 团伙在本批之前**没有页签**（端点 /risk/clusters 不带 /list 段）；检测与确认都没有行上下文
      { label: '团伙', path: '/admin/v1/risk/clusters', list: true, riskClusters: true },
      {
        label: '反作弊事件',
        path: '/admin/v1/anticheat/events',
        list: true,
        preferred: ['id', 'rule_name', 'severity', 'action', 'status', 'user_id', 'created_at'],
        crud: ANTICHEAT_CRUD,
      },
    ],
  },

  profile: {
    title: '我的',
    sub: '账号与系统设置',
    account: true,
    groups: [
      { label: '系统配置', path: '/admin/v1/config', list: true, preferred: ['id', 'group', 'key', 'value', 'type'], crud: CONFIG_CRUD },
      // 角色/权限的 index 挂在 /role、/permission 本身（Route::resource，**不是** /role/list）
      { label: '角色', path: '/admin/v1/role', list: true, preferred: ['id', 'name', 'slug', 'description', 'status', 'users_count'], crud: ROLE_CRUD },
      // 权限是树：tree 让 RowBrowser 把 children 展开成行，否则子节点只是顶层行里的一个「n 项」，改不到
      { label: '权限', path: '/admin/v1/permission', list: true, tree: 'children', preferred: ['id', 'name', 'slug', 'type', 'path', 'sort', 'parent_name'], crud: PERMISSION_CRUD },
      // CDN 列表不回传 config（凭据），故编辑表单里它是空的：留空 = 不改（见 CDN_FIELDS 的 hint）
      {
        label: 'CDN',
        path: '/admin/v1/cdn/provider/list',
        list: true,
        preferred: ['id', 'name', 'provider', 'status', 'sort'],
        crud: CDN_CRUD,
      },
      { label: '国家配置', path: '/admin/v1/country/config/list', list: true, preferred: ['id', 'country_code', 'currency', 'min_deposit', 'status'], crud: COUNTRY_CRUD },
    ],
  },
} satisfies Record<string, PageDef>;

function AccountCard() {
  const { user } = useAuth();
  const signOut = useSignOut();
  return (
    <Card title="账号" sub="当前登录管理员">
      <Field label="用户名" value={user?.username ?? '—'} />
      <Field label="姓名" value={user?.real_name ?? '—'} />
      <Field label="用户 ID" value={user?.id ?? '—'} />
      <Field
        label="操作"
        value={
          <button type="button" className="btn btn-sm" onClick={() => void signOut()}>
            退出登录
          </button>
        }
      />
    </Card>
  );
}

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
          游戏
          <select className="input" value={gameId} onChange={(event) => setGameId(event.target.value)}>
            <option value="">请选择游戏</option>
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
        <p className="muted">选择游戏后显示其区服。</p>
      ) : (
        <RowBrowser path={path} query={{ game_id: gameId }} preferred={['id', 'name', 'region', 'status', 'sort']} crud={serverCrud(gameId)} />
      )}
    </>
  );
}

/**
 * 平台用户列表：行内「编辑」（nickname / status，局部 PUT）与「注销」。
 * 不走 RowBrowser 的 CrudConfig —— 这个模块没有新建端点，也不该长出「+ 新建」；
 * 注销还要回读列表确认人真的不在了（见 PlatformUserDestroy），通用 delete 撑不住。
 */
function PlatformUsers({ path, preferred }: { path: string; preferred?: string[] }) {
  const { data, loading, error, reload } = useApi<unknown>(path);
  const [notice, setNotice] = useState<string | null>(null);
  const [editing, setEditing] = useState<Row | null>(null);

  const rows = asRows(data) ?? [];
  const columns = columnsFrom(rows, preferred);
  // 操作列排在末尾，不占 preferred 的列预算
  columns.push({
    key: '__actions',
    label: '操作',
    render: (row) => (
      <span className="rowact">
        <button
          type="button"
          className="btn btn-sm"
          onClick={() => {
            setNotice(null);
            setEditing(row);
          }}
        >
          编辑
        </button>
        <PlatformUserDestroy row={row} path={path} onNotice={setNotice} onDone={reload} />
      </span>
    ),
  });

  const key = editing ? pick(editing, ID_KEYS) : undefined;
  const id = key === null || key === undefined || key === '' ? '' : String(key);

  return (
    <>
      {notice ? <ErrorNote message={notice} /> : null}
      <DataTable columns={columns} rows={rows} loading={loading} error={error} onRetry={reload} />
      {editing ? (
        <FormModal
          key={id}
          title={`编辑用户 ${labelOf(editing, 'username')}`}
          fields={PLATFORM_USER_FIELDS}
          row={editing}
          submitLabel="保存"
          onSubmit={async (body) => {
            // 一个字段都没改就不空发一次 PUT（后端 update 是局部更新，空体等于无操作）
            if (id !== '' && Object.keys(body).length > 0) {
              await api(`/admin/v1/platform/user/${id}`, { method: 'PUT', body });
            }
            setEditing(null);
            reload();
          }}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </>
  );
}

/**
 * 平台用户注销 —— DELETE /admin/v1/platform/user/{hashid}。
 *
 * 后端拒绝有非零余额的用户（安全要求），拒绝原因在信封 message 里 —— 原样显示，
 * 不吞成「操作失败」，否则运营只看到「失败」而不知道该先清余额。
 * 成功以回读为准：重取列表，该用户不再出现才算成功，不以「请求发出去了」为准。
 */
function PlatformUserDestroy({
  row,
  path,
  onNotice,
  onDone,
}: {
  row: Row;
  path: string;
  onNotice: (message: string | null) => void;
  onDone: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const picked = pick(row, ID_KEYS);
  const key = picked === null || picked === undefined || picked === '' ? '' : String(picked);

  const destroy = async () => {
    // 注销前必须能看清是谁：用户名 + 昵称都摆进确认文案（列表里这两列可能被 preferred 挤掉）
    const nickname = String(row.nickname ?? '').trim();
    const who = `${labelOf(row, 'username')}${nickname === '' ? '' : `（${nickname}）`}`;
    if (!key || !window.confirm(`确认注销用户「${who}」？注销会清空其资料、会话与第三方绑定，且不可撤销。`)) return;
    onNotice(null);
    setBusy(true);
    try {
      await api(`/admin/v1/platform/user/${key}`, { method: 'DELETE' });
    } catch (cause) {
      onNotice(cause instanceof ApiError ? cause.message : '网络异常，请稍后重试');
      return;
    } finally {
      setBusy(false);
    }
    try {
      const after = await api<unknown>(path);
      if ((asRows(after) ?? []).some((r) => String(pick(r, ID_KEYS) ?? '') === key)) {
        onNotice('注销请求已提交，但该用户仍在列表中，请刷新确认');
      }
    } catch {
      onNotice('注销结果未能回读，请刷新确认');
    }
    onDone();
  };

  if (!key) return null;
  return (
    <button type="button" className="btn btn-sm" disabled={busy} onClick={() => void destroy()}>
      注销
    </button>
  );
}

export function TabPage({ page }: { page: PageDef }) {
  // 账号标签无端点，path 缺省即本地渲染
  const groups: Group[] = page.account ? [{ label: '账号' }, ...page.groups] : page.groups;
  const [active, setActive] = useState(0);
  const group: Group = groups[active] ?? groups[0] ?? { label: '' };

  return (
    <>
      <PageHead title={page.title} sub={page.sub} />
      {groups.length > 1 ? (
        <Tabs
          tabs={groups.map((item, index) => ({ key: String(index), label: item.label }))}
          value={String(active)}
          onChange={(key) => setActive(Number(key))}
        />
      ) : null}
      {!group.path ? (
        <AccountCard />
      ) : group.list ? (
        <Card title={group.label}>
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
          ) : (
            <RowBrowser path={group.path} preferred={group.preferred} tree={group.tree} crud={group.crud} />
          )}
        </Card>
      ) : group.withdrawSwitch ? (
        <Card title={group.label}>
          <WithdrawSwitch />
        </Card>
      ) : (
        <Section title={group.label} path={group.path} />
      )}
    </>
  );
}
