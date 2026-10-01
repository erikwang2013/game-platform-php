/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 印地语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { hiFields } from './hi.fields.ts';
import { hiUi } from './hi.ui.ts';

export const hi = { ...hiUi, ...hiFields };
