/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 阿拉伯语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { arFields } from './ar.fields.ts';
import { arUi } from './ar.ui.ts';

export const ar = { ...arUi, ...arFields };
