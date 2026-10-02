/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 外壳域：侧栏 / 底部 tab / 顶栏 / 语言菜单。
 *
 * 形状 `键: [英文, 中文]`，键用 `域.名`（与外壳管端树 `admin/apps/angular` 的 `dict/*` 同构）。
 * **中文值逐字来自抽取前的源码**（`git show HEAD:apps/angular/src/app/app.ts` 与 `app.html`），
 * 不是重打的 —— 改这列就是改用户看到的文案，别顺手润色。
 */
export const SHELL: Record<string, [string, string]> = {
  // 侧栏 / 底部 tab 的五个主导航项（app.ts 的 NAV）
  'nav.home': ['Home', '首页'],
  'nav.games': ['Games', '游戏'],
  'nav.wallet': ['Wallet', '钱包'],
  'nav.messages': ['Messages', '消息'],
  'nav.me': ['Me', '我的'],

  // 语言菜单按钮的无障碍名（复用管理端树的键与 13 表译文：en/zh 两处逐字相同）
  'app.lang': ['Language', '语言'],

  // 顶栏 / 侧栏底部
  'app.logout': ['Logout', '退出登录'],
  'app.login': ['Log in', '登录'],
  'app.login_or_register': ['Log in / Register', '登录 / 注册'],

  // 搜索框（placeholder 与 aria-label 是两句：前者带省略号）
  'app.search_games_hint': ['Search games…', '搜索游戏…'],
  'app.search_games': ['Search games', '搜索游戏'],

  // 底部 tab 的无障碍名
  'app.main_nav': ['Main navigation', '主导航'],
  // —— C 批（friends 页）：三个动作/入口词，别处也会用到（逐字取自 react 同键）——
  'nav.friends': ['Friends', '好友'],
  'app.message': ['Message', '发消息'],
  'app.delete': ['Delete', '删除'],
  'app.search': ['Search', '搜索'],
};
