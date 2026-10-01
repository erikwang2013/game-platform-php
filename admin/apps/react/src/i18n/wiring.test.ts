/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
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

/* ================================================================================================
 * 列标题的兜底链
 * ============================================================================================== */

/**
 * RowBrowser 解析表头是**三级链**：① 模块声明的 `fields[].label` / `editFields[].label`（权威）
 * → ② `f.<字段名>`（`fieldLabelKey`）→ ③ **原样露裸字段名**（`columnsFrom` 的 `labels[key] ?? key`）。
 *
 * 第 ③ 级是静默的：本批漏掉的四个表头就是这么来的（提现订单的 `order_no` / `platform_amount` /
 * `fiat_amount` / `payout_status` 一直是裸名，界面上照常出数、请求照常 200）。`i18n.test.ts` 那条
 * 「13 张表键集相同」的断言看不见这一类 —— 它管的是 13 张表**互相**齐不齐，不管**某个列清单要的
 * 键**在不在表里：13 张表一起缺同一个键时它全绿。
 *
 * 所以这里反过来读源码：把**显式声明的列清单**全捞出来，逐键问「表里有 `f.<键>` 吗，或者同一个
 * 元素 / 同一项的 `crud` 声明了这个字段吗」。只认三种写法（数组字面量、指向同文件字符串常量的
 * 标识符、`X.PROP` 指向同文件对象数组里的某个数组）；认不出来的写法要么落进 `aliases`（下面那条
 * 封闭断言会逼你来看一眼），要么让 `lists` / `slots` 的计数下限变红 —— 宁可吵，不可静默漏。
 */
const SRC = fileURLToPath(new URL('../', import.meta.url));
const keysOf = (body: string): string[] => [...body.matchAll(/'([A-Za-z0-9_]+)'/g)].map((m) => m[1]);

/** 「同一个元素 / 同一个 groups 项」的范围：在标签的属性表里就到 `/>`，否则按大括号配平。 */
function elementSpan(src: string, at: number): string {
  const lt = src.lastIndexOf('<', at);
  const gt = lt < 0 ? -1 : src.indexOf('>', lt);
  if (lt >= 0 && gt > at) {
    const end = src.indexOf('/>', at);
    return src.slice(lt, end < 0 ? at + 2000 : end + 2);
  }
  const start = src.lastIndexOf('{', at);
  if (start < 0) return src.slice(at, at + 800);
  let depth = 0;
  for (let j = start; j < src.length && j - start < 2000; j += 1) {
    if (src[j] === '{') {
      depth += 1;
    } else if (src[j] === '}') {
      depth -= 1;
      if (depth === 0) return src.slice(start, j + 1);
    }
  }
  return src.slice(start, start + 2000);
}

const enKeys = new Set([...read('en.fields.ts').matchAll(/^ {2}'(f\.[A-Za-z0-9_]+)':/gm)].map((m) => m[1]));
assert.ok(enKeys.size > 400, `en.fields.ts 只解析出 ${enKeys.size} 个 f.* 键 —— 提取口径坏了`);

/** 模块里 `fields:` / `editFields:` 指向的字段名 —— 兜底链第 ① 级，有它就不必补 `f.<键>`。 */
const moduleSrc = read('../pages/modules.ts');
const declared = new Map<string, string[]>();
for (const m of moduleSrc.matchAll(/const\s+([A-Za-z_]\w*)\s*(?::[^=]+?)?=\s*\[([\s\S]*?)\];/g)) {
  declared.set(m[1], [...m[2].matchAll(/name:\s*'([A-Za-z0-9_]+)'/g)].map((x) => x[1]));
}
function crudFields(name: string): Set<string> {
  const def = new RegExp(`const\\s+${name}\\s*(?::[^=]+?)?=\\s*\\{([\\s\\S]*?)\\n\\};`).exec(moduleSrc);
  const out = new Set<string>();
  if (!def) return out;
  for (const f of def[1].matchAll(/(?:editFields|fields)\s*:\s*(?:([A-Za-z_]\w*)|\[([\s\S]*?)\])/g)) {
    const names = f[1] ? (declared.get(f[1]) ?? []) : [...(f[2] ?? '').matchAll(/name:\s*'([A-Za-z0-9_]+)'/g)].map((x) => x[1]);
    for (const n of names) out.add(n);
  }
  return out;
}

