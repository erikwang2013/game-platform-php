/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { COMMON } from './dict/common';
import { CONTENT } from './dict/content';
import { DEPOSIT } from './dict/deposit';
import { EXCHANGE } from './dict/exchange';
import { FRIENDS } from './dict/friends';
import { GAMEPLAY } from './dict/gameplay';
import { IDENTITY } from './dict/identity';
import { PROFILE } from './dict/profile';
import { SHELL } from './dict/shell';
import { TICKETS } from './dict/tickets';
import { WALLET } from './dict/wallet';
import { WITHDRAW } from './dict/withdraw';

/**
 * 词条总表：一个域一个文件（`dict/*.ts`，形状 `键: [英文, 中文]`），这里只做汇总 ——
 * 一个文件塞下全部词条会顶破 500 行上限，所以按域拆。
 *
 * **重复键直接抛**，不静默覆盖：两个域文件写了同名键时，后者会悄悄把前者的译文顶掉，
 * 界面上看不出任何异常（只是某个页面文案不对），这种错必须当场炸出来。
 *
 * ⚠ 跨域共用的词放 `dict/common.ts`，别各域各写一份 —— 同一个概念有 N 个键时，
 * 翻错一处从界面上看不出来，13 张表还要跟着同步 N 份。
 */
const PARTS: Record<string, [string, string]>[] = [
  SHELL,
  COMMON,
  CONTENT,
  WALLET,
  DEPOSIT,
  WITHDRAW,
  EXCHANGE,
  FRIENDS,
  IDENTITY,
  PROFILE,
  GAMEPLAY,
  TICKETS,
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
