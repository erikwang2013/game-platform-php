/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { t } from '../i18n/index.ts';
import { ApiError } from './api.ts';

/**
 * 注销页的判定逻辑单独放在 `.ts` 里，是为了能被钉住：本树测试脚本是
 * `node --experimental-strip-types --test`，glob 只收 `.ts` 测试文件，
 * 收不了 `.tsx`。判定留在 Me.tsx 里就是零覆盖，改坏了套件照样全绿。
 */

/** 服务端拒绝原因原样透出（如「请先提现所有余额后再注销账号」），不吞成「操作失败」 */
export function deleteErrorMessage(e: unknown): string {
  return e instanceof ApiError ? e.message : t('error.network');
}

/** 回读本身失败 ⇒ 注销结果无法判定，绝不当作成功 */
export function deleteUnknownMessage(e: unknown): string {
  return t('error.delete_unknown', {
    message: e instanceof ApiError ? e.message : t('error.network_short'),
  });
}

/** 回读结论：只有回读确认账号取不到（gone）才算注销成功；仍读得到 ⇒ 如实报告没注销掉 */
export function deleteVerdict(gone: boolean): { ok: boolean; message: string } {
  if (gone) return { ok: true, message: '' };
  return { ok: false, message: t('error.delete_still_readable') };
}
