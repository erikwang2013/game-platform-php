/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 葡萄牙语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { ptFields } from './pt.fields.ts';
import { ptUi } from './pt.ui.ts';

export const pt = { ...ptUi, ...ptFields };
