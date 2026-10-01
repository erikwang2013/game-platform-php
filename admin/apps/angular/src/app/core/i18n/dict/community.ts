/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 社群页词条（community.ts：组队/公会列表 + 成员审计 + 分享裂变统计）。
 *
 * 三个只读端点：GET /admin/v1/groups、/groups/{hashid}/audit、/share/stats。后端没有群组的
 * 增删改（成员由游戏侧写入），所以这一族里没有 create/edit/delete 之类的动作文案 —— 页面也
 * 不摆那些按钮。
 *
 * 键名与文案沿用 `admin/apps/react` 的 i18n/<lang>.ui.ts（那边 community.tsx 是同样两屏）：
 * community.any_type / community.team / community.guild / community.any_status / community.any_game
 * / community.subtitle / tab 两条，以及 `share.range_hint` → `community.range_hint`，逐字沿用。
 * 页头标题**不另开键**，直接用侧栏那条 `nav.community`（与 games.ts 同款判断）。
 */
export const COMMUNITY: Record<string, [string, string]> = {
  'community.subtitle': [
    'Teams/guilds and share-fission statistics',
    '组队/公会与分享裂变统计',
  ],
  'community.tab_groups': ['Teams / Guilds', '组队 / 公会'],
  'community.tab_share': ['Share Statistics', '分享统计'],
  /** 下面三个 any_* 是筛选下拉的「全部…」项（后端缺参即不过滤） */
  'community.any_type': ['Any type', '全部类型'],
  'community.team': ['Team', '组队'],
  'community.guild': ['Guild', '公会'],
  'community.any_status': ['Any status', '全部状态'],
  'community.any_game': ['Any game', '全部游戏'],
  /** 行内按钮 + 审计抽屉标题（`f.audit` 的措辞：审计=成员变动流水） */
  'community.audit': ['Audit', '成员审计'],
  'community.start_date': ['Start date', '开始日期'],
  'community.end_date': ['End date', '结束日期'],
  /** 日期范围说明：留空即全量（后端只在给了 from/to 时才加条件）—— 摆两个空框要讲清楚这点 */
  'community.range_hint': [
    'Empty shows all data; the backend only filters when from/to are given',
    '留空即全部数据；后端只在填了起止日期时才加筛选',
  ],
  /** 漏斗三项的卡片标题（与按天表的表头是同一组词） */
  'community.shares': ['Shares', '分享数'],
  'community.clicks': ['Clicks', '点击数'],
  'community.conversions': ['Conversions', '转化数'],
  'community.reset': ['Reset', '重置'],
};
