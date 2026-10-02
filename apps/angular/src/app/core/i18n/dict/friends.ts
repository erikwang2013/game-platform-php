/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 好友域（`pages/friends.ts`）：好友列表 / 收到的申请 / 搜人添加。
 *
 * 除末尾两条**本树独有**的（添加页没搜之前的空态 —— 那个状态下 react 树什么都不渲染）外，
 * 全部逐字取自 react 树同格的 `friends.*` / `app.*`。两个动作提示带 `{name}` 参数：
 * 名字是用户 id 之外的东西，**不能在源码里拼接**（拼了就成了存下来的成文）。
 *
 * ⚠ `friends.tab_list` / `friends.tab_requests` 的 `{n}` 参数传的是**带前导空格的计数串或空串**
 * （react 同形）：计数直接贴在词后，没有空格语言会糊成一个词。
 */
export const FRIENDS: Record<string, [string, string]> = {
  'friends.tab_list': ['Friends{n}', '好友{n}'],
  'friends.tab_requests': ['Requests{n}', '申请{n}'],
  'friends.tab_add': ['Add', '添加'],
  'friends.empty_title': ['No friends yet', '还没有好友'],
  'friends.empty_hint': ['Use the Add tab to search by username', '去「添加」按用户名搜人'],
  'friends.req_empty_title': ['No pending requests', '没有待处理的申请'],
  'friends.req_empty_hint': ['Requests appear here when someone adds you', '别人加你时会出现在这里'],
  'friends.accept': ['Accept', '接受'],
  'friends.reject': ['Decline', '拒绝'],
  'friends.search_placeholder': ['Search by username or nickname', '按用户名或昵称搜索'],
  'friends.searching': ['Searching…', '搜索中…'],
  'friends.no_hit_title': ['No matching users', '没有找到匹配的用户'],
  'friends.no_hit_hint': ['Try another username or nickname', '换个用户名或昵称试试'],
  'friends.already': ['Already friends', '已是好友'],
  'friends.add': ['Add friend', '加好友'],
  'friends.add_idle_title': ['Find people to add', '搜人加好友'],
  'friends.add_idle_hint': ['Enter a username or nickname, then search', '输入用户名或昵称后点搜索'],
  'friends.accepted': ['Accepted {name}', '已接受 {name}'],
  'friends.rejected': ['Declined {name}', '已拒绝 {name}'],
  'friends.removed': ['Removed {name}', '已删除好友 {name}'],
  'friends.sent': ['Request sent to {name}', '已向 {name} 发送申请'],
  // —— 邀请好友（`pages/invite.ts`）。`invite.title` 同时是**活动类型名**「邀请好友」的文案
  //    （`ACTIVITY_TYPE_LABEL.invite` 指的就是这个功能）—— 同一个概念只留一个键 ——
  'invite.title': ['Invite friends', '邀请好友'],
  'invite.hint_create': [
    'Generate an invite code and send it to a friend. The invite only counts as a conversion after they open the link and register with it.',
    '生成一个邀请码发给朋友。对方打开链接、用它注册之后，这次邀请才会计入转化。',
  ],
  'invite.generating': ['Generating…', '生成中…'],
  'invite.generate': ['Generate invite code', '生成邀请码'],
  'invite.code_label': ['Invite code', '邀请码'],
  'invite.copy_code': ['Copy code', '复制码'],
  'invite.link_label': ['Invite link', '邀请链接'],
  'invite.copy_link': ['Copy link', '复制链接'],
  'invite.hint_share': [
    'Send the link to a friend. It takes them straight to the sign-up page with this code filled in; a completed registration counts as one conversion.',
    '把链接发给朋友。对方打开后会自动跳到注册页并填好这个码，注册成功即完成一次转化。',
  ],
  'invite.again': ['Generate another one', '再生成一个'],
};
