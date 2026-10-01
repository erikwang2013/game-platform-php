/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 德语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { deFields } from './de.fields.ts';
import { deUi } from './de.ui.ts';

export const de = { ...deUi, ...deFields };
