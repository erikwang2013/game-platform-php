/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * `config/route.php` 的只读视图：用例拿它断言「界面上摆的按钮在路由表里真的有」。
 * 单独一个非测试文件，是因为模块组与风控组两组用例都要用 —— node --test 每个文件跑在独立进程里，
 * 从一个 *.test.ts 里 import 另一个，会把对方的用例在本进程再注册一遍（计数翻倍）。
 */
import { readFileSync } from 'node:fs';

export const routes = readFileSync(new URL('../../../../config/route.php', import.meta.url), 'utf8');

/** /admin/v1/x → /x（route.php 里的路径写在 `Route::group('/admin/v1')` 内，是相对段）。 */
export const rel = (path: string): string => path.replace('/admin/v1', '');

/** 路由表里有没有这个方法 + 路径（路径按上面归一成相对段再比）。 */
export const hasRoute = (method: string, path: string): boolean =>
  routes.includes(`Route::${method.toLowerCase()}('${rel(path)}'`);
