/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

/**
 * 残留中文的**棘轮**：本树的可译文案一律走 i18n（键在 `i18n/*.ui.ts` / `*.fields.ts`），
 * 源码里不该再有硬编码中文。抽干净之后没人拦着，下一次「顺手写个字面量」就会静悄悄退回去，
 * 故把「当前为 0」这件事本身钉成用例。
 *
 * 两个口径（与抽检脚本 /tmp/react-i18n/coverage.mjs 同一套规则，报告的 before/after 就是它跑的）：
 * - `lit`：含汉字的**字符串字面量**（单/双/模板引号）条数。
 * - `outside`：剥掉注释与字符串之后仍含汉字的行数 —— 兜住字面量口径的盲区（JSX 文本 `重试`、
 *   `共 {total} 条` 这类根本不是字面量的，`lit` 抓不到，实测正是它先抓出 5 处）。
 *
 * 排除项与理由：
 * - `*.test.ts(x)`：用例里的中文是**测试数据**（`'贪吃蛇'`、行名），不是界面文案；断言界面文案的
 *   那些已改成断言键或译文，不需要这条棘轮再管一遍。
 * - `i18n/**`：译文表本身就是中文的落点；`languages.ts` 里的 13 个母语名（`简体中文`/`日本語`…）
 *   是**语言清单**不是待译内容 —— 翻成「Chinese」等于让看不懂当前界面的用户选不对。
 *
 * ponytail: 手写扫描器。注释只按两条正则剥（双斜线到行尾、块注释引号对），字符串里的双斜线
 * 会被误当行注释、模板串里的反引号也不认嵌套 —— 本树没有这两种写法（写了会在这里报红而不是静默漏）。
 * 要更严谨得上真 parser，等真出现误报再说。
 */
const SRC = fileURLToPath(new URL('../', import.meta.url));
const LITERAL = /'(?:[^'\\\n]|\\.)*'|"(?:[^"\\\n]|\\.)*"|`(?:[^`\\]|\\.)*`/g;
const HAN = /[一-鿿]/;
/** 整段换成等长空白（保留换行）：删掉的话后面的行号与 `outside` 的行切分就全错位了。 */
const blank = (match: string): string => match.replace(/[^\n]/g, '');

const offenders: string[] = [];
let litTotal = 0;
let outsideTotal = 0;
for (const rel of readdirSync(SRC, { recursive: true, encoding: 'utf8' }).sort()) {
  if (!/\.tsx?$/.test(rel) || /\.test\.tsx?$/.test(rel)) continue;
  if (rel.startsWith('i18n/') || rel.startsWith('i18n\\')) continue;
  const src = readFileSync(`${SRC}${rel}`, 'utf8')
    .replace(/\/\*[\s\S]*?\*\//g, blank)
    .replace(/\/\/[^\n]*/g, '');
  const lit = (src.match(LITERAL) ?? []).filter((text) => HAN.test(text)).length;
  const outside = src.replace(LITERAL, blank).split('\n').filter((line) => HAN.test(line)).length;
  litTotal += lit;
  outsideTotal += outside;
  if (lit > 0 || outside > 0) offenders.push(`${rel}: lit=${lit} outside=${outside}`);
}

test('源码里没有残留的硬编码中文（注释除外；测试文件与 i18n 表按上面的理由排除）', () => {
  assert.deepEqual(offenders, [], `以下文件还有硬编码中文，改走 i18n 的键：\n${offenders.join('\n')}`);
  assert.equal(litTotal, 0, '字符串字面量里的中文');
  assert.equal(outsideTotal, 0, '字面量之外（JSX 文本等）的中文');
  // 计数不能是「扫到 0 个文件」这种假绿：本树确实有一批 ts/tsx（扫不到文件时上面两条恒真）
  assert.ok(readdirSync(SRC, { recursive: true }).length > 20, '扫描根目录空了（路径写错就是假绿）');
});
