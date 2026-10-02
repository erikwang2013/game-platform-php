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
  // —— 运营活动（`pages/activities.ts`）——
  //    标的是**不可签到的原因**（不是错误）：三类判据见该页文件头注释，全部来自服务端读码。
  //    另：`activities.type_*` 三个键是 `ACTIVITY_TYPE_LABEL` 的**值**（组件数据表走「值=键」那一路），
  //    `invite` 那条与 `invite.title` 共用同一个键——「邀请好友」是同一个功能不是两句话。
  'activities.title': ['Campaigns', '运营活动'],
  'activities.granted': [
    'Granted: {summary} (see it in your wallet history)',
    '已发放：{summary}（可在钱包流水中查看）',
  ],
  'activities.claimed_none': [
    'Target reached (nothing to grant this time)',
    '已达标（本次没有可发放的奖励）',
  ],
  'activities.empty_title': ['No campaigns to join', '暂无可参与的活动'],
  'activities.empty_hint': [
    'The platform has not launched any campaign yet, or this account is not in the rollout group',
    '平台还没投放活动，或当前账号不在灰度范围内',
  ],
  'activities.ends_at': ['Ends {time}', '截止 {time}'],
  'activities.go_play': ['Go play the game', '去玩对应游戏'],
  'activities.auto_invite': ['Counts automatically when a friend registers', '按好友注册自动累计'],
  'activities.auto_task': [
    'Counts automatically once the task conditions are met',
    '按任务条件自动累计',
  ],
  'activities.in_game': ['Complete it inside the game', '请在对应游戏内完成'],
  'activities.done_today': ['Completed today', '今日已完成'],
  'activities.not_started_today': ['Not started today', '今日还没开始'],
  'activities.progress_today': ['Today: {current} / {target}', '今日进度 {current} / {target}'],
  'activities.claimed': ['Claimed', '已领取'],
  'activities.reached': ['Reached', '已达标'],
  'activities.idle': ['Not started', '未开始'],
  'activities.running': ['In progress', '进行中'],
  'activities.claimed_today': ['Claimed today', '今日已领'],
  'activities.checkin': ['Check in', '签到'],
  'activities.type_signin': ['Daily check-in', '每日签到'],
  'activities.type_daily_task': ['Daily task', '每日任务'],

  // —— 游戏详情（`pages/game.ts`）——
  'game.back_hall': ['Back to the hall', '返回大厅'],
  'game.launch': ['Start game', '开始游戏'],
  'game.launching': ['Starting…', '正在启动…'],
  'game.launch_hint': [
    'Not signed in — tapping this takes you to the sign-in page first',
    '未登录，点击将先跳转登录',
  ],
  'game.launched': ['Launched', '已启动'],
  'game.session_id': ['Session ID', '会话 ID'],
  'game.entry': ['Game entry point', '游戏入口'],
  'game.session_hint': [
    'The session is valid for 5 minutes — complete the integration on the game side as soon as possible.',
    '会话有效期 5 分钟，请在游戏端尽快完成接入。',
  ],
  'game.currencies': ['Supported currencies', '支持币种'],
  'game.integration': ['Integration details', '接入信息'],
  'game.api_endpoint': ['API endpoint', '接口地址'],
  'game.slug': ['Game slug', '游戏标识'],
  'game.id': ['Game ID', '游戏 ID'],

  // —— 排行榜（`pages/leaderboard.ts`）。下面两组是组件数据表的值（`METRIC_LABEL` / `TYPE_LABEL`），
  //    真源是 `LeaderboardService::computeRanking` 认的那三个 metric / 四个周期 ——
  'leaderboard.title': ['Leaderboard', '排行榜'],
  'leaderboard.metric_earned': ['Total bought', '累计买入'],
  'leaderboard.metric_spent': ['Total sold', '累计卖出'],
  'leaderboard.metric_play_count': ['Games played', '开局次数'],
  'leaderboard.type_daily': ['Daily', '日榜'],
  'leaderboard.type_weekly': ['Weekly', '周榜'],
  'leaderboard.type_monthly': ['Monthly', '月榜'],
  'leaderboard.type_all': ['All-time', '总榜'],
  'leaderboard.empty_title': ['No leaderboard yet', '暂无榜单'],
  'leaderboard.empty_hint': [
    'The platform has not enabled any leaderboard yet',
    '平台还没有开启排行榜',
  ],
  'leaderboard.rank_empty_title': ['This leaderboard is still empty', '榜单还是空的'],
  'leaderboard.rank_empty_hint': [
    'No rankable data has been produced in this period yet',
    '该周期内还没有产生可统计的数据',
  ],
};
