/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/** 中文表合并点，与 `en.ts` 同构（拆两族同样只为 500 行约定）。 */
import { zhFields } from './zh.fields.ts';
import { zhUi } from './zh.ui.ts';

export const zh = { ...zhUi, ...zhFields };
