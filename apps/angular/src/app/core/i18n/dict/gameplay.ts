/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 玩法域：活动页签到结果、锦标赛报名结果。
 *
 * **这三条为什么需要键**：同 `identity.ts` —— 都是响应回调里 `set(…)` 的异步文案。
 *
 * ⚠ 活动页那两条是**同一格 `note` 的互斥分支**（`已领过` / `已记录未达标`）：
 * 状态机里的第三个分支（达标可领）走的是按钮，不落文案。所以两条都要有键，
 * 少一条就会在切语言后留下中文残影。
 */
export const GAMEPLAY: Record<string, [string, string]> = {
  // —— 活动签到 ——
  'activities.already': ['Already claimed today — come back tomorrow.', '今天已经领过了，明天再来。'],
  'activities.not_yet': [
    'Recorded — target not reached yet, keep going.',
    '已记录，还没达到目标，继续加油。',
  ],
  // —— 锦标赛报名 ——
  'tournaments.joined': [
    'Joined — you will show up on the leaderboard once it starts.',
    '报名成功，开赛后会出现在排行榜里。',
  ],
  // —— 我的游戏（playlogs 页）：复用 react 树同键的 13 语译文，中文串逐字相同才复用 ——
  'mygames.title': ['Game assets', '游戏资产'],
  'mygames.empty_title': ['No game assets yet', '还没有游戏资产'],
  'mygames.col_session': ['Session', '会话'],
  'mygames.col_before': ['Before', '变动前'],
  'mygames.col_change': ['Change', '变动'],
  'mygames.col_after': ['After', '变动后'],
  'mygames.col_platform_change': ['Platform coin change', '平台币变动'],
  'mygames.col_window': ['Start / End', '开始 / 结束'],
  'mygames.col_time': ['Recorded at', '记录时间'],
  'mygames.action_launch': ['Launch', '启动'],
  'mygames.action_bet': ['Bet', '下注'],
  'mygames.action_settle': ['Settle', '结算'],
  'mygames.action_refund': ['Refund', '退还'],
  'mygames.action_end': ['End', '结束'],
  'mygames.action_earn': ['Win', '赢取'],
  'mygames.action_spend': ['Spend', '消耗'],
  'mygames.action_start': ['Start', '开局'],
  'mygames.empty_hint': ['Exchange game coins in the wallet and each game\'s balance appears here', '在钱包里兑换游戏币后，各游戏的余额会显示在这里'],
  'mygames.no_logs_title': ['No game activity yet', '暂无游戏流水'],
  'mygames.no_logs_hint': ['Once you play a game and its coins change, the record appears here', '进入任意游戏并产生游戏币变动后，记录会出现在这里'],
  'mygames.detail_title': ['Activity detail', '流水详情'],
  'mygames.col_action': ['Action', '动作'],
  // —— 赛事（tournaments 页）：除下面两条 own 外，全部逐字取自 react 树同键 ——
  'tourney.title': ['Tournaments', '赛事'],
  'tourney.all_platform': ['All platforms', '全平台'],
  'tourney.leaderboard': ['Leaderboard', '排行榜'],
  'tourney.tab_upcoming': ['Upcoming', '即将开始'],
  'tourney.tab_active': ['Live', '进行中'],
  'tourney.tab_ended': ['Ended', '已结束'],
  'tourney.empty_upcoming': ['No upcoming tournaments', '暂无即将开始的赛事'],
  'tourney.empty_active': ['No tournaments running right now', '当前没有进行中的赛事'],
  'tourney.empty_ended': ['No finished tournaments yet', '还没有已结束的赛事'],
  'tourney.empty_hint': ['Try another tab, or come back later', '换个标签看看，或稍后再来'],
  'tourney.players_max': ['{current} / {max} players', '{current} / {max} 人'],
  'tourney.players': ['{count} players', '{count} 人'],
  'tourney.free': ['Free', '免费'],
  'tourney.prize_pool': ['Prize pool', '奖池'],
  'tourney.detail_title': ['Tournament details', '赛事详情'],
  'tourney.entry_fee': ['Entry fee', '报名费'],
  'tourney.time': ['Time', '时间'],
  'tourney.players_col': ['Players', '人数'],
  'tourney.type': ['Type', '类型'],
  'tourney.game': ['Game', '游戏'],
  'tourney.joined_badge': ['Registered', '已报名'],
  'tourney.rank_suffix': [' · Rank {rank}', ' · 第 {rank} 名'],
  'tourney.joining': ['Registering…', '报名中…'],
  'tourney.join': ['Join tournament', '报名参赛'],
  'tourney.join_closed': ['Registration is closed (the server rejects entries once a tournament starts)', '报名已截止（服务端在开赛后拒收报名）'],
  'tourney.no_scores': ['No scores yet', '还没有成绩'],
  'tourney.score': ['Score', '积分'],
  'tourney.lb_hint': ['Sorted by score, up to 100 entries', '按积分倒序，最多 100 条'],
};
