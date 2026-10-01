/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 韩语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { koFields } from './ko.fields.ts';
import { koUi } from './ko.ui.ts';

export const ko = { ...koUi, ...koFields };
