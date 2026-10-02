/// <reference types="vite/client" />
/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { describe, expect, it } from 'vitest';
import { DICT } from './dictionary';

/**
 * 源码层三颗钉子（同一枚硬币的两面）：
 *
 *  ① **残差棘轮** —— 还有多少行硬编码中文没抽走，只许减不许增。
 *  ② **键引用解析** —— 源码里每个**字面量**词条键都真的在表里。
 *  ③ **未迁移读点棘轮** —— 还有多少处 `e.message`（= 抛点那一刻的**冻结**文案），只许减不许增。
 *
 * ## 为什么必须有（①）
 *
 * 抽取做完之后**没有第二种观察者**：`ng build` 不看文案，真机脚本不进 CI。
 * 没人拦着，下一次「顺手写个中文字面量」就静悄悄退回去了，只有切到另一种语言才看得出来。
 *
 * ## 为什么必须有（③）
 *
 * `ApiError` 是**两态**的：`.msg` 是「服务端原文 / 键」那一态（键拖到渲染期才求值 ⇒ 切语言跟着变），
 * `.message` 是构造那一刻的快照。读 `.message` 的页面**在切语言时不会重渲染成新语言** ——
 * 而那正是本批（C）要治的冻结文案缺陷族：不报错、测不出、只有切语言才看得见。
 * 这个数字必须**收敛到 0**（每处 `set(e.message)` 改成 `set(e.msg)`），故设上限而不是设目标区间。
 *
 * ## 为什么必须有（②）
 *
 * 这是本批变异实测抓到的缺口：把 `pages/deposit.ts` 里的 `{{ 'deposit.title' | t }}`
 * 拼错成 `deposit.titel`，**全套件 0 红** —— `t()` 对认不出的键**原样吐键名**，
 * 于是页面上大摇大摆地印着 `deposit.titel`，而键集/占位符那两条钉子查的是**表**不是**引用点**。
 *
 * ⚠ **覆盖边界**（别把它当成万能）：只查**字面量**键。动态键查不到 ——
 * 例如 `label(TX_LABEL, r.type) | t` 的键来自组件内数据表。那种表由
 * `pages/wallet.spec.ts` 的「表里每条都能翻出中文」盯着（它断言**整条链**无 ASCII，
 * 键拼错时 `t()` 吐键名 ⇒ 命中 ASCII ⇒ 红），两者互补。
 *
 * ⚠ 本文件是 `.spec.ts` ⇒ 被 `tsconfig.spec.json` 收进 `ng test`、被 `tsconfig.app.json`
 * 排除在 `ng build` 之外。**扫描器/棘轮必须落在这个命名口径里**，否则会被 `ng build` 纳入而挡门
 * （另一个代理在 react 树上踩过一次：`residue.ts` 把 `tsc -b` 打红）。
 *
 * 规则只留一份：本文件是唯一实现，`/tmp` 下的同名脚本只是它的影子，别反过来抄。
 */

/** 原文取用走 vite 的 `?raw` ⇒ **不需要 `@types/node`**（本树 `tsconfig.spec.json` 的 `types` 只有 `vitest/globals`） */
const RAW = import.meta.glob('../../../**/*.{ts,html,scss}', {
  query: '?raw',
  import: 'default',
  eager: true,
}) as Record<string, string>;

/** 本文件所在目录（相对 `src/`）—— glob 键是**相对本文件**的，得先折回相对 `src/` 的路径 */
const IMPORTER = 'app/core/i18n';

/**
 * ⚠ **vite 会把 glob 模式里的 `../../../` 收敛**：实际拿到的是 `./locales/ja.ts`、
 * `../captcha.ts`、`../../pages/kyc.ts` —— 不是统一的 `../../../` 前缀。
 * 所以这里必须**逐段折叠**（`.` 跳过、`..` 弹栈），不能只剥固定前缀：
 * 按固定前缀剥的那版，`SKIP` 一条都没命中 ⇒ `dict/`、`locales/` 里的中文全被算成欠账
 * （实测虚高 753 vs 真值 457），而这类错误**只会把数字算大**，看起来像「还没抽干净」，
 * 不会报错 —— 即典型的「仪器坏了但读数看着合理」。
 */
