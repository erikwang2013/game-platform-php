/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 英文表**合并点** —— **本文件即键的真值面**：`MessageKey = keyof typeof en`。
 *
 * 全表拆两个文件只是 500 行的项目约定（字段描述那一族 ~400 条）：
 * - `en.ui.ts` 界面文案（页面/组件里的 `t()`）
 * - `en.fields.ts` 数据描述（`pages/modules.ts` 的字段 label/placeholder/hint/选项）
 *
 * **两族的键必须互不相交**：`...` 展开时重名是后者静默覆盖前者，看不出错，
 * 所以有一条用例专门断言交集为空（见 i18n.test.ts）。
 */
import { enFields } from './en.fields.ts';
import { enUi } from './en.ui.ts';

export const en = { ...enUi, ...enFields };

/** 文案键。`t()` 的入参、`Field.label`、`CrudConfig.label` 一律用它 —— **键写错是编译期报错**。 */
export type MessageKey = keyof typeof en;
