/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { ADMIN } from './dict/admin';
import { ANALYTICS } from './dict/analytics';
import { COLUMNS } from './dict/columns';
import { CONTENT } from './dict/content';
import { DASHBOARD } from './dict/dashboard';
import { FINANCE } from './dict/finance';
import { FRAME } from './dict/frame';
import { GAMES } from './dict/games';
import { INFRA } from './dict/infra';
import { LOGIN } from './dict/login';
import { MARKETING } from './dict/marketing';
import { RISK } from './dict/risk';
import { SETTINGS } from './dict/settings';
import { SUPPORT } from './dict/support';
import { USER } from './dict/user';

/**
 * 词条总表：一个域一个文件（`dict/*.ts`，形状 `键: [英文, 中文]`），这里只做汇总 ——
 * 一个文件塞下全部词条会顶破 500 行上限，所以按域拆。
 *
 * **重复键直接抛**，不静默覆盖：两个域文件写了同名键时，后者会悄悄把前者的译文顶掉，
 * 界面上看不出任何异常（只是某个页面文案不对），这种错必须当场炸出来。
 */
const PARTS: Record<string, [string, string]>[] = [
  FRAME,
  LOGIN,
  RISK,
  FINANCE,
  USER,
  ANALYTICS,
  DASHBOARD,
  INFRA,
  SUPPORT,
  SETTINGS,
  ADMIN,
  CONTENT,
  GAMES,
  MARKETING,
  COLUMNS,
];

const merged: Record<string, [string, string]> = {};
for (const part of PARTS) {
  for (const [key, pair] of Object.entries(part)) {
    if (key in merged) throw new Error(`i18n 词条重复：${key}`);
    merged[key] = pair;
  }
}

/** 键 → [en, zh]。空表是有意义的（查不到会回落键名本身），但真出现空表说明域文件没挂上 */
export const DICT: Record<string, [string, string]> = merged;
