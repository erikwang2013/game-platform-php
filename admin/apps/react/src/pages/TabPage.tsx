/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useState } from 'react';
import { RowBrowser } from '../components/RowBrowser';
import { Section } from '../components/Section';
import { Card, Field, PageHead, Tabs } from '../components/ui';
import { useAuth } from '../lib/auth';
import { useSignOut } from '../lib/hooks';

/** 一个标签页 = 一个后端端点。list=true 走 RowBrowser（列表 + 首选列），其余交给 Section/AutoView。 */
type Group = { label: string; path?: string; list?: boolean; preferred?: string[] };
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
      { label: '游戏列表', path: '/admin/v1/game/list', list: true, preferred: ['game_id', 'id', 'name', 'game_name', 'status', 'created_at'] },
      { label: '分类', path: '/admin/v1/game/category/list', list: true, preferred: ['id', 'name', 'sort', 'status'] },
      { label: '排行榜', path: '/admin/v1/leaderboard/list', list: true },
      { label: '成就', path: '/admin/v1/achievement/list', list: true },
      { label: '活动', path: '/admin/v1/activities/list', list: true },
      { label: '公告', path: '/admin/v1/announcement/list', list: true },
    ],
  },

  users: {
    title: '用户',
    sub: '平台用户、身份与工单',
    groups: [
      { label: '平台用户', path: '/admin/v1/platform/user/list', list: true, preferred: ['user_id', 'id', 'username', 'nickname', 'status', 'vip_level', 'created_at'] },
      { label: '身份', path: '/admin/v1/identity/list', list: true },
      { label: 'VIP 等级', path: '/admin/v1/vip/level/list', list: true },
      { label: '工单', path: '/admin/v1/ticket/list', list: true },
    ],
  },

  withdrawals: {
    title: '资金',
    sub: '提现、支付方式与优惠券',
    groups: [
      { label: '提现订单', path: '/admin/v1/withdraw/orders', list: true, preferred: ['order_id', 'id', 'user_id', 'amount', 'status', 'created_at'] },
      { label: '支付方式', path: '/admin/v1/payment/method/list', list: true },
      { label: '优惠券', path: '/admin/v1/coupon/list', list: true },
    ],
  },

  risk: {
    title: '风控',
    sub: '风险总览、名单与反作弊',
    groups: [
      { label: '总览', path: '/admin/v1/risk/overview' },
      { label: '面板', path: '/admin/v1/risk/dashboard' },
      { label: '风险用户', path: '/admin/v1/risk/users' },
      { label: '事件', path: '/admin/v1/risk/event/list', list: true },
      { label: '规则', path: '/admin/v1/risk/rule/list', list: true },
      { label: '设备', path: '/admin/v1/risk/device/list', list: true },
      { label: 'IP', path: '/admin/v1/risk/ip/list', list: true },
      { label: '反作弊事件', path: '/admin/v1/anticheat/events', list: true },
    ],
  },

  profile: {
    title: '我的',
    sub: '账号与系统设置',
    account: true,
    groups: [
      { label: '系统配置', path: '/admin/v1/config' },
      { label: '角色', path: '/admin/v1/role' },
      { label: '权限', path: '/admin/v1/permission' },
      { label: 'CDN', path: '/admin/v1/cdn/provider/list', list: true },
      { label: '国家配置', path: '/admin/v1/country/config/list', list: true },
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
          <RowBrowser path={group.path} preferred={group.preferred} />
        </Card>
      ) : (
        <Section title={group.label} path={group.path} />
      )}
    </>
  );
}
