/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 印尼语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { idFields } from './id.fields.ts';
import { idUi } from './id.ui.ts';

export const id = { ...idUi, ...idFields };
