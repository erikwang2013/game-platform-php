/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 孟加拉语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { bnFields } from './bn.fields.ts';
import { bnUi } from './bn.ui.ts';

export const bn = { ...bnUi, ...bnFields };
