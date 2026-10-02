/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import test from 'node:test';

// ⚠ 必须先 import 这个：它的模块体里就装了 `localStorage` 替身，而 `i18n/index.ts`
// 在**求值期**就要读偏好（`loadCode()`）—— 顺序反了会读到 undefined 直接抛。
import { installLocalStorage } from '../lib/apiStub.ts';
import { language } from '../lib/api.ts';
import { en } from './en.ts';
import { enCommon } from './en.common.ts';
import { enPages } from './en.pages.ts';
import { DEFAULT_CODE, LANGUAGES, LOCALE_KEY, TABLES, currentCode, isRtl, resolve, setCode, t } from './index.ts';

installLocalStorage();

/** 13 个短码，写死而不是从 LANGUAGES 派生：后者少一条时下面的键集断言会跟着少一条、恒真。 */
const CODES = ['en', 'zh', 'ja', 'ko', 'ru', 'de', 'fr', 'es', 'pt', 'hi', 'ar', 'bn', 'id'];
const HAN = /[一-鿿]/;

test('语言清单与后端 Locale::SUPPORTED 逐项同码（13 条，顺序即菜单顺序）', () => {
  assert.deepEqual(
    LANGUAGES.map((l) => l.code),
    CODES,
    '本清单是 packages/platform-common/src/Locale.php 的手工镜像，后端增删语言必须同步',
  );
  // 母语名是菜单可用的前提，别退化成译名或空串
  for (const item of LANGUAGES) assert.ok(item.native.trim().length > 0, `${item.code} 没有母语名`);
});

test('拆出的两族（common/pages）键集互不相交，且合并后就是整表', () => {
  // ⚠ 只查 en：它是键的真值面，其余 12 张表的键集由下面那条断言逐键钉在 en 上，
  // 而两族的归属是**生成器里 domain→族的纯函数**（一个键不可能落进两族）。
  // 这条挡的是**手改生成产物**：`{...a, ...b}` 重名是后者静默覆盖前者，看不出错。
  const common = Object.keys(enCommon);
  const pages = Object.keys(enPages);
  assert.deepEqual(
    common.filter((k) => pages.includes(k)),
    [],
    '两族有重键 ⇒ 展开时 pages 静默盖掉 common',
  );
  assert.deepEqual(
    [...common, ...pages].sort(),
    Object.keys(en).sort(),
    '两族并集对不上整表 ⇒ barrel 漏合了（或有人往 barrel 里手加了键）',
  );
});

test('13 张表都在，且键集与 en 逐键相等（少一个键就是静默回落英文）', () => {
  const enKeys = Object.keys(en);
  assert.ok(enKeys.length > 300, `en 表只有 ${enKeys.length} 个键，像是被截断了`);
  for (const code of CODES) {
    const table = TABLES[code];
    assert.ok(table, `${code}: 没有注册表 —— 选了它界面会静默回落成英文`);
    assert.deepEqual(
      enKeys.filter((k) => !(k in table)),
      [],
      `${code} 比 en 少键 ⇒ 这些键在 ${code} 下会静默回落成英文`,
    );
    assert.deepEqual(
      Object.keys(table).filter((k) => !(k in en)),
      [],
      `${code} 有 en 没有的键（多出来的键永远不会被读到）`,
    );
  }
});

/**
 * **眼眉 ≠ 标题**：`.label` 眼眉与它正下方渲 `nav.*` 的 `<h1>` **不许同形**。
 *
 * 为什么这是不变量而不是「文案去重」：`Activities.tsx:173-177` 与 `Announcements.tsx:20-22`
 * 都是 `<p className="label">{t('X.label')}</p>` 紧贴 `<h1>{t('nav.X')}</h1>` —— 眼眉的功能是
 * **把标题那个大概念收窄**（限定词 + 概念词）。两格落了同一个词之后，眼眉不再限定任何东西、只剩装饰。
 * 2026-10-02 实测到这个缺陷的**两种成因**：一次是主动把 11 种语言「统一」到 nav 词（已撤销），
 * 一次是 6 种语言的翻译各自塌陷成 nav 词（ja/ko/ru/fr/bn/id 的 `announcements.label`）。
 *
 * ⚠ 判据是「**不相等**」，**不是**「眼眉是标题的子串」：en 的 `Operations` / `Activities` 正是
 * 限定/概念关系，只是恰好不构成包含（12/13 格构成包含形纯属巧合）。谁为了凑包含形把它改成
 * `Operations Activities`，那是**为形式改语义** —— 这条断言故意**不**去钉包含关系。
 */
