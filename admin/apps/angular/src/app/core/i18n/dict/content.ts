/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 内容运营页词条（content.ts：成就 / 活动 / 公告 / 排行榜）。
 *
 * 键名沿用 `admin/apps/flutter` 的 translations.dart —— 那边有 `achievement.*`、`activity.*`、
 * `announcement.*`、`leaderboard.*` 四族同名页面，同名同义的（*.title / achievement.key /
 * achievement.condition / activity.type_signin / activity.config / announcement.field_title /
 * announcement.type_system / leaderboard.metric_earned / leaderboard.rule …）逐字沿用那边的键名。
 *
 * ⚠ flutter 那边同一字段的**键名与这侧不完全对应**（那边 hint 与 label 常共用一个键，
 * 例如 `announcement.publish_time` 既是字段名也是提示）：本树 label 与 placeholder 是两份文案，
 * 所以这里按「同义就近」挂键，值不同的各自新开一个 `_hint` 键。
 * 中文一侧**逐字等于抽取前的界面原文**。
 */
export const CONTENT: Record<string, [string, string]> = {
  'content.title': ['Content', '内容运营'],
  'content.subtitle': ['Achievements / Activities / Announcements / Leaderboards', '成就 / 活动 / 公告 / 排行榜'],
  'content.search_hint': ['Name / ID', '名称 / ID'],

  // ---- 公告（announcement）----
  'announcement.noun': ['announcement', '公告'],
  'announcement.title': ['Announcement Management', '公告'],
  'announcement.field_title': ['Title', '公告标题'],
  'announcement.title_hint': ['Max 255 characters', '最长 255'],
  'announcement.type': ['Type', '公告类型'],
  'announcement.type_system': ['System', '系统'],
  'announcement.type_game': ['Game', '游戏'],
  'announcement.type_payment': ['Payment', '支付'],
  'announcement.status': ['Publish status', '上架状态'],
  'announcement.target_lang': ['Target Language', '目标语言'],
  'announcement.target_lang_hint': ['Empty = all languages, max 10 characters', '留空 = 全语言，最长 10'],
  'announcement.start_at': ['Start time', '生效时间'],
  'announcement.start_hint': ['2026-09-30 12:00:00, empty = no bound', '2026-09-30 12:00:00，留空 = 不限'],
  'announcement.end_at': ['End time', '失效时间'],
  'announcement.end_hint': ['Must not be earlier than the start time', '不得早于生效时间'],
  'announcement.content': ['Content', '公告内容'],

  // ---- 成就（achievement）----
  'achievement.noun': ['achievement', '成就'],
  'achievement.title': ['Achievement Management', '成就'],
  'achievement.key': ['Key', '成就标识'],
  'achievement.key_hint': ['lowercase letters / digits / _, max 50 characters', '小写字母/数字/_，最长 50'],
  'achievement.name': ['Name', '成就名称'],
  'achievement.name_hint': ['Max 100 characters', '最长 100'],
  'achievement.description': ['Description', '成就描述'],
  'achievement.description_hint': ['Max 500 characters', '最长 500'],
  'achievement.icon': ['Icon', '图标'],
  'achievement.icon_hint': ['Image URL, max 200 characters', '图片 URL，最长 200'],
  'achievement.condition': ['Condition (JSON)', '达成条件（JSON）'],
  'achievement.condition_hint': [
    '{"event":"game.played","metric":"count","threshold":10}',
    '{"event":"game.played","metric":"count","threshold":10}',
  ],
  'achievement.points': ['Points', '奖励积分'],
  'achievement.points_hint': ['Integer >= 0', '≥ 0 的整数'],

  // ---- 活动（activity）----
  'activity.noun': ['activity', '活动'],
  'activity.title': ['Activities', '活动'],
  'activity.type': ['Type', '活动类型'],
  'activity.type_signin': ['Sign-in', '签到'],
  'activity.type_daily_task': ['Daily Task', '每日任务'],
  'activity.type_invite': ['Invite', '邀请'],
  'activity.name': ['Name', '活动名称'],
  'activity.name_hint': ['Max 100 characters', '最长 100'],
  'activity.game_id': ['Game ID (numeric, 0 = all platforms)', '关联游戏 ID（数字，0 = 全平台）'],
  'activity.game_id_hint': ['Raw database ID (not a hashid)', '数据库原始 ID（不是 hashid）'],
  'activity.config': ['Config (JSON, validated per type)', '活动配置（JSON，按类型校验）'],
  'activity.config_hint': ['Empty = the default config of that type', '留空 = 用该类型的默认配置'],
  'activity.status': ['Status', '活动状态'],
  'activity.status_disabled': ['Disabled', '禁用'],
  'activity.status_enabled': ['Enabled', '启用'],
  'activity.status_ended': ['Ended', '已结束'],
  'activity.start_at': ['Start time', '生效时间'],
  'activity.time_hint': ['2026-09-30 12:00:00, empty = no bound', '2026-09-30 12:00:00，留空 = 不限'],
  'activity.end_at': ['End time', '失效时间'],
  'activity.end_hint': ['Must not be earlier than the start time', '不得早于生效时间'],
  'activity.rollout_percent': ['Rollout %', '灰度比例（%）'],
  'activity.rollout_hint': ['0-100, empty = 100', '0-100，留空 = 100'],

  // ---- 排行榜（leaderboard）----
  'leaderboard.noun': ['leaderboard', '排行榜'],
  'leaderboard.title': ['Leaderboards', '排行榜'],
  'leaderboard.name': ['Name', '排行榜名称'],
  'leaderboard.name_hint': ['Max 100 characters', '最长 100'],
  'leaderboard.type': ['Type', '榜单周期'],
  'leaderboard.type_daily': ['Daily', '日榜'],
  'leaderboard.type_weekly': ['Weekly', '周榜'],
  'leaderboard.type_monthly': ['Monthly', '月榜'],
  'leaderboard.type_alltime': ['All Time', '总榜'],
  'leaderboard.metric': ['Metric', '排行指标'],
  'leaderboard.metric_earned': ['Earned', '累计获得'],
  'leaderboard.metric_spent': ['Spent', '累计消耗'],
  'leaderboard.metric_play_count': ['Play Count', '游戏次数'],
  'leaderboard.game_id': ['Game', '关联游戏'],
  'leaderboard.game_id_hint': ['Game hashid, empty = all platforms', '游戏 hashid，留空 = 全平台'],
  'leaderboard.rule': ['Rule (JSON)', '排行规则（JSON）'],
  'leaderboard.rule_hint': ['Optional', '可选'],
  'leaderboard.status': ['Status', '启用状态'],
  'leaderboard.sort': ['Sort', '排序'],
  'leaderboard.sort_hint': ['Smaller comes first', '数字越小越靠前'],
  /** 行内动作：只清缓存并重算，不动榜单定义 */
  'leaderboard.refresh': ['Refresh cache', '刷新缓存'],
};
