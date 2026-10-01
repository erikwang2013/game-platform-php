/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * `/user/export-data`（GDPR 数据可携带）的响应形状。
 * 2026-10-01 从 types.ts 拆出：那份已 469 行，再加这一域会破 500。
 * types.ts 顶部 `export type * from './types.export.ts'` 把它并回同一出口，消费者 import 不用改。
 *
 * 四类明细是控制器 `UserController::exportData:134-153` 里 `->toArray()` 的**原样行**（模型全列），
 * 所以这里只写 `Record<string, unknown>[]` —— 页面**不解读列名**，只计数并原样落盘。
 * 编一份列名清单只会与真表悄悄漂开（列变了没人会想起来改这里）。
 */

export interface ExportProfile {
  username: string;
  nickname: string | null;
  email: string | null;
  phone: string | null;
  country: string | null;
  language: string | null;
  created_at: string | null;
}

export interface ExportWallet {
  /** 三个都是字符串金额（模型上是 decimal cast），**不要 parseFloat** */
  balance: string;
  total_earned: string;
  total_spent: string;
}

export interface ExportOAuthAccount {
  provider: string;
  created_at: string | null;
}

export interface ExportData {
  profile: ExportProfile;
  /** 无钱包行时服务端回 `null`（`$user->wallet ? … : null`）⇒ 页面要能显示「—」 */
  wallet: ExportWallet | null;
  /**
   * ⚠ 以下四类**各最多 100 条**（控制器逐个 `->limit(100)`），不是全量 ——
   * 页面上必须说明，否则用户会以为导出的就是全部历史。
   */
  transactions: Record<string, unknown>[];
  exchange_records: Record<string, unknown>[];
  deposit_orders: Record<string, unknown>[];
  withdraw_orders: Record<string, unknown>[];
  oauth_accounts: ExportOAuthAccount[];
  /** 服务端墙钟串 `Y-m-d H:i:s`（控制器 `date(...)`），落盘文件名的唯一来源 */
  exported_at: string;
}