test('眼眉（.label）与它对应的 nav 标题不许同形（13 语言 × 2 组）', () => {
  const PAIRS = [
    ['activities.label', 'nav.activities'],
    ['announcements.label', 'nav.announcements'],
  ] as const;
  const collapsed: string[] = [];
  for (const code of CODES) {
    const table = TABLES[code];
    assert.ok(table, `${code}: 没有表 —— 缺表时下面的比较会整段空转`);
    for (const [label, nav] of PAIRS) {
      // 先把「两格都在」钉死：缺格时 `undefined === undefined` 会让这条断言以**错误的方式**红
      assert.ok(table[label], `${code}: 缺 ${label}（眼眉没了，页面会渲成键名）`);
      assert.ok(table[nav], `${code}: 缺 ${nav}（标题没了）`);
      if (table[label] === table[nav]) collapsed.push(`${code}: ${label} = ${nav} = ${JSON.stringify(table[label])}`);
    }
  }
  assert.deepEqual(
    collapsed,
    [],
    `眼眉与标题同形 ⇒ 眼眉不再限定任何东西（限定词 + 概念词，不是同一句）：\n${collapsed.join('\n')}`,
  );
});

test('占位符逐键对齐，且没有空值 / 裸键名', () => {
  const holders = (s: string): string[] => (s.match(/\{\w+\}/g) ?? []).sort();
  for (const code of CODES) {
    const table = TABLES[code];
    assert.ok(table);
    for (const [key, value] of Object.entries(table)) {
      assert.ok(value.trim().length > 0, `${code}: ${key} 是空串`);
      assert.notEqual(value, key, `${code}: ${key} 的值是裸键名（英文界面会显示 snake_case）`);
      assert.deepEqual(
        holders(value),
        holders(en[key] ?? ''),
        `${code}: ${key} 的占位符与 en 不一致（写丢一个 {x} 就是渲染时少一块）`,
      );
    }
  }
});

/**
 * 有两格的**空格本身是载荷**（不是排版噪声），而"顺手抹平空格"看起来永远像在清理。
 * 判据逐条对着调用点写，不是抄一句"看起来对"的期望值：
 *
 * - `game.spread`：调用点是 `{c.symbol}` 与 `{t('game.spread')}` 两个**相邻表达式**，
 *   JSX 会把两表达式之间的纯空白文本节点整段丢掉（`rolldown` 实测）⇒ 那个分隔空格
 *   只能由值自己带。抹掉它渲染成 `USD· 点差 2%`（见 `GameDetail.tsx:128` 的注释）。
 * - `friends.tab_list`：空格**不在表里**，而在调用点的参数内（`n: ` ${count}``，计数为 0 时传空串）
 *   ⇒ 表里再多一个空格就渲染成 `好友  3`（两个空格）。见 `Friends.tsx:100`。
 *
 * ⚠ 13 张表都查：翻译填格时最容易把这类空格"统一"掉，而症状只在真机上看得见。
 */
test('两处承载空格的文案：空格在哪一侧是逐字比对定下来的', () => {
  const cases = [
    { key: 'game.spread', ok: (v: string) => v.startsWith(' '), why: '丢了前导空格 ⇒ 渲染成 `USD· 点差 2%`（符号与文案粘在一起）' },
    { key: 'friends.tab_list', ok: (v: string) => !/\s/.test(v), why: '值里多了空格 ⇒ 与参数自带的那个空格叠成两个（`好友  3`）' },
  ] as const;
  for (const code of CODES) {
    const table = TABLES[code];
    assert.ok(table, `${code}: 没有注册表`);
    for (const { key, ok, why } of cases) {
      const value = table[key];
      // 缺键由上面那条键集断言管，这里只管形状：表还没填的语言不该在这里报假红
      if (value === undefined) continue;
      assert.ok(ok(value), `${code}: ${key} = ${JSON.stringify(value)} —— ${why}`);
    }
  }
});

