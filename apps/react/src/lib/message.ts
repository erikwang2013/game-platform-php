/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiError } from './api.ts';
import { t, type MessageKey } from '../i18n/index.ts';

/**
 * 操作反馈的两态存放形状：**存键 + 参数，或存错误对象，绝不存翻好的串**。
 *
 * ⚠ 存翻好的串会把语言冻在那一刻 —— 切完语言这一行还是旧语言（同 `Wallet.tsx` 的 `Row` 注释）。
 * `msgText()` 在**渲染期**调，所以跟得上语言。
 *
 * 单独成文件而不是放在用它的页面里：`Me.tsx`（昵称块）与 `MePanels.tsx`（导出块）都要用，
 * 而那两个文件都得保持「只导出组件」（oxlint 的 `react(only-export-components)`）。
 */
export type Msg =
  | { ok: boolean; key: MessageKey; params?: Record<string, string | number> }
  | { ok: false; err: unknown };

export const msgText = (m: Msg, fallback: MessageKey): string =>
  'err' in m ? (m.err instanceof ApiError ? m.err.message : t(fallback)) : t(m.key, m.params);
