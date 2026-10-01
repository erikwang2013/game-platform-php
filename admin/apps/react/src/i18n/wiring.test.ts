/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { LANGUAGES } from './index.ts';

/**
 * ⚠ 本文件是**源码级**断言，不是行为级：本树没有 DOM 底座（`npm test` 是 `node --test`，
 * 无 jsdom / 无 testing-library / 未装 react 测试渲染器），拿不到真实渲染树，也拦不到 fetch。
 * 能钉住的只有「那段代码还在不在、还在不在正确的位置上」。
 *
 * 本仓已有同款先例：`src/components/rowBrowser.guard.test.ts`（读 RowBrowser.tsx 源码钉守卫调用点）
 * 与 `service/tests/GameSettlePayoutGuardTest`。为什么值得这么钉：**换语言的失败模式是静默的**——
 * 少发一个 `X-Language`，界面照样切、请求照样 200，只有服务端 message 永远是中文；
 * 切换器少一种语言，也只是那一项不出现。两者都不报错，光看测试全绿发现不了。
 */
const read = (rel: string): string => readFileSync(fileURLToPath(new URL(rel, import.meta.url)), 'utf8');

test('请求出口带 X-Language：三个 fetch 调用点逐个钉住（漏一个就有一种请求说错语言）', () => {
  const api = read('../lib/api.ts');
  // 主链路（业务请求）——**这条是本任务的成败点**：不发这个头，后端 LanguageMiddleware
  // 一律按默认 zh 选表，界面切了语言、服务端 message 也永远是中文
  assert.match(
    api,
    /const headers: Record<string, string> = \{ 'X-Language': currentCode\(\) \};/,
    'apiEnvelope 的 send() 里没有注入 X-Language',
  );
  // rawPost 与 refresh 各有自己的 send/fetch：它们同样打到带上限流与中间件的后端
  assert.equal(
    api.split("'X-Language': currentCode()").length - 1,
    3,
    'X-Language 的注入点不是 3 处（apiEnvelope / rawPost / refreshAccessToken）',
  );
  // 头里必须是**当前**语言，不能是常量：常量会让切换器形同虚设
  assert.match(api, /import \{ currentCode, t \} from '\.\.\/i18n\/index\.ts';/);
});

test('切换器：13 种平铺 + 母语名 + 当前项打点，且落值走 setCode（不是只改本地 state）', () => {
  const shell = read('../components/Shell.tsx');
  // 候选来自 LANGUAGES 全表 —— 写死成 3-5 项的菜单是旧 flutter 那个「13 种只能二选一」缺陷的翻版
  assert.match(shell, /\{LANGUAGES\.map\(\(item\) => \(/, '切换器没有遍历 LANGUAGES（不是平铺全表）');
  assert.match(shell, /\{item\.native\}/, '切换器没显示母语名');
  assert.match(shell, /\{item\.code === code \? '●' : ''\}/, '当前项没有打点');
  // 选中即持久化：落值必须经 i18n 的 setCode（写偏好 + 通知订阅者），
  // 只 setState 的话界面切了、下次进来又回去了，请求头也还是旧语言
  assert.match(shell, /setCode\(item\.code\);/, '切换器没有调 setCode（只改 state = 不落盘、请求头也不变）');
  // 布局层订阅语言：Shell 是整棵树的父级，它重渲染才能把页面里的 t() 一起带过去
  assert.match(shell, /const \{ code, setCode, t \} = useI18n\(\);/);
});

test('平铺清单与 i18n 的 LANGUAGES 同源：切换器不许自己再写一份语言表', () => {
  const shell = read('../components/Shell.tsx');
  assert.match(shell, /import \{ LANGUAGES, useI18n, type MessageKey \} from '\.\.\/i18n\/index\.ts';/);
  // 每一款的母语名都要能在切换器渲染出来（防「表里有 13 种、菜单只显示前几个」）
  for (const item of LANGUAGES) assert.ok(item.native.length > 0, `${item.code} 没有母语名`);
});
