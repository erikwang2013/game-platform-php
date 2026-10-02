/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 页面注册表体检 —— 本树没有页面级测试框架（ArkTS 无 vitest/ng test 对应物），
 * 这四条静态断言是本树唯一的非 UI 钉子。用法：node scripts/check_pages.mjs
 *
 * 前三条是硬门（红即 exit 1），第四条是告警（读数字，不挡）。
 * 前三条正是 2026-10-02 删掉那个假页（WalletPage：余额恒恒 `--`、四个宫格 onClick 全空）
 * 时顺手能立起来的形状：文件/注册漂移、导航指向没注册的页、点了没反应的死控件。
 *
 * 第四条（可达性）是**告警**不是硬门 —— 报出来但不挡门，免得一条已知项把整颗钉子变成常年红。
 * 它此前长期报 1：GameHallPage 全树零入边（GameDetailPage 只被 GameHallPage push）。
 * 定性是**入边漏写**、不是死代码：后端 `/admin/v1/game/launch` 的 apidoc 写明是「管理端试玩入口」、
 * `/admin/v1/game/{hashid}` 写明「供管理端客户端游戏详情页使用」，且 `git log -S pages/GameHallPage`
 * 显示本树**从建树起**就没有过入口。2026-10-02 由 ProfilePage 补上入口后归零（导航边 34 → 35）。
 */
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { dirname, resolve, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const etsDir = resolve(root, 'entry/src/main/ets');
const pagesDir = resolve(etsDir, 'pages');
const manifestPath = resolve(root, 'entry/src/main/resources/base/profile/main_pages.json');

const fail = [];
const warn = [];
const read = (p) => readFileSync(p, 'utf8');
/** 去掉所有空白再匹配：ArkTS 里 `(() => {})` 的换行/缩进形态太多，逐字节对不现实 */
const squeeze = (s) => s.replace(/\s+/g, '');

const manifest = JSON.parse(read(manifestPath)).src;
const files = readdirSync(pagesDir).filter((f) => f.endsWith('.ets')).map((f) => resolve(pagesDir, f));
const sources = new Map(files.map((p) => [basename(p, '.ets'), read(p)]));

// ① 注册表 → 盘：每一页都要有 .ets，且里面有同名 struct（@Entry 收的正是这个名字）
for (const entry of manifest) {
  const name = entry.replace(/^pages\//, '');
  const src = sources.get(name);
  if (!src) {
    fail.push(`main_pages.json 注册了 ${entry}，但 ${entry}.ets 不存在`);
  } else if (!new RegExp(`\\bstruct\\s+${name}\\b`).test(src)) {
    fail.push(`${entry}.ets 里没有 struct ${name}（@Entry 收不到这个名字）`);
  }
}

// ② 盘 → 注册表：每一处字面量导航目标都必须是注册过的页（指向没注册的页 = 点进去白屏）
const navTargets = new Map(); // 页名 -> 它 push/replace 到的页名集合
for (const [name, src] of sources) {
  const targets = new Set();
  for (const m of src.matchAll(/(?:push|replace)Url\(\{[\s\S]{0,80}?url:\s*'pages\/([A-Za-z0-9_]+)'/g)) {
    targets.add(m[1]);
  }
  navTargets.set(name, targets);
  for (const t of targets) {
    if (!manifest.includes(`pages/${t}`)) {
      fail.push(`${name}.ets 导航到 pages/${t}，但它不在 main_pages.json 里`);
    }
  }
}

// ③ 死控件：`onClick(() => {})` 空实现 —— 假页 WalletPage 的四个宫格就是这个形状。
//    真要做无副作用的占位，写一句提示或加墓碑注释，别留个能按但没反应的控件。
const emptyClick = 'onClick(()=>{})';
for (const [name, src] of sources) {
  const n = squeeze(src).split(emptyClick).length - 1;
  if (n > 0) {
    fail.push(`${name}.ets 有 ${n} 处空的 onClick(() => {})（点了没反应的死控件）`);
  }
}

// ④ 可达性（告警）：从首屏（main_pages.json[0]）沿导航边 BFS，走不到的注册页 = 死页
const entryPage = manifest[0].replace(/^pages\//, '');
const seen = new Set([entryPage]);
for (const queue = [entryPage]; queue.length > 0; ) {
  for (const t of navTargets.get(queue.shift()) ?? []) {
    if (!seen.has(t)) {
      seen.add(t);
      queue.push(t);
    }
  }
}
const orphans = manifest.map((e) => e.replace(/^pages\//, '')).filter((p) => !seen.has(p));
if (orphans.length > 0) {
  warn.push(`从 ${entryPage} 走不到的注册页（无入边）：${orphans.join(', ')}`);
}

for (const w of warn) {
  console.log(`WARN  ${w}`);
}
for (const f of fail) {
  console.log(`FAIL  ${f}`);
}
console.log(
  `${fail.length === 0 ? 'OK' : 'FAIL'}  注册页 ${manifest.length} / 盘上页 ${files.length}` +
    ` / 导航边 ${[...navTargets.values()].reduce((a, s) => a + s.size, 0)} / 告警 ${warn.length}`
);
process.exit(fail.length === 0 ? 0 : 1);
