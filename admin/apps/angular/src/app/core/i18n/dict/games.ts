/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 游戏管理页词条（games.ts：游戏列表 / 游戏分类 / 区服）。
 *
 * 键名沿用 `admin/apps/flutter` 的 translations.dart —— 那边有 `game.*`、`game_category.*`、
 * `game_server.*` 三族同名页面，同名同义的（game.name / game.slug / game.type / game.self /
 * game.embedded / game.third_party / game.region / game.sort / game.sdk_version / game.cover_image /
 * game.api_endpoint / game.description / game_category.slug_hint / game_server.region_hint /
 * game_server.status_maintenance …）逐字沿用那边的键名。
 *
 * ⚠ 两处按本树原文写（那边同键不同文）：
 *  - `game.status`：flutter 的 hint 是 `game.region_hint`/`game.slug_hint` 一族，status 只是列头；
 *    本树这个键是表单字段名「上架状态」；
 *  - `game.api_key` / `game.api_secret`：flutter 那边没有对应键，本树自建，提示语按本树原文。
 * 中文一侧**逐字等于抽取前的界面原文**。
 */
export const GAMES: Record<string, [string, string]> = {
  /** 页头用侧栏那条（同一个页面名，不另开键），副标题是本页自有的 */
  'game.subtitle': ['Games / Categories / Servers', '游戏 / 分类 / 区服'],
  'game.search_hint': ['Game name / slug', '游戏名 / 标识'],

  // ---- 游戏列表 ----
  'game.noun': ['game', '游戏'],
  'game.title': ['Game Management', '游戏列表'],
  'game.name': ['Name', '游戏名称'],
  'game.name_hint': ['Max 100 characters', '最长 100'],
  'game.slug': ['Slug', '游戏标识'],
  'game.slug_hint': ['lowercase letters / digits / _ / -, max 50 characters', '小写字母/数字/_/-，最长 50'],
  'game.type': ['Type', '游戏类型'],
  'game.self': ['Self-developed', '自研'],
  'game.embedded': ['Embedded', '内嵌'],
  'game.third_party': ['Third-party', '第三方'],
  'game.platform': ['Platform', '平台'],
  /** 同组的 H5/Unity/Web 三个选项是**后端枚举原文**，不译，所以只登记这一个中文项 */
  'game.platform_native': ['Native', '原生'],
  'game.region': ['Region', '地区'],
  'game.region_hint': ['e.g. global, max 10 characters', '如 global，最长 10'],
  'game.status': ['Status', '上架状态'],
  'game.sort': ['Sort', '排序'],
  'game.sort_hint': ['Smaller comes first', '数字越小越靠前'],
  'game.sdk_version': ['SDK Version', 'SDK 版本'],
  'game.sdk_version_hint': ['Max 20 characters', '最长 20'],
  'game.cover_image': ['Cover Image', '封面图'],
  'game.cover_image_hint': ['Image URL, max 255 characters', '图片 URL，最长 255'],
  'game.api_endpoint': ['API Endpoint', 'API 端点'],
  'game.api_endpoint_hint': ['Max 255 characters', '最长 255'],
  'game.api_key': ['API Key', 'API Key'],
  'game.api_key_hint': ['Empty on edit = keep unchanged', '编辑时留空 = 不修改'],
  'game.api_secret': ['API Secret', 'API Secret'],
  'game.api_secret_hint': [
    'Empty on edit = keep unchanged; self-developed / embedded generate one automatically when empty',
    '编辑时留空 = 不修改；自研/内嵌留空自动生成',
  ],
  'game.description': ['Description', '游戏描述'],
  /**
   * 行内动作「游戏币种」（POST /game/currency/manage）。三条语义原文照抄 react 的
   * `f.game_currencies_hint`：不写进来的不删、带 id 才是改、单条不过整批拒绝 ——
   * 少写一条提示，运营就会把它当成整表替换，一次提交把币种表的 id 全抹掉变成重复行。
   */
  'game.currency_act': ['Game currencies', '游戏币种'],
  'game.currency_hint': [
    'A JSON array; keep id to update a row, drop it to create a new one. Currencies you leave out are kept as they are - this is not a full replace. name/symbol text, exchange_rate > 0, spread_pct between 0 and 100.',
    'JSON 数组；带 id = 改这一条，去掉 id = 新建一条。没写进来的币种原样保留 —— 这不是整表替换。name/symbol 为文本，exchange_rate 需大于 0，spread_pct 在 0 到 100 之间。',
  ],

  // ---- 游戏分类 ----
  'game_category.noun': ['category', '分类'],
  'game_category.title': ['Game Categories', '游戏分类'],
  'game_category.name': ['Name', '分类名称'],
  'game_category.name_hint': ['Max 50 characters', '最长 50'],
  'game_category.slug': ['Slug', '分类标识'],
  'game_category.slug_hint': ['lowercase letters / digits / _ / -, max 50 characters', '小写字母/数字/_/-，最长 50'],
  'game_category.icon': ['Icon', '图标'],
  'game_category.icon_hint': ['Image URL or icon name, max 255 characters', '图片 URL 或图标名，最长 255'],
  'game_category.sort': ['Sort', '排序'],
  'game_category.sort_hint': ['Smaller comes first', '数字越小越靠前'],
  /**
   * 行内动作「分配游戏」（POST /game/category/assign，`{category_id, game_ids[]}`）。
   * **整体替换**：后端先删光该分类的关联再插入 —— 分类侧没有任何读端点能拿回当前关联
   * （GameCategoryController 只有 list/create/update/destroy/assign），所以表单只能空白开局，
   * 提示里必须把「没列的会被解绑、留空即清空」说死（原文照抄 react 的 `f.from_the_games_list_id`）。
   */
  'game_category.assign_games': ['Assign Games', '分配游戏'],
  'game_category.assign_title': ['Assign games to this category', '分配游戏到分类'],
  'game_category.assign_placeholder': ['One per line', '每行一个'],
  'game_category.assign_hint': [
    'From the games list id column; this submission replaces the whole association set of the category (it does not append)',
    '取自游戏列表的 id 列；本次提交整体替换该分类的关联（不是追加）',
  ],

  // ---- 区服 ----
  'game_server.noun': ['server', '区服'],
  'game_server.title': ['Game Servers', '区服'],
  'game_server.game_id': ['Game', '所属游戏'],
  'game_server.game_id_hint': [
    'Game hashid (copy it from the "Game Management" tab)',
    '游戏 hashid（从「游戏列表」标签页复制）',
  ],
  'game_server.name': ['Name', '区服名称'],
  'game_server.name_hint': ['Max 50 characters', '最长 50'],
  'game_server.region': ['Region', '所属区域'],
  'game_server.region_hint': ['e.g. global/asia/eu/na, max 20 characters', '如 global/asia/eu/na，最长 20'],
  /** 4 值枚举（0 维护 / 1 正常 / 2 火爆 / 3 新服）⇒ select，不是 0/1 开关 */
  'game_server.status': ['Status (empty = keep unchanged)', '区服状态（留空 = 不改）'],
  'game_server.status_maintenance': ['Maintenance', '维护'],
  'game_server.status_normal': ['Normal', '正常'],
  'game_server.status_hot': ['Hot', '火爆'],
  'game_server.status_new': ['New', '新服'],
  'game_server.sort': ['Sort', '排序'],
  'game_server.sort_hint': ['Smaller comes first', '数字越小越靠前'],

  /** 区服列表按游戏查（game_id 是 required）⇒ 没填时**不发请求**，空态里直说 */
  'game_server.game_id_filter': ['Game hashid (the server list is queried per game)', '游戏 hashid（区服列表按游戏查）'],
  'game_server.need_game_id': ['Enter a game hashid above first', '先在上面填游戏 hashid 再查询'],
};
