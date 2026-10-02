/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 本语言的合并点，与 `en.ts` 同构（同样只为 500 行约定而拆）。
 *
 * 拆开只是 500 行的项目约定：每个语言码 3 个文件（本 barrel + 下面两族）。
 * - `ar.common.ts` 跨页共享的骨架（导航/通用动作/错误兜底/验证码/上传/导出/流水类型）
 * - `ar.pages.ts`  各页文案（`home.*`、`wallet.*` …）
 *
 * **两族的键必须互不相交**：`...` 展开时重名是后者静默覆盖前者、看不出错，
 * 故 `i18n.test.ts` 钉着交集为空。
 *
 * ⚠ 本文件由生成器从一份数据集产出，**别在这里手改** —— 下次生成会把改动冲掉。
 */
import { arCommon } from './ar.common.ts';
import { arPages } from './ar.pages.ts';

export const ar = { ...arCommon, ...arPages };
