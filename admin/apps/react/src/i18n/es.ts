/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 西班牙语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { esFields } from './es.fields.ts';
import { esUi } from './es.ui.ts';

export const es = { ...esUi, ...esFields };
