/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 硬编码中文的**残差扫描器 + 棘轮** —— 一份代码两处用：本仓棘轮，与控制组读数
 * （同一条规则打在 `admin/apps/react/src` 上应当 0）。
 *
 * ⚠ **本文件必须是 `.test.ts`**，不是 `.ts`：`tsconfig.app.json:28` 那条「排除测试文件」的
 * `exclude` 正是把 `node:*` 说明符挡在浏览器工程外的机制。落成 `.ts` 时 `tsc -b` 当场报两条
 * （`TS2591 node:fs` + `TS7006 text 隐式 any`）⇒ `npm run build` 直接失败。**别改回 `.ts`**。
 * （那行 `exclude` 的 glob 在这里刻意不写全 —— 它含 `**` 加斜杠，会把本块注释**提前闭合**。）
 *
 * 为什么扫描逻辑单独成一层而不是像 `admin/apps/react` 那样塞进用例里：控制组
 * 那一跑必须用**逐字节相同的判定规则**，抄一份就总有一天会漂。`root` 是参数。
 *
 * 两个口径（沿用 `admin/apps/react/src/i18n/coverage.test.ts` 的定义，控制组读数才可比）：
 * - `lit`：含汉字的**字符串字面量**条数。
 * - `outside`：剥掉注释与字符串之后仍含汉字的行数 —— 兜住 `lit` 的盲区，JSX 文本
 *   （`<button>重试</button>`、`共 {n} 条`）根本不是字面量，`lit` 抓不到。
 *
 * 排除项：`*.test.ts(x)`（中文是用例数据/描述，不是界面文案）、`i18n/**`（译文表与
 * 13 个母语名就是中文的落点）。**注释按行剥**：本仓注释密度极高，不剥的话读数是垃圾
 * （这正是「朴素 grep 在 C 端得 358、在已清零的 admin 端也有 356」的原因 —— 两个数
 * 几乎相等就是仪器在数注释的指纹）。
 *
 * ponytail: 手写扫描器，注释只按两条正则剥，模板串里不认嵌套反引号 —— 与 admin 那台
 * 同款、同款局限。要更严谨得上真 parser，等真出现误报再说。
 */
import assert from 'node:assert/strict';
import { mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

const LITERAL = /'(?:[^'\\\n]|\\.)*'|"(?:[^"\\\n]|\\.)*"|`(?:[^`\\]|\\.)*`/g;
const HAN = /[一-鿿]/;
/** 整段换成等长空白（保留换行）：直接删掉会让后面的行号与行切分全错位。 */
const blank = (match: string): string => match.replace(/[^\n]/g, '');

export type Residue = {
  /** 相对 `root` 的路径。 */
  rel: string;
  /** 含汉字的字符串字面量条数（与 admin 控制组同口径）。 */
  lit: number;
  /** 字面量之外仍含汉字的行数（与 admin 控制组同口径）。 */
  outside: number;
  /** 上面两种残差各自落在哪几行（1-based），给「文件:行」清单用。 */
  litLines: number[];
  outsideLines: number[];
};

/** 该文件是否在扫描范围内。与 admin 的排除项逐条对应。 */
export function inScope(rel: string): boolean {
  if (!/\.tsx?$/.test(rel)) return false;
  if (/\.test\.tsx?$/.test(rel)) return false;
  if (/^i18n[\\/]/.test(rel)) return false;
  return true;
}