const rel = (p: string): string => {
  const out = IMPORTER.split('/');
  for (const seg of p.split('/')) {
    if (seg === '.' || seg === '') continue;
    if (seg === '..') out.pop();
    else out.push(seg);
  }
  return out.join('/');
};

/**
 * 排除项，逐条给理由 —— 「扫不到」和「不扫」必须看得出区别：
 */
const SKIP: { test: (r: string) => boolean; why: string }[] = [
  { test: (r) => /\.spec\.ts$/.test(r), why: '用例里的中文是测试数据，不是界面文案' },
  { test: (r) => r.includes('/i18n/dict/'), why: '译文表本身 = 中文的落点' },
  { test: (r) => r.includes('/i18n/locales/'), why: '同上（另 11 种语言的表）' },
  { test: (r) => r === 'app/core/i18n/dictionary.ts', why: '唯一一条 CJK 是「词条重复」的开发期 throw，不进界面' },
  { test: (r) => r === 'app/core/i18n/langs.ts', why: '13 个母语名是语言清单 —— 翻成 "Chinese" 等于让看不懂界面的人选不对' },
  { test: (r) => r === 'index.html', why: '构建期静态壳，无运行时 i18n 通道（两棵 C 端树同形）' },
];

const HAN = /[一-鿿]/;
/** 整行 CJK 字面量都被包住才算免：`t('k')` / `t('k', {…})` / `'k' | t` */
const WRAP = /(?:\bt|\$t|T)\s*\(\s*['"`][^'"`]*['"`]\s*\)|\|\s*t\b/;
/** 键形（用于「键形字面量」这一路，能把组件数据表里的值也覆盖进来） */
const KEY_SHAPE = /^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/;

/**
 * 当前欠账额：**只许减不许增**。每抽完一批（B2/B3/B4）把它改成**当批实测值**；抽完全树应为 0。
 * 305 = G2 收尾时的实测值（清单留档 `/tmp/x-cend-i18n-angular/residue-pre.txt`，且与抽 `residueOf` 之后的
 * 读数**逐字节相同**——那一步是行为不变的纯重构）。
 * 271 = 抽完 `pages/playlogs.ts` 之后的实测值（305 − 34，清单 `residue-playlogs-post.txt`：
 * 那一页 34 条一条不剩，其余页一行未动）。
 * 239 = 抽完 `pages/tournaments.ts` 之后的实测值（271 − 32，清单 `residue-tourney-post.txt`：
 * 那一页 32 条一条不剩，逐文件计数与上一批**逐行相同**，只少了 tournaments 那一列）。
 * 207 = 抽完 `pages/tickets.ts` 之后的实测值（239 − 32，清单 `residue-tickets-post.txt`：
 * 那一页 32 条一条不剩，逐文件计数同样与上一批**逐行相同**，只少了 tickets 那一列。
 * 178 = 抽完 `pages/friends.ts` 之后的实测值（207 − 29，清单 `residue-friends-post.txt`：
 * 那一页 29 条一条不剩，同样只少了 friends 那一列 —— item ② 点名的四页到此**全部归零**，
 * 剩下的 16 个文件是 item ② 之外的存量，最大一坨 `pages/activities.ts` 21 条）。
 * ⚠ 后续批次只会把它**压得更低**（抽走一条少一条）⇒ 届时顺手改成那一批的实测值即可，
 * 不是「基线过期要重取」；真正会让它作废的只有「往界面里新写中文」（那本来就该红）。
 */
const BUDGET = 178;

/**
 * ③ 未迁移读点的射程：**界面层**（`pages/**`）加 `core/captcha.ts`（那个 catch 的文案直接落进页面）。
 *
 * 为什么**不**扫全树：`core/api.base.ts` / `core/upload.ts` 里的 `res?.message`、`body.message`、
 * `env?.message` 是**信封字段读**，是两态取值的**生产端**，永远归不了零 ——
 * 收进来就给棘轮垫了一个降不到的地板，「只许减」也就失去了终点。
 * 反过来，`pages/**` 里每一处 `.message` 按构造都是界面读点（实测 61/61 都是 `e.message`，
 * 没有一处是信封读），所以按**目录**划界而不是按**变量名**划界：后者会被 `err.message`
 * 这种换个名字的写法静默漏掉，棘轮变松而读数看着合理。
 */
const MSG_SCOPE = (r: string): boolean => r.startsWith('app/pages/') || r === 'app/core/captcha.ts';
/**
 * 未迁移读点：**只许减不许增**。C-1 落地后实测 62（pages 61 + captcha 1）；
 * 59 = 抽完 `pages/playlogs.ts` 之后的实测值（pages 58 + captcha 1 —— 那页 3 处 `set(e.message)`
 * 全改成 `set(e.msg)`，其余页一处未动）。
 * 56 = 抽完 `pages/tournaments.ts` 之后的实测值（pages 55 + captcha 1 —— 同样是 3 处
 * `set(e.message)` → `set(e.msg)`；清单里 tournaments.ts 已一条不剩）。
 * 52 = 抽完 `pages/tickets.ts` 之后的实测值（pages 51 + captcha 1 —— 那一页 4 处
 * `set(e.message)` → `set(e.msg)`：load / create / open / send 四条错误回包各一处）。
 * 48 = 抽完 `pages/friends.ts` 之后的实测值（pages 47 + captcha 1 —— 那一页 4 处：
 * 好友列表 / 申请列表两条各自降级的 `catchError`、`act()` 的公共壳、`doSearch()` 的失败分支）。
 * 收敛到 0 才算抽完。
 */
const BUDGET_MSG_READS = 48;

/**
 * ④ 「写入那一刻就把语言定死」的形态：`x.set(t('键'))`。
 *
 * `t()` 是**普通函数**，在 `set()` 实参位置上立即求值 ⇒ 存进去的是一段**当刻**的成文，
 * 之后无论怎么切语言那一行都不会再变（`Msg` 的两态里它退化成 raw 串）。
 * 正解是存键（`x.set({ key: '…' })`）再在模板上过 `| mt` —— 键在**渲染时**才求值。
 *
 * ⚠ 与上面两条棘轮的关系：这条**不看欠账额**，只看形态，目标恒为 0（G1 落地后实测 0）。
 * 它能抓的与它抓不到的都要说清：
 *   抓得到 —— 有人再把某一处写回 `set(t('…'))`（这是**回归**，不是欠账）；
 *   抓不到 —— 键名拼错（归「键引用」那条钉子）、以及「存了键但模板忘了过 `| mt`」
 *             （那一处由真机读数管：只有真机才能证明**已渲染的那行会跟着切**）。
 * 终态为 0 的判据天生会因扫描坏掉而**无声变绿** ⇒ 必须配合成正控（见用例）。
 */
const EAGER = /\.set\(\s*t\s*\(/g;
/** 正控与主判据**共用这一份**实现：正控只证明「这条正则能红」，不能替主判据证明别的 */
const eagerIn = (r: string, code: string): string[] =>
  [...code.matchAll(EAGER)].map((m) => `${r}:${code.slice(0, m.index ?? 0).split('\n').length}`);

type Lit = { start: number; end: number; value: string; quote: string };

/**
 * 把**注释**换成等长空白（保留换行 ⇒ 行号不错位），并把**字符串字面量**切出来。
 *
 * ⚠ **不能拿正则 replace 剥注释**（这版之前就是这么写的，实测踩了两次）：
 * `//` 出现在字符串/模板里（`https://…`、样式里的 `//`）时，「`//` 到行尾」那条 replace
 * 会把该行剩下的 **引号一起吃光**，之后全文引号配对整体错位 —— 症状是「字面量」跨越多行函数体，
 * 于是 ① 残差从真值 457 虚高到 753，② 「键形字面量」一路**直接归零**（集合变小 ⇒ 那第三条钉子
 * 静默变松，照样绿）。两个症状都不报错，只是读数变得"看着合理"。
 * ⇒ 引号/注释必须在**同一次扫描**里按状态机走，不能让正则各管各的。
 */
function mask(src: string): { code: string; lits: Lit[] } {
  const out = src.split('');
  const lits: Lit[] = [];
  const wipe = (from: number, to: number): void => {
    for (let k = from; k < to && k < src.length; k++) if (src[k] !== '\n') out[k] = ' ';
  };
  let i = 0;
  while (i < src.length) {
    const c = src[i]!;
    if (c === '/' && src[i + 1] === '/') {
      let j = i;
      while (j < src.length && src[j] !== '\n') j++;
      wipe(i, j);
      i = j;
      continue;
    }
    if (c === '/' && src[i + 1] === '*') {
      const e = src.indexOf('*/', i + 2);
      const stop = e < 0 ? src.length : e + 2;
      wipe(i, stop);
      i = stop;
      continue;
    }
    if (c === '<' && src.startsWith('<!--', i)) {
      const e = src.indexOf('-->', i + 4);
      const stop = e < 0 ? src.length : e + 3;
      wipe(i, stop);
      i = stop;
      continue;
    }
    if (c === "'" || c === '"') {
      const start = i;
      i++;
      while (i < src.length) {
        if (src[i] === '\\') {
          i += 2;
          continue;
        }
        if (src[i] === c) {
          i++;
          break;
        }
        i++;
      }
      lits.push({ start, end: i, value: src.slice(start + 1, i - 1), quote: c });
      // 字面量的**内容**抹掉、引号留着：这样 `code` 里剩下的中文才是「字面量之外」的中文。
      // ⚠ 不抹的话每条 CJK 字面量会被**数两遍**（字面量一遍、「字面量外」按行又一遍）：
      // 实测读到 1074 而清单里**每一条都带着一个 `(字面量外)` 孪生条目** —— 那正是「虚高一倍」的指纹。
      // 引号必须留：`WRAP` 判据认的就是 `t('…')` 这个形状。
      wipe(start + 1, i - 1);
      continue;
    }
    // ⚠ **反引号刻意不当作字面量**（本树模板全是内联反引号串）：
    // 当成字面量的话，整个 `template: …` 那一段会被吞成**一条**巨大字面量 —— 里面的
    // `{{ 'deposit.title' | t }}` 就不再是字面量，于是**键引用那一路对模板整体失明**，
    // 而模板恰好是本树绝大多数键的引用点（M3 要堵的就是这里）。
    // 透明处理后：模板正文里的中文落进「字面量外」按行数（行号准、不重复），
    // 模板里的 `'键'` 落进正常字面量。
    // 代价：模板正文里若出现**落单的引号**会错位 —— 症状是残差虚高，看得见，不是静默。
    i++;
  }
  return { code: out.join(''), lits };
}

/**
 * ① 残差的**唯一实现**：字面量内 + 字面量外两路，**按行去重**。
 *
 * 抽成函数只为给「合成正控」用 —— 正控必须走**同一份实现**，否则它只证明"另一条正则能红"，
 * 不能替主判据证明什么（同 ④ 的 `eagerIn`）。入参是已经 `mask()` 过的 `code` 与 `lits`：
 * `scan()` 里一次 mask 供三条钉子共用，别在这里再扫一遍。
 */
function residueOf(r: string, code: string, lits: Lit[]): { lines: number[]; offenders: string[] } {
  const litLines = new Set<number>();
  const outLines = new Set<number>();
  let wrappedAll = true;
  for (const lit of lits) {
    if (!HAN.test(lit.value)) continue;
    const startLine = code.slice(0, lit.start).split('\n').length;
    lit.value.split('\n').forEach((piece, i) => {
      if (HAN.test(piece)) litLines.add(startLine + i);
    });
    // 判据只看字面量前后各 60 字符 —— 够覆盖 `'k' | t` 与 `t('k', {…})` 两种形态
    if (!WRAP.test(code.slice(Math.max(0, lit.start - 60), lit.end + 20))) wrappedAll = false;
  }
  // 整行 CJK 字面量全被 t 包住才免；否则连同未包的一起算欠账
  const exemptLit = litLines.size > 0 && wrappedAll;
  // 字面量**外**的中文：模板正文、HTML 文本节点（内容已抹掉，所以这里不会和上面重复数）
  for (const [i, line] of code.split('\n').entries()) if (HAN.test(line)) outLines.add(i + 1);
  // ⚠ 两路**必须并成一个集合再数**：判据是「多少**行**硬编码中文」，
  // 而一行可以同时有「字面量里的中文」和「字面量外的中文」——
  // 实测 `pages/tournaments.ts:146`（`目录 {{ … ? ' · 第 ' + … + ' 名' … }}`）就是一例，
  // 按路累加会把它数两遍（458 vs 457），而虚高的读数**看着完全合理**、不会报错。
  const counted = new Set<number>([...(exemptLit ? [] : litLines), ...outLines]);
  const lines = [...counted].sort((a, b) => a - b);
  return { lines, offenders: lines.map((n) => `${r}:${n}${outLines.has(n) ? ' (字面量外)' : ''}`) };
}

/** 扫描一次，供三条用例共用（模块级算一次，别在用例里重复扫） */
function scan(): {
  files: number;
  residue: number;
  offenders: string[];
  keys: Set<string>;
  paths: string[];
  msgReads: string[];
  eager: string[];
} {
  let residue = 0;
  const offenders: string[] = [];
  const keys = new Set<string>();
  const paths: string[] = [];
  const msgReads: string[] = [];
  const eager: string[] = [];
  for (const [path, raw] of Object.entries(RAW).sort(([a], [b]) => a.localeCompare(b))) {
    const r = rel(path);
    if (!/\.(ts|html|scss)$/.test(r) || SKIP.some((s) => s.test(r))) continue;
    paths.push(r);
    const { code, lits } = mask(raw);

    // —— ① 残差（实现见 `residueOf`，合成正控走同一份）——
    const res = residueOf(r, code, lits);
    residue += res.lines.length;
    offenders.push(...res.offenders);

    // —— ② 键引用：三路并集（比任何一路都强）——
    for (const lit of lits) {
      const before = code.slice(Math.max(0, lit.start - 40), lit.start);
      const after = code.slice(lit.end, lit.end + 20);
      // `t('k')` / `$t('k')` / `T('k')`（`\s*$` 保证是紧邻的左括号）
      if (/(?:\bt|\$t|T)\s*\(\s*$/.test(before)) keys.add(lit.value);
      // `{{ 'k' | t }}` / `{{ (a ? 'x' : 'k') | t }}`（中间可能隔着右括号）
      else if (/^\s*\)*\s*\|\s*t\b/.test(after)) keys.add(lit.value);
      // 键形字面量（组件数据表里的值就走这一路）
      else if (lit.quote === "'" && KEY_SHAPE.test(lit.value)) keys.add(lit.value);
    }

    // —— ③ 未迁移读点：`.message`（冻结文案）——
    // 走 `code` 而不是原文：注释里的 `.message` 与字面量里的 `.message` 都不算读点
    // （文件头注释、`'message' in body` 那种形状被抹掉后不会命中）。
    if (MSG_SCOPE(r)) {
      for (const m of code.matchAll(/\.message\b/g)) {
        msgReads.push(`${r}:${code.slice(0, m.index ?? 0).split('\n').length}`);
      }
    }

    // —— ④ 已经落地的反模式：`set(t('键'))` ——
    // 射程是**全树**（不像 ③ 要排除信封读的生产端）：这个形态在哪里都是"写入那一刻定死语言"，
    // 目标恒为 0，没有垫地板的问题。
    eager.push(...eagerIn(r, code));
  }
  return { files: Object.keys(RAW).length, residue, offenders, keys, paths, msgReads, eager };
}

const SCAN = scan();

describe('源码层 i18n 钉子', () => {
  it('扫描分母没塌（glob 路径写错 ⇒ 一个都没扫到 ⇒ 下面两条恒真）', () => {
    // 本树 90+ 个文件进 glob。这条只拦「扫了个寂寞」这种假绿：
    // 若 `import.meta.glob` 没被 vite 转换/路径写错，RAW 会是空对象，后面两条会**无声变绿**。
    expect(SCAN.files, 'RAW 为空 ⇒ glob 没生效（路径写错或 ?raw 未被支持）').toBeGreaterThan(50);
    // 正向对照 ①：钱四个页面必须真的进了扫描集（`rel()` 折错时它们会被算成走不到）
    expect(SCAN.paths, '四个资金页没进扫描集').toEqual(
      expect.arrayContaining([
        'app/pages/wallet.ts',
        'app/pages/deposit.ts',
        'app/pages/withdraw.ts',
        'app/pages/exchange.ts',
      ]),
    );
    // 正向对照 ②：**每一条路一个对照**，且每条都挑「只在这一条路上出现」的键
    // （挑错键的对照会假装通过：`wallet.available` 只活在 `dict/`、`locales/` 里，
    //  而那两处是 SKIP 掉的，拿它当对照**测不出任何一路**）。
    // 少了对照，第三条钉子会**静默变松**：集合小了，照样「全解析」。
    expect(SCAN.keys.has('deposit.title'), '模板路（{{ \'deposit.title\' | t }}，只在 pages/deposit.ts）').toBe(true);
    expect(SCAN.keys.has('tx.exchange_in'), '数据表路（TX_LABEL 里的值，只在 pages/wallet.ts）').toBe(true);
    // 反向对照：残差为 0 是**终态**。现在还没抽完，若读到 0 说明扫描逻辑被改坏了（而不是"抽干净了"）
    expect(SCAN.offenders.length > 0, '残差为 0 ⇒ 扫描逻辑坏了，不是抽干净了').toBe(true);
  });

  /**
   * ⚠ 正控**必须另立一条用例**（同 ④ 的拆法）：并进上限那条的话，扫描器一变松，正控先红
   * ⇒ 那条用例**当场中止** ⇒ 主判据在同一次运行里报不出自己的状态。
   *
   * 为什么非有不可：上限那条的终态是「残差降到很低」，而扫描器**变松**（少算）时读数只会变小
   * ⇒ 它**无声变绿**；分母那条只拦得住「扫了个寂寞」，拦不住「扫了但漏算」。
   * 这里喂**合成源码**、走**同一份实现**（`mask()` + `residueOf()`），把四种形态的精确读数断死。
   */
  it('残差判据的正控：合成源码走同一份实现，四种形态各自断死（变松/变严这里先红）', () => {
    const syn = (src: string) => {
      const { code, lits } = mask(src);
      return residueOf('合成.ts', code, lits);
    };
    // ① 未包的字面量：算欠账，且**不带**「字面量外」标记（它来自字面量那一路）
    expect(syn("const a = '未包的中文';").offenders, '未包的字面量没被算成欠账 ⇒ 扫描器变松').toEqual([
      '合成.ts:1',
    ]);
    // ② 被 t 包住的：免 —— `t('…')` 与模板里的 `'…' | t` 两种形态各一条
    expect(syn("this.x = t('已包的中文');").offenders, '`t(…)` 包住的仍被算成欠账 ⇒ 扫描器变严').toEqual([]);
    expect(syn("const h = `<b>{{ '已包的中文' | t }}</b>`;").offenders, '`| t` 形态的免没生效').toEqual([]);
    // ③ 字面量**外**的中文（模板正文；反引号在本树刻意透明）：按行数，带「字面量外」标记
    expect(syn('const tpl = `<div>模板里的中文</div>`;').offenders, '字面量外的中文没进账 ⇒ 模板路失明').toEqual([
      '合成.ts:1 (字面量外)',
    ]);
    // ④ **同一行两路只算一次**（就是 457 vs 458 那个坑）：一行里既有未包字面量又有字面量外的中文 ⇒ 仍只 1 条
    expect(syn("const m = `<div>外</div>` + '里';").offenders, '同行两路被数了两遍（欠账虚高）').toEqual([
      '合成.ts:1 (字面量外)',
    ]);
  });

  it(`未抽取的中文行数不超过欠账额（当前 ${BUDGET}）`, () => {
    // 正控是**上面那条独立用例**（合成源码四种形态）；这条只管读数，不重复证明扫描器还活着。
    expect(
      SCAN.residue,
      `还有 ${SCAN.residue} 行硬编码中文，上限 ${BUDGET}。` +
        `新写的字面量请改走 i18n 的键；清单：\n${SCAN.offenders.join('\n')}`,
    ).toBeLessThanOrEqual(BUDGET);
  });

  it(`未迁移的 \`.message\` 读点不超过棘轮（当前 ${BUDGET_MSG_READS}，只许减）`, () => {
    // 正向对照（**先跑**）：棘轮的终点是 0，读点集一旦因为作用域/正则写坏而变空，
    // 下面那条上限就**无声变绿** —— 与残差那条同一个坑（终态自毁）。
    // 挑两个「只在自己那条路上出现」的读点：换掉任一条判据都必须让这里先红。
    expect(
      SCAN.msgReads,
      'pages/me.ts 有 7 处 e.message。扫不到 ⇒ 作用域坏了，棘轮恒真',
    ).toEqual(expect.arrayContaining([expect.stringMatching(/^app\/pages\/me\.ts:\d+$/)]));
    expect(
      SCAN.msgReads,
      'core/captcha.ts 那处读点不在 pages/ 下，是**单独**收进射程的，扫不到就是收漏了',
    ).toEqual(expect.arrayContaining([expect.stringMatching(/^app\/core\/captcha\.ts:\d+$/)]));
    // 反向对照：信封字段读**刻意不入账**（生产端永远归不了零，收进来等于垫地板）。
    // 它靠上面两条正控兜底 —— 扫描整体坏掉时上面先红，这里不会假装通过。
    expect(
      SCAN.msgReads.some((p) => p.startsWith('app/core/api.base.ts')),
      'api.base.ts 的信封读被算进来了 ⇒ 射程划错，棘轮永远降不到 0',
    ).toBe(false);
    expect(
      SCAN.msgReads.length,
      `还有 ${SCAN.msgReads.length} 处读**冻结**文案（\`.message\` 而非 \`.msg\`），上限 ${BUDGET_MSG_READS}。` +
        `改读 \`.msg\` 就能随语言切换实时变；清单：\n${SCAN.msgReads.join('\n')}`,
    ).toBeLessThanOrEqual(BUDGET_MSG_READS);
  });

  /**
   * ⚠ 正控与主判据**必须是两条用例**（合并成一条就退回弱形态）：
   * 并成一条时，扫描器/正则一坏，正控先红 ⇒ 该用例**当场中止**⇒ 主判据在**同一次运行里报不出自己的状态**
   * （实测 E2：那时「主判据是绿的」只能靠"两者共用 `eagerIn`"**推理**，不是读数）。
   * 拆开之后，E2 那类变异下你会**同时看到**：正控红、主判据绿 —— 一个坏扫描器长什么样，是读出来的。
   */
  it('形态判据的正控：`set(t(…))` 抓得到、落键与清空抓不到（扫描器一坏这里先红）', () => {
    // 喂的是与真实站点**同形**的源码，且与主判据共用同一份 `eagerIn`；否则正控只证明"那条正则能红"。
    expect(
      eagerIn('合成.ts', "this.amountError.set(t('deposit.err_amount_required'));"),
      '合成正控不红 ⇒ 这条正则已经抓不到目标形态，主判据那一侧恒真',
    ).toEqual(['合成.ts:1']);
    expect(
      eagerIn('合成.ts', "this.error.set({ key: 'x.y' });\nthis.error.set('');"),
      '反向对照：落键形态与清空形态都不该命中（命中 = 正则宽到把正解也判红）',
    ).toEqual([]);
  });

  it('没有 `set(t(…))` 形态：写入那一刻就把语言定死（全树，目标恒 0）', () => {
    expect(
      SCAN.eager,
      `这 ${SCAN.eager.length} 处是**写入时定死语言**（\`set(t('键'))\`）：切语言后那一行不会跟着变。` +
        `改为存键 \`set({ key: '…' })\` 且在模板上过 \`| mt\`：\n${SCAN.eager.join('\n')}`,
    ).toEqual([]);
  });

  it('源码里引用的每个字面量词条键都在表里（拼错键 ⇒ t() 原样吐键名）', () => {
    const missing = [...SCAN.keys].filter((k) => !(k in DICT)).sort();
    expect(
      missing,
      `这些键在源码里被引用但表里没有 —— 界面上会原样印出键名：\n${missing.join('\n')}`,
    ).toEqual([]);
  });
});