test('t() 读当前语言的表；该语言缺这个键才逐键回落 en', () => {
  setCode('ja');
  assert.equal(t('nav.home'), TABLES.ja!['nav.home']);
  assert.notEqual(t('nav.home'), en['nav.home']); // 日语译文恰好等于英文就说明表没生效

  // 删掉日语的一个键 —— t() 必须当即可见地回落到 en（存的是引用不是拷贝）
  const backup = TABLES.ja!['nav.home']!;
  delete TABLES.ja!['nav.home'];
  try {
    assert.equal(t('nav.home'), en['nav.home']);
  } finally {
    TABLES.ja!['nav.home'] = backup;
  }
  setCode(DEFAULT_CODE);
});

test('切语言同时改 X-Language 的取值 —— 界面与请求头必须同语言', () => {
  setCode('ja');
  assert.equal(currentCode(), 'ja');
  assert.equal(
    language.get(),
    'ja',
    'localStorage 键没跟着动 ⇒ 界面切了、服务端响应文案没变（i18n 与 lib/http.ts 的 K_LANG 漂了）',
  );
  assert.equal(localStorage.getItem(LOCALE_KEY), 'ja');
  setCode('zh');
  assert.equal(language.get(), 'zh');
});

test('没存过偏好时，界面语言与 X-Language 的兜底是同一个值', () => {
  localStorage.clear();
  // 两边都读同一个键：http.ts 的 `|| 'zh'` 与本模块的 DEFAULT_CODE 必须一致
  assert.equal(language.get(), DEFAULT_CODE);
  assert.equal(resolve(null).code, DEFAULT_CODE);
});

test('resolve 按后端 Locale::normalize 的口径归一（短码/全码/大小写都认）', () => {
  assert.equal(resolve('zh-CN').code, 'zh');
  assert.equal(resolve('zh_CN').code, 'zh');
  assert.equal(resolve('EN').code, 'en');
  assert.equal(resolve('pt-BR').code, 'pt');
  assert.equal(resolve('xx').code, DEFAULT_CODE, '认不出来的码落默认值，不抛');
  assert.equal(resolve(undefined).code, DEFAULT_CODE);
});

test('13 种里恰好只有 ar 是 RTL', () => {
  assert.deepEqual(CODES.filter(isRtl), ['ar']);
  assert.deepEqual(CODES.filter((c) => !isRtl(c)).length, 12);
});

/**
 * 例外名单：zh 值本来就不该含汉字，或含了也是错的。
 * **只许一个一个加，加的时候写清为什么** —— 它同时是这条断言的灵敏度上限。
 */
const NO_HAN_OK = new Set([
  // 纯占位符骨架：全角括号是中文标点，但没有汉字可含（「网络异常（-1）」里的汉字来自 {message}）
  'error.with_code',
  // 同款：`{name}（{min} ~ {max}）` 全是占位符，汉字分别在 {name}（如「支付宝」）里
  'deposit.method_option',
  // 专有名词：PayPal 在 13 种语言里都写 `PayPal`，没有可译的东西（与 en 逐字相同是对的）
  'withdraw.method_paypal',
  // 枚举分隔符：zh 是 `、`（U+3001 全角顿号）—— 是标点不是汉字。这格**必须有**：
  // `Activities.tsx` 的 `rewardText()` 要按语言换分隔符（HEAD 写死的 `、` 在英文里是错的）
  'app.list_sep',
  // 纯占位符骨架：`{who} · {time}` 里的汉字全在 {who}（如「张三」）里，zh 与 en **逐字相同是对的**。
  // 与 `Activities.tsx` 同款：`·` 是标点、位置由 zh 文本定死，13 语言的差别只在两边的参数里
  'ticket.reply_meta',
]);

/**
 * `GET /language/list` 的响应夹具 —— **逐字**照
 * `service/app/api/v1/controller/LanguageController::list` +
 * `common/service/TranslationService::getAvailableLanguages()` 的真实产出写，
 * 键的顺序即 `Locale::supported()` 的顺序（`common/Locale.php:81` 的 `FULL_CODES`）。
 *
 * 该端点**本树刻意不接**（理由与真形状见 `lib/types.ts` 的墓碑），所以这里没有包装可测 ——
 * 这条钉的是**形状契约**：将来谁把它接回来，必须按下面的判据写。
 */
