/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 内容浏览域：首页大厅、平台公告、全局搜索、消息/会话。
 *
 * 为什么单开一个文件而不是塞进 `common.ts`：那四个页面各有一整套**页面内**文案
 * （空态标题+说明、引导语、分页按钮），放 `common.ts` 会把它从「跨域共用的词」
 * 变成杂物间 —— 而 `dictionary.ts` 的注释写明「一个域一个文件」的分法。
 *
 * ⚠ 长段落的**内联标记**（`<strong>` / `<b>`）按标记切段存，一段一个键：
 * `note_a` + `<strong>note_b</strong>` + `note_c`。切段是 i18n 的固有代价 ——
 * 中文把它塞进一句里看不出来，但拉丁语言里加粗的那半句位置会挪。
 */
export const CONTENT: Record<string, [string, string]> = {
  // —— 首页大厅 ——
  'home.hero_title': ['Find your next game', '发现你的下一款游戏'],
  'home.hero_sub': [
    'Multi-platform arcade · instant play · one wallet',
    '多平台游戏大厅 · 实时开局 · 统一钱包',
  ],
  'home.news_empty': ['No announcements yet', '暂无公告'],
  'home.lb_title': ['See who is on the board', '看看谁在榜上'],
  'home.lb_hint': [
    'Daily / weekly / monthly — ranked by buys, sells or games played',
    '日榜 / 周榜 / 月榜，按买入、卖出或开局次数排名',
  ],
  'home.hall_title': ['Game hall', '游戏大厅'],
  'home.empty_title': ['No games yet', '暂无游戏'],
  'home.empty_search': ['No matching game — try another keyword', '没有匹配的游戏，换个关键词试试'],
  'home.empty_platform': ['The platform has not listed any game yet', '平台还没有上架游戏'],

  // —— 平台公告 ——
  'announcements.title': ['Announcements', '平台公告'],
  'announcements.empty_title': ['No announcements yet', '暂无公告'],
  'announcements.empty_hint': [
    'Platform events and maintenance notices are posted here',
    '平台活动与维护通知会发布在这里',
  ],
  'announcements.detail_title': ['Announcement details', '公告详情'],
  'announcements.fallback_title': ['Announcement', '公告'],

  // —— 全局搜索 ——
  'search.placeholder': ['Search by game name or description', '搜索游戏名称或简介'],
  'search.idle_title': ['Enter a keyword to start searching', '输入关键词开始搜索'],
  'search.idle_hint': ['Matches game names and descriptions', '按游戏名称与简介匹配'],
  'search.page_empty_title': ['No results on this page', '这一页没有结果'],
  'search.page_empty_hint': [
    'This keyword does not have that many pages of results',
    '这个关键词的结果没有这么多页',
  ],
  'search.no_hit_title': ['No game found for “{q}”', '没有找到与「{q}」相关的游戏'],
  'search.no_hit_hint': ['Try another keyword', '换个关键词试试'],
  'search.back_to_first': ['Back to page 1', '回到第 1 页'],

  // —— 消息（会话列表）——
  'chat.title': ['Messages', '消息'],
  'chat.note_a': ['This page does not update on its own — ', '本页不会自动更新 —— '],
  'chat.note_b': ['refresh after new messages arrive', '新消息到达后刷新'],
  'chat.note_c': [
    '. Opening a conversation also marks the messages the other person sent as read, so refresh this page once more after you come back.',
    '。另外，打开某个对话本身就会把对方发来的消息标成已读，所以看完再回到本页也要刷新一次。',
  ],
  'chat.empty_title': ['No conversations yet', '还没有聊天记录'],
  'chat.empty_hint': ['Open Friends and tap Message next to someone', '去「好友」里点某人右边的「发消息」'],
  'chat.go_friends': ['Go to friends', '去好友列表'],

  // —— 消息（与某人的对话）——
  'chat.back_to_list': ['Back to messages', '返回消息'],
  'chat.room_note_a': ['This page does not update on its own — ', '本页不会自动更新 —— '],
  'chat.room_note_b': ['refresh after new messages arrive', '新消息到达后刷新'],
  'chat.room_note_c': [
    '. Opening this page already marks the messages the other person sent as read.',
    '。打开本页的同时，对方发来的消息就已经被标成已读了。',
  ],
  'chat.placeholder': ['Say something…', '说点什么…'],
  'chat.room_empty_title': ['No messages yet', '还没有聊过天'],
  'chat.room_empty_hint': ['Say hello in the box above', '在上面输入框里打个招呼吧'],
  'chat.load_earlier': ['Load earlier messages', '加载更早的消息'],
  'chat.fallback_peer': ['Conversation', '对话'],
};
