/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 日语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { jaFields } from './ja.fields.ts';
import { jaUi } from './ja.ui.ts';

export const ja = { ...jaUi, ...jaFields };