/** 同一元素/同一项里写的 `crud={X}` / `crud: X`；`crud={f(x)}` 这种解析不出来，记 '?'。 */
function crudAt(src: string, at: number): string | null {
  const seg = elementSpan(src, at);
  const m = /crud\s*[:=]\s*\{?\s*([A-Za-z_]\w*)\s*\}?/.exec(seg);
  if (!m) return /\bcrud\s*[:=]/.test(seg) ? '?' : null;
  return /^\s*\(/.test(seg.slice(m.index + m[0].length)) ? '?' : m[1];
}

/** 列清单是给 JSX 的 prop；`.ts` 里的 `preferred` 是形参/类型标注，不是清单。 */
const tsx = readdirSync(SRC, { recursive: true, encoding: 'utf8' })
  .filter((rel) => rel.endsWith('.tsx') && !rel.endsWith('.test.tsx')).sort();

const lists: { rel: string; kind: string; keys: string[]; crud: string | null }[] = [];
const aliases: string[] = [];
for (const rel of tsx) {
  const src = readFileSync(SRC + rel, 'utf8');
  const consts = new Map<string, string[]>();
  const objArrays = new Map<string, string[][]>();
  for (const m of src.matchAll(/const\s+([A-Za-z_]\w*)\s*(?::[^=]+?)?=\s*\[([\s\S]*?)\];/g)) {
    if (m[2].includes('{')) {
      for (const p of m[2].matchAll(/([A-Za-z_]\w*)\s*:\s*\[([^\]]*)\]/g)) {
        const k = `${m[1]}.${p[1]}`;
        objArrays.set(k, [...(objArrays.get(k) ?? []), keysOf(p[2])]);
      }
    } else {
      consts.set(m[1], keysOf(m[2]));
    }
  }
  for (const m of src.matchAll(/preferred\s*=\s*\{\s*\[([\s\S]*?)\]|preferred\s*:\s*\[([\s\S]*?)\]/g)) {
    const keys = keysOf(m[1] ?? m[2]);
    if (keys.length > 0) lists.push({ rel, kind: '字面量', keys, crud: crudAt(src, m.index) });
  }
  for (const m of src.matchAll(/preferred\s*=\s*\{\s*([A-Za-z_]\w*)(?![\w.])\s*\}?|preferred\s*:\s*([A-Za-z_]\w*)(?![\w.])/g)) {
    const name = m[1] ?? m[2];
    if (consts.has(name)) lists.push({ rel, kind: `const ${name}`, keys: consts.get(name) ?? [], crud: crudAt(src, m.index) });
    else aliases.push(`${rel}:preferred:${name}`);
  }
  for (const m of src.matchAll(/preferred\s*=\s*\{\s*[A-Za-z_]\w*\.([A-Za-z_]\w*)\s*\}/g)) {
    const hit = [...objArrays].filter(([k]) => k.endsWith(`.${m[1]}`));
    if (hit.length === 0) aliases.push(`${rel}:preferred:*.${m[1]}`);
    for (const [, arrays] of hit) for (const keys of arrays) lists.push({ rel, kind: `*.${m[1]}`, keys, crud: crudAt(src, m.index) });
  }
}

let slots = 0;
const gaps: { rel: string; kind: string; key: string; crud: string | null }[] = [];
for (const list of lists) {
  for (const key of list.keys) {
    slots += 1;
    if (enKeys.has(`f.${key}`)) continue;
    if (list.crud !== null && list.crud !== '?' && crudFields(list.crud).has(key)) continue;
    gaps.push({ rel: list.rel, kind: list.kind, key, crud: list.crud });
  }
}

test('列标题兜底链：列清单里的键要么有 f.<键>、要么被同元素的 crud 声明（露裸名是静默的）', () => {
  assert.ok(tsx.length >= 25, `只扫到 ${tsx.length} 个 .tsx —— 路径或过滤写错就是恒绿`);
  assert.ok(lists.length >= 30, `只认出 ${lists.length} 处显式列清单（少于此数说明提取口径漏了形状）`);
  assert.ok(slots >= 200, `只认出 ${slots} 个键位`);
  assert.deepEqual(
    [...new Set(aliases)].sort(),
    ['pages/TabPage.tsx:preferred:*.preferred'],
    '运行期才决定的 preferred 清单变了：静态看不见它，确认新写法要不要补进上面三种形状',
  );
  // 无条件全绿：**没有账本**。本批把既存的 8 个裸名列（风控四页 + 搜索结果页）连同词条一起补齐后，
  // 这里是一条真断言 —— 任何列表再冒出一个裸列，无论新旧都红（变异读数见交付说明）。
  assert.deepEqual(
    gaps.map((g) => `${g.rel} [${g.kind}] ${g.key}（crud=${g.crud ?? '无'}）`),
    [],
    '这些列会露出裸字段名：补 f.<键> 词条，或让该模块的 crud 声明这个字段（RowBrowser 兜底链第 ① 级）',
  );
});
