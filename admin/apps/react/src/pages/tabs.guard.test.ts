/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

/**
 * ⚠ 源码级断言（同 `i18n/wiring.test.ts` 的理由：本树没有 DOM 底座，`npm test` 是 `node --test`
 * 不带 jsdom，拿不到真实渲染树）。钉的是 TabPage 渲染链里的一类**静默**缺陷：
 *
 * 分组项声明了自定义渲染标志（`withdrawLimits: true` 之类），但那条分支挂在它**没声明**的前置条件
 * 里面 ⇒ 分支永远不可达，页面悄悄落到兜底渲染（`Section`→`AutoView`：列是响应键序、表头露裸字段名、
 * 工具栏与行内动作整块消失），**不报错、请求照常 200**。本批真抓到一处：`withdrawLimits` 的
 * 分组项漏了 `list: true`，于是「全档位重置」按钮与整个 `WITHDRAW_LIMIT_CRUD`（单档 PUT）不可达。
 *
 * 判据是**可达性**，且前置条件**从渲染链里现推**，不写死映射表：把每个 `group.<标志> ?` 分支的
 * 位置与 `group.list ? (…)` 那条臂、`!group.path ?` 那个判定比大小，就知道它要求什么。渲染链改了，
 * 这里跟着改；新加一个自定义页签忘记声明前置条件，这里红。
 */
const src = readFileSync(fileURLToPath(new URL('./TabPage.tsx', import.meta.url)), 'utf8');

/** `type Group` 里声明成布尔的那些字段 —— 只有它们可能是渲染标志（path/tree/detailBase 是字符串）。 */
const typeBody = /type Group = \{([\s\S]*?)\n\};/.exec(src);
assert.ok(typeBody, 'TabPage.tsx 里找不到 type Group 的定义');
const declaredFlags = [...typeBody[1].matchAll(/^ {2}([A-Za-z_]\w*)\?: boolean;/gm)].map((m) => m[1]);
/** 这两个不是「渲染分支的标志」：list 是别处分支的**前置条件**，paged 只是传给 RowBrowser 的开关。 */
const NOT_A_BRANCH = new Set(['list', 'paged']);
const renderFlags = declaredFlags.filter((f) => !NOT_A_BRANCH.has(f));

/** 渲染链上的分支位置：`group.<标志> ?`。 */
const branches = new Map<string, number>();
for (const m of src.matchAll(/\bgroup\.([A-Za-z_]\w*)\s*\?/g)) {
  if (renderFlags.includes(m[1])) branches.set(m[1], m.index);
}

/** 从 `(` 配平到对应的 `)`（跳过字符串/模板串与注释）。 */
function armEnd(at: number): number {
  let depth = 0;
  for (let i = at; i < src.length; i += 1) {
    const c = src[i];
    if (c === '"' || c === "'" || c === '`') {
      for (i += 1; i < src.length && src[i] !== c; i += 1) if (src[i] === '\\') i += 1;
      continue;
    }
    if (c === '/' && src[i + 1] === '/') {
      i = src.indexOf('\n', i);
      if (i < 0) break;
      continue;
    }
    if (c === '/' && src[i + 1] === '*') {
      i = src.indexOf('*/', i) + 1;
      continue;
    }
    if (c === '(') depth += 1;
    else if (c === ')') {
      depth -= 1;
      if (depth === 0) return i;
    }
  }
  throw new Error('渲染链的括号不配平 —— 提取口径坏了');
}

/** `group.list ? (` 那条 true 臂的区间：落在里面的分支，分组项**必须**声明 `list: true` 才够得着。 */
const listAt = src.indexOf('group.list ?');
assert.ok(listAt > 0, '渲染链里找不到 group.list 分支');
const listArm = [src.indexOf('(', listAt), armEnd(src.indexOf('(', listAt))];
/** 排在它之前的 `!group.path ?`：没有 path 的分组项会被上一级截走，渲染成「我的账号」。 */
const pathAt = src.indexOf('!group.path ?');
assert.ok(pathAt > 0 && pathAt < listArm[0], '`!group.path` 的判定不在 group.list 之前，渲染链的顺序变了');

/** 包住这个下标的那个 `{ … }` —— 分组项对象字面量。 */
function entryAt(at: number): string {
  let start = -1;
  let depth = 0;
  for (let i = at; i >= 0; i -= 1) {
    if (src[i] === '}') depth += 1;
    else if (src[i] === '{') {
      if (depth === 0) {
        start = i;
        break;
      }
      depth -= 1;
    }
  }
  if (start < 0) return '';
  depth = 0;
  for (let j = start; j < src.length; j += 1) {
    if (src[j] === '{') depth += 1;
    else if (src[j] === '}') {
      depth -= 1;
      if (depth === 0) return src.slice(start, j + 1);
    }
  }
  return '';
}

const problems: string[] = [];
let seen = 0;
for (const flag of renderFlags) {
  const branchAt = branches.get(flag);
  if (branchAt === undefined) {
    problems.push(`${flag}：type Group 里声明了，渲染链里却没有任何 group.${flag} 分支（标志是死的）`);
    continue;
  }
  const hits = [...src.matchAll(new RegExp(`\\b${flag}:\\s*true\\b`, 'g'))];
  if (hits.length === 0) {
    problems.push(`${flag}：有渲染分支，但没有任何分组项声明它（那条分支永远不亮）`);
    continue;
  }
  for (const hit of hits) {
    seen += 1;
    const entry = entryAt(hit.index);
    const short = entry.replace(/\s+/g, ' ').slice(0, 90);
    if (!/label:/.test(entry)) problems.push(`${flag}：${short} —— 标志不在分组项对象里，提取口径坏了`);
    // ① 分支嵌在 list 臂里 ⇒ 该项必须 list: true，否则永远落不到这条分支
    if (branchAt > listArm[0] && branchAt < listArm[1]) {
      if (!/[{,]\s*list:\s*true\b/.test(entry)) {
        problems.push(`${flag}：${short} —— 分支在 group.list 的 true 臂里，本项没声明 list: true ⇒ 不可达`);
      }
    } else if (/[{,]\s*list:\s*true\b/.test(entry)) {
      problems.push(`${flag}：${short} —— 本项声明了 list: true，分支却在 list 臂之外 ⇒ 分支不可达`);
    }
    // ② 分支排在 !group.path 之后 ⇒ 没 path 的项会先被 AccountCard 截走
    if (branchAt > pathAt && !/\bpath:/.test(entry)) {
      problems.push(`${flag}：${short} —— 分支在 !group.path 之后，本项没声明 path ⇒ 被「我的账号」截走`);
    }
  }
}

test('页签渲染链：自定义标志与它分支的前置条件配对（漏声明 = 分支静默不可达）', () => {
  assert.ok(renderFlags.length >= 12, `type Group 只解析出 ${renderFlags.length} 个布尔标志 —— 提取口径坏了就是恒绿`);
  assert.ok(branches.size >= 12, `渲染链只认出 ${branches.size} 条 group.<标志> 分支`);
  assert.ok(seen >= 12, `只找到 ${seen} 处标志声明 —— 提取口径坏了`);
  assert.deepEqual(problems, [], '上面的每一行都是一条够不着的分支：补齐分组项的前置条件（或把分支挪到正确的臂里）');
});