const LIST_FIXTURE = {
  code: 0,
  data: {
    current: 'zh', // 短码
    languages: {
      // 全码 —— 两个字段**码制不一致**，这就是当初撤下时那句「届时需重新核」警告的事
      'en-US': { name: 'English', nativeName: 'English', icon: 'us' },
      'zh-CN': { name: 'Chinese (Simplified)', nativeName: '简体中文', icon: 'cn' },
      'ja-JP': { name: 'Japanese', nativeName: '日本語', icon: 'jp' },
      'ko-KR': { name: 'Korean', nativeName: '한국어', icon: 'kr' },
      'ru-RU': { name: 'Russian', nativeName: 'Русский', icon: 'ru' },
      'de-DE': { name: 'German', nativeName: 'Deutsch', icon: 'de' },
      'fr-FR': { name: 'French', nativeName: 'Français', icon: 'fr' },
      'es-ES': { name: 'Spanish', nativeName: 'Español', icon: 'es' },
      'pt-PT': { name: 'Portuguese', nativeName: 'Português', icon: 'pt' },
      'hi-IN': { name: 'Hindi', nativeName: 'हिन्दी', icon: 'in' },
      'ar-SA': { name: 'Arabic', nativeName: 'العربية', icon: 'sa' },
      'bn-BD': { name: 'Bengali', nativeName: 'বাংলা', icon: 'bd' },
      'id-ID': { name: 'Indonesian', nativeName: 'Bahasa Indonesia', icon: 'id' },
    },
  },
};

test('/language/list：current 是短码、languages 的键是全码 ⇒ 必须归一后再比', () => {
  const { current, languages } = LIST_FIXTURE.data;
  const keys = Object.keys(languages);

  // 夹具前提：`current` **不是**键之一。哪天后端统一了码制，这条钉子就该退休 ——
  // 它会先在这里红，告诉你前提没了，而不是继续假装在保护什么。
  assert.ok(
    !keys.includes(current),
    '夹具前提变了：current 直接就是 languages 的键之一，本条钉子的意义已消失',
  );

  // ⚠ 变异点：把两边的 resolve() 去掉就成了 `keys.includes(current)` ⇒ 恰好这条红。
  // 不归一变形的症状是**静默错配**（13 种语言一种都取不到，回落 undefined），不是抛错。
  assert.deepEqual(
    keys.map((k) => resolve(k).code),
    CODES,
    '归一后必须与 LANGUAGES 的 13 个短码逐项对齐（顺序即菜单顺序）',
  );
  assert.ok(
    keys.map((k) => resolve(k).code).includes(resolve(current).code),
    '归一后 current 对不上任何一个 languages 的键 ⇒ 菜单标不出当前选中项',
  );
  // 母语名是菜单可用性的前提，与 LANGUAGES 是同一份真值的两种来源，别漂
  for (const item of LANGUAGES) {
    const full = keys.find((k) => resolve(k).code === item.code)!;
    assert.equal(languages[full as keyof typeof languages].nativeName, item.native);
  }
});

test('每个键的 zh 值都含汉字（挡「表里混进英文/漏译成键名」）', () => {
  const offenders = Object.entries(TABLES.zh!)
    .filter(([k, v]) => !NO_HAN_OK.has(k) && !HAN.test(v))
    .map(([k]) => k);
  assert.deepEqual(offenders, [], `这些键的 zh 值没有汉字，确认不是漏译：${offenders.join(', ')}`);
});

/**
 * 「切语言真的会改界面文案」这一维，本树没有 DOM 测试基建 ⇒ 只能在源码层钉。
 * 钉的是**冻结缺陷类**：模块顶层的 `t('键')` 只在求值那一刻跑一次，把文案冻在首屏语言上，
 * 之后切语言一个字都不变，而且**没有任何报错**（是静默的）。
 * 本批实测踩过三次同款：`Exchange.tsx` 的 `DIRECTIONS`、`Me.tsx` 的 `NAV`、
 * `Activities.tsx` 的 `TYPE_LABEL` —— 修法是常量改存**键**、由调用方在渲染期翻。
 *
 * ⚠ 灵敏度上限（写清楚，别当成全覆盖）：判据 = **缩进 0 的行里没有 `t(`**，即「模块顶层的语句」。
 * 顶层常量里再套一层函数（`const f = () => t('x')`，缩进 2）它看不见 —— 那一类靠上面那条约定与 review 挡。
 */
/** walk 到的文件数下限（两个消费者共用）：实测 43（pages 20 + components 5 + lib 18），留余量
 *  —— 只挡「静默变 0」，不挡正常增删。 */