/** 扫一棵树，只回**有残差**的文件（全绿时返回空数组 —— 控制组读数就是这么来的）。 */
export function scanChinese(root: string): Residue[] {
  const base = root.endsWith('/') ? root : `${root}/`; // readdirSync 回的是相对路径，拼接别漏斜杠
  const offenders: Residue[] = [];
  for (const rel of readdirSync(base, { recursive: true, encoding: 'utf8' }).sort()) {
    if (!inScope(rel)) continue;
    const src = readFileSync(`${base}${rel}`, 'utf8')
      .replace(/\/\*[\s\S]*?\*\//g, blank)
      .replace(/\/\/[^\n]*/g, '');
    const literal = (src.match(LITERAL) ?? []).filter((text: string) => HAN.test(text));
    const bare = src.replace(LITERAL, blank);

    const withLit = src.split('\n');
    const noLit = bare.split('\n');
    const litLines: number[] = [];
    const outsideLines: number[] = [];
    for (let i = 0; i < withLit.length; i += 1) {
      // 同一行两样都占（`const a = '中文'; <b>中文</b>`）就两边都记：口径是「这行有没有」
      if (HAN.test(withLit[i]!) && !HAN.test(noLit[i]!)) litLines.push(i + 1);
      if (HAN.test(noLit[i]!)) outsideLines.push(i + 1);
    }

    if (literal.length > 0 || outsideLines.length > 0) {
      offenders.push({ rel, lit: literal.length, outside: outsideLines.length, litLines, outsideLines });
    }
  }
  return offenders;
}

/** 给报告用的一行摘要：`文件:平铺行号`，测试与脚本共用同一种打印。 */
export function format(rows: Residue[]): string {
  return rows
    .flatMap((row) => [
      ...row.litLines.map((n) => `${row.rel}:${n} (lit)`),
      ...row.outsideLines.map((n) => `${row.rel}:${n} (outside)`),
    ])
    .join('\n');
}

const CEND_SRC = fileURLToPath(new URL('../', import.meta.url));
/** 控制组：`admin/apps/react` 已有自建棘轮、记忆称 0 残留 ⇒ 同一台仪器打上去必须是 0。 */
const ADMIN_SRC = fileURLToPath(new URL('../../../../admin/apps/react/src/', import.meta.url));

/**
 * **每文件残留基线**（`lit + outside` 之和）。
 *
 * 为什么是「每文件上限」而不是一个全局上限：全局和会被**文件间的挪动**蒙混过去
 * （Kyc 少 3 条、Tickets 多 3 条，总和不变 ⇒ 棘轮不红）。分文件之后，任何一条新增都必然
 * 顶破它所在文件的那一格。而用**每文件和**而不是分开钉 `lit`/`outside`：抽取过程中
 * JSX 文本可能被改写成字符串字面量（`<b>重试</b>` → `{'重试'}`），那一瞬 `lit` ↑ / `outside` ↓
 * 而和不变 —— 分开钉会在抽取中途报假红。
 *
 * 取数窗口：2026-10-02T11:42:40+08:00，树指纹 `ce91397a61dbf1b87c8e167f136e40fc`
 * （`find src \( -name '*.ts' -o -name '*.tsx' \) | sort | xargs md5sum | md5sum`；**条数必须与哈希一起打印**
 * —— 同一份清单再 `| wc -l` 跑一遍。哈希的**形状**在「零匹配」与「正常」时**完全相同**，
 * 条数是唯一能区分两者的读数：本仓实测 `在册文件数 = 0` 配一个看着正常的 32 位指纹同一个命令里出来）。
 * ⚠ 照抄这条配方**别把 glob 关进引号**：写成 `'*.ts{,x}'` 时花括号不展开 ⇒ `find` 一个也不匹配、
 * `xargs md5sum` 收不到实参就退化成读 stdin ⇒ 读数变成**空清单的 md5**（`886f4202…`），
 * 看着像指纹，其实是空转。两种写法跑出来的清单已验过**逐字节相同**，只有引号这一处会静默改读数。
 * ⚠ 该窗口内 **`apps/react` 有并发写者正在做抽取**（见报告），所以这组数**不是稳定基线**，
 * 只是「拍下这一刻，之后不许涨」。
 *
 * **抽干净之后把本表清空**（`const BASELINE = {}`）—— 那时任何中文都该红，本表就是全零。
 *
 * ✅ **2026-10-02 已清空**（20 页全部抽完，最后一批是 `Tickets.tsx` / `Kyc.tsx`）：
 * 实测 `scanChinese(src/)` 回 **0 个文件**（`ALL=104 IN_SCOPE=45`，其中 20 个 page），
 * 即每个文件的上限都收到 0 —— 现在**任何**新增硬编码中文都必然顶破它所在文件那一格。
 * 清空前先验过**仪器没瞎**（零命中只能证明「盘上没有」，不能证明「它看得见」）：
 * ① 阳性对照：把 `export const a = '中文';` 放进 `/tmp/residue-probe/probe.tsx` 扫出 `lit=1, litLines=[1]`；
 * ② 在册文件数 `45`（>20 的反假绿断言仍成立）；③ 朴素 grep 报出的 `lib/labels.ts` / `lib/datetime.ts` /
 * `Login.tsx` 逐行看过，命中全在注释里。
 */
const BASELINE: Record<string, number> = {};

const detail = (rows: Residue[]): string =>
  rows.map((r) => `${r.rel} lit=${r.lit} outside=${r.outside}`).join('\n');

test('控制组：同一台扫描器打在 admin/apps/react 上必须是 0（证明本仪器不是恒红）', () => {
  const rows = scanChinese(ADMIN_SRC);
  assert.deepEqual(
    rows.map((r) => `${r.rel}:${r.lit}/${r.outside}`),
    [],
    `控制组不干净 ⇒ 要么 admin 树真退化了，要么**扫描器本身失效**（漏排除注释就恒红、\n` +
      `路径写错就恒绿），两种都不许往下走：\n${detail(rows)}`,
  );
  // 控制组必须真扫到文件：根目录写错时上面那条 `deepEqual([], [])` 是**假绿**
  assert.ok(
    readdirSync(ADMIN_SRC, { recursive: true }).length > 20,
    '控制组根目录空了（路径写错 ⇒ 上面那条恒绿）',
  );
});

/**
 * **仪器自检**：同一台扫描器对一段**已知含中文**的输入必须报出 `lit=1`。
 *
 * 为什么必须有：上面那条控制组打的是 `admin/apps/react` —— **那棵树也是干净的** ⇒
 * 「干净树 × 死扫描器」交回**同一个绿**。实测：把 `inScope` 改成恒 `false`（扫描器彻底瞎了）
 * 之后，全套 83 条**一条都不红**。零命中只能证明「盘上没有」，不能证明「它看得见」——
 * 这里给它一个已知答案的输入，把当初那次人肉阳性对照变成每次跑套件都在跑的断言。
 *
 * ⚠ 本用例自身也要能红：把期望值改成 `probe.tsx:2/0` ⇒ **恰好这一条红**（不是装饰）。
 */
test('自检：扫描器对一段已知中文必须报出 1 条 lit（否则它就是瞎的）', () => {
  const dir = mkdtempSync(`${tmpdir()}/residue-probe-`);
  try {
    writeFileSync(`${dir}/probe.tsx`, "export const PROBE = '中文';\n", 'utf8');
    assert.deepEqual(
      scanChinese(dir).map((r) => `${r.rel}:${r.lit}/${r.outside}`),
      ['probe.tsx:1/0'],
      '扫描器对已知中文报了 0 条 ⇒ 下面那条棘轮在终态恒真（它在数一个它看不见的树）',
    );
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test('C 端源码没有**新增**硬编码中文（逐文件棘轮；注释/测试/i18n 表按上面的理由排除）', () => {
  const rows = scanChinese(CEND_SRC);
  const over = rows
    .map((r) => ({ rel: r.rel, now: r.lit + r.outside, cap: BASELINE[r.rel] ?? 0 }))
    .filter((x) => x.now > x.cap);
  assert.deepEqual(
    over,
    [],
    `这些文件**新增**了硬编码中文（界面文案要走 i18n 的键）：\n` +
      over.map((x) => `${x.rel}: ${x.now} > 上限 ${x.cap}`).join('\n') +
      `\n\n全部残差：\n${detail(rows)}`,
  );

  // 扫描根必须真扫到东西：路径写错时上面的 `over` 恒空（与 admin 那条同款的反假绿）
  assert.ok(
    readdirSync(CEND_SRC, { recursive: true }).length > 20,
    '扫描根目录空了（路径写错就是假绿）',
  );

  // 基线表本身不许退化成空壳：空表 = 上限全 0 = 一旦抽干净就恒真，谁也发现不了它失效。
  // 断言的是「本表要么空（抽完了）、要么确实覆盖了当前所有有残差的文件」。
  const covered = rows.filter((r) => !(r.rel in BASELINE));
  assert.deepEqual(
    covered.map((r) => r.rel),
    [],
    '有残差文件不在基线表里（上限按 0 算），要么是新增文件，要么是基线表漏了一格',
  );
});
