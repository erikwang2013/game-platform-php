/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 法语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { frFields } from './fr.fields.ts';
import { frUi } from './fr.ui.ts';

export const fr = { ...frUi, ...frFields };
