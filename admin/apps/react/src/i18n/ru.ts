/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 俄语表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { ruFields } from './ru.fields.ts';
import { ruUi } from './ru.ui.ts';

export const ru = { ...ruUi, ...ruFields };
