/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 营销中心页词条（marketing.ts：优惠券 / VIP 等级）。
 *
 * 键名沿用 `admin/apps/flutter` 的 translations.dart —— 那边有 `coupon.*` 与 `vip.*` 两族同名页面，
 * 同名同义的（coupon.name / coupon.type / coupon.value / coupon.min_amount / coupon.max_discount /
 * coupon.game_id / coupon.total_qty / coupon.user_limit / coupon.start_at / coupon.end_at /
 * coupon.used_qty / vip.level / vip.name / vip.required_exp / vip.benefits / vip.benefits_hint …）
 * 逐字沿用那边的键名。中文一侧**逐字等于抽取前的界面原文**。
 *
 * ⚠ 三处与 flutter 不同文（按本树原文写）：
 *  - `coupon.type_fixed` / `coupon.type_rate`：本树的选项 label **把后端枚举值也印出来**
 *    （「fixed 固定金额」），flutter 那侧只有译名 ⇒ 本树把枚举值留在译文里；
 *  - `coupon.stats_title`：flutter 是「Coupon stats: {name}」带占位符，本树是抽屉的固定标题；
 *  - `coupon.delete_label`：flutter 的 `coupon.delete_confirm_target` 是**整句确认**，
 *    本树这个 label 只是 `crud.delete_confirm` 的 `{name}` 实参 ⇒ 另开一个键。
 */
export const MARKETING: Record<string, [string, string]> = {
  'marketing.subtitle': ['Coupons / VIP levels', '优惠券 / VIP 等级'],

  // ---- 优惠券 ----
  'coupon.noun': ['coupon', '优惠券'],
  'coupon.title': ['Coupons', '优惠券'],
  'coupon.name': ['Name', '券名'],
  'coupon.name_hint': ['Max 100 characters', '最长 100'],
  'coupon.type': ['Type', '类型'],
  /** 选项 label 里带着后端枚举值（fixed / rate），别把枚举抹掉 */
  'coupon.type_fixed': ['fixed (fixed amount)', 'fixed 固定金额'],
  'coupon.type_rate': ['rate (discount rate)', 'rate 折扣率'],
  'coupon.value': ['Value / rate', '面值 / 折扣率'],
  'coupon.value_hint': [
    'fixed = platform-token amount; rate = discount rate (0.10 = 10% off), > 0',
    'fixed=平台币金额；rate=折扣率（0.10 = 9 折），> 0',
  ],
  'coupon.min_amount': ['Min spend', '最低使用金额'],
  'coupon.min_amount_hint': ['0 = no threshold', '0 = 不限'],
  'coupon.max_discount': ['Max discount', '最大优惠金额'],
  'coupon.max_discount_hint': ['Rate type only; 0 = no cap', 'rate 类型有效；0 = 不封顶'],
  'coupon.game_id': ['Game', '适用游戏'],
  'coupon.total_qty': ['Total qty', '发放总量'],
  'coupon.total_qty_hint': ['0 = unlimited', '0 = 不限量'],
  'coupon.user_limit': ['Per-user limit', '每人限领'],
  'coupon.user_limit_hint': ['>= 1, defaults to 1', '≥ 1，默认 1'],
  'coupon.start_at': ['Start at', '开始时间'],
  'coupon.end_at': ['End at', '结束时间'],
  'coupon.time_hint': [
    'YYYY-MM-DD HH:MM:SS; empty = no bound / keep unchanged',
    'YYYY-MM-DD HH:MM:SS；留空 = 不限 / 不修改',
  ],
  'coupon.end_hint': ['Must not be earlier than the start time', '不得早于开始时间'],
  'coupon.used_qty': ['Claimed', '已领取'],
  /** 列头「面值/折扣率」与表单 label「面值 / 折扣率」的斜杠两侧空格不同 ⇒ 各留各的（逐字保真） */
  'coupon.head.value': ['Value / rate', '面值/折扣率'],
  /** 列头把枚举含义写进去（值原样显示） */
  'coupon.head.status': ['Status (0 disabled / 1 enabled)', '状态(0停用/1启用)'],
  /** 券状态筛选的下拉首项（值是空串 = 不过滤） */
  'coupon.all_status': ['All statuses', '全部状态'],
  /** 列表里 game_id 为 0/空时显示的名字 */
  'coupon.all_platforms': ['All platforms', '全平台'],
  'coupon.stats_title': ['Coupon stats', '优惠券统计'],
  'coupon.stats_loading': ['Loading stats…', '统计加载中…'],
  'coupon.stats_empty': ['No stats to show for this coupon', '该券暂无可展示统计'],
  'coupon.empty_hint': ['Note', '提示'],
  /** 删除确认里的对象标识：destroy 是**级联删除**（连全部领取记录一起删） */
  'coupon.delete_label': [
    '{name} | along with every user claim record',
    '{name}｜连同全部用户领取记录',
  ],
  /** 游戏表取失败时拼在字段 label 后面（不把整页打成错误态） */
  'coupon.games_failed': [
    '{name} — game list failed to load: {error}',
    '{name} —— 游戏列表加载失败：{error}',
  ],

  // ---- VIP 等级 ----
  'vip.noun': ['VIP level', 'VIP 等级'],
  'vip.title': ['VIP Level Management', 'VIP 等级'],
  'vip.level': ['Level', '等级'],
  'vip.level_hint': ['Integer >= 0', '≥ 0 的整数'],
  'vip.name': ['Name', '等级名称'],
  'vip.name_hint': ['Max 50 characters', '最长 50'],
  'vip.required_exp': ['Required EXP', '所需经验'],
  'vip.required_exp_hint': ['Integer >= 0', '≥ 0 的整数'],
  'vip.benefits': ['Benefits (JSON)', '权益（JSON 对象）'],
  'vip.benefits_hint': [
    '{"exchange_discount":0.05,"withdraw_fee_discount":0.1,"rate_bonus":0.02}',
    '{"exchange_discount":0.05,"withdraw_fee_discount":0.1,"rate_bonus":0.02}',
  ],
};