const MIN_SRC_FILES = 30;

/**
 * 遍历 `src/{pages,components,lib}` 下的非测试源码，逐行交给 `cb(行内容, 位置标签, 行号)`，
 * **并回访问到的文件数** —— 两个消费者都必须拿它当下限断言：walk 静默访问 0 个文件时
 * `offenders` 保持空，那两条用例一起恒真（「0」既是"真的没有"，也是"根本没看"）。
 *
 * ⚠ 三种「变 0」里只有两种是静默的（实测 `readdirSync` 对**不存在**的目录抛 `ENOENT` ⇒ 会红，不用防）：
 * ① 根在、但一个文件都匹配不上（`/\.tsx?$/` 被改坏、源码整体改名成别的扩展名）；
 * ② 根在、但是空壳（文件搬走、目录没删）。分母挡的是这两种。
 */
function eachSrcLine(cb: (line: string, where: string, lineNo: number) => void): number {
  let visited = 0;
  const walk = (dir: URL): void => {
    for (const name of readdirSync(dir)) {
      if (statSync(new URL(name, dir)).isDirectory()) { walk(new URL(`${name}/`, dir)); continue; }
      if (!/\.tsx?$/.test(name) || /\.test\.tsx?$/.test(name)) continue;
      visited += 1;
      const where = `${dir.pathname.replace(/.*\/src\//, '')}${name}`;
      readFileSync(new URL(name, dir), 'utf8')
        .split('\n')
        .forEach((line, i) => cb(line, where, i + 1));
    }
  };
  for (const d of ['pages', 'components', 'lib']) walk(new URL(`../${d}/`, import.meta.url));
  return visited;
}

test('模块顶层没有 t()：文案一律留到渲染期再翻（否则切语言不生效）', () => {
  const offenders: string[] = [];
  const visited = eachSrcLine((line, where, n) => {
    // 缩进 0 + 有 `t(` ⇒ 模块顶层语句里翻了文案
    if (/^(?!\s)[^\n]*\bt\(/.test(line)) offenders.push(`${where}:${n}`);
  });
  assert.ok(
    visited >= MIN_SRC_FILES,
    `walk 只访问到 ${visited} 个文件（下限 ${MIN_SRC_FILES}）⇒ 下面那条 deepEqual 恒真（空转）：` +
      `要么过滤器改坏了，要么那三个根目录成了空壳`,
  );
  assert.deepEqual(offenders, [], `这些模块顶层的 t() 只求值一次，切语言后不会变：${offenders.join(', ')}`);
});

/**
 * 同族第二条，**上一批漏掉的那一种**：翻好的串**存进 state** 也是冻结点 ——
 * state 只在置位那一刻求值一次，之后切语言不会重算。
 *
 * 实测命中处是 `lib/hooks.ts` 的 `setError(e instanceof ApiError ? e.message : t('error.load_failed'))`：
 * 它比模块顶层那条更隐蔽 —— 在 promise 回调里，跟数据一起进来，看着像「已经翻好了」，
 * 而界面上表现为**错误框停留在旧语言**，其余文案都跟着变了。
 * 修法同族：存**原始输入**（错误对象 / 键 / 参数），翻的动作留到渲染期。
 *
 * ⚠ 灵敏度上限：只看**同一行**的实参表。跨行的 `setX(\n t('k')\n)`，
 * 以及 `const s = t('k'); setX(s)` 这类转手传递，它都看不见。
 */
test('没有把 t() 的结果直接存进 state（state 只置位时求值一次，切语言不重算）', () => {
  const offenders: string[] = [];
  const visited = eachSrcLine((line, where, n) => {
    const s = line.trim();
    if (s.startsWith('//') || s.startsWith('*') || s.startsWith('/*')) return;
    if (/(?:\bset[A-Z]\w*|\buseState)\([^)]*\bt\(/.test(line)) offenders.push(`${where}:${n}`);
  });
  assert.ok(
    visited >= MIN_SRC_FILES,
    `walk 只访问到 ${visited} 个文件（下限 ${MIN_SRC_FILES}）⇒ 下面那条 deepEqual 恒真（空转）：` +
      `要么过滤器改坏了，要么那三个根目录成了空壳`,
  );
  assert.deepEqual(offenders, [], `这些地方把翻好的文案存进了 state，切语言后不会变：${offenders.join(', ')}`);
});
