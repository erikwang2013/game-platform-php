/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { DEFAULT_CODE, LANGUAGES, LOCALE_KEY, TABLES, currentCode, isRtl, resolve, setCode, t } from './index.ts';
import { en } from './en.ts';
import { zh } from './zh.ts';

/**
 * 本树没有 DOM 底座（`npm test` 是 `node --test`，无 jsdom），所以这里全在**纯逻辑层**：
 * 语言清单、查表/回落、占位符、持久化。`useI18n` 的组件侧绑定与切换器的交互**没有覆盖**
 * （见同目录 wiring.test.ts 只做源码级断言）。
 */

/** 假 localStorage —— 真浏览器里由 window 提供；node 下不存在，模块内读偏好走的是 catch 分支。 */
type Store = Record<string, string>;
const stub = (initial: Store = {}) => {
  const store: Store = { ...initial };
  (globalThis as unknown as { localStorage: unknown }).localStorage = {
    getItem: (key: string) => store[key] ?? null,
    setItem: (key: string, value: string) => {
      store[key] = value;
    },
  };
  return store;
};

test('语言清单：13 种，短码与母语名逐项锁定（镜像后端 Locale::SUPPORTED）', () => {
  assert.deepEqual(
    LANGUAGES.map((item) => item.code),
    ['en', 'zh', 'ja', 'ko', 'ru', 'de', 'fr', 'es', 'pt', 'hi', 'ar', 'bn', 'id'],
  );
  // 母语名照抄后端/两棵 flutter 的写法：改成译名（如「日语」）就等于让看不懂当前界面的用户选不对
  assert.deepEqual(
    LANGUAGES.map((item) => item.native),
    [
      'English',
      '简体中文',
      '日本語',
      '한국어',
      'Русский',
      'Deutsch',
      'Français',
      'Español',
      'Português',
      'हिन्दी',
      'العربية',
      'বাংলা',
      'Bahasa Indonesia',
    ],
  );
  // 默认语言就是清单第一项（resolve 的回落目标），两者漂移会让「认不出的码」落成语义不明的值
  assert.equal(LANGUAGES[0].code, DEFAULT_CODE);
  assert.equal(new Set(LANGUAGES.map((item) => item.code)).size, LANGUAGES.length, '短码有重复');
});

test('resolve：认不出的码一律回落 en，不抛错', () => {
  assert.equal(resolve('zh').code, 'zh');
  assert.equal(resolve('ja').code, 'ja');
  // 后端已下线的码 / 空值 / 大小写变体：偏好里存着它们时界面要照常起来，不能白屏
  for (const bad of ['xx', 'zh-CN', 'ZH', '', null, undefined, 'en-US']) {
    assert.equal(resolve(bad).code, 'en', `${String(bad)} 没回落 en`);
  }
});

test('查表：13 种语言各查自己的表，没有一种靠回落英文', () => {
  setCode('en');
  assert.equal(t('nav.users'), 'Users');
  setCode('zh');
  assert.equal(t('nav.users'), '用户');
  // 每种语言都必须在自己的表里命中：没注册表的语言会**静默**回落英文 ——
  // 切换器照常列出它、点下去也确实存了偏好，只有文案永远是英文，不看这一条发现不了。
  let translated = 0;
  for (const { code } of LANGUAGES) {
    setCode(code);
    assert.equal(t('nav.users'), TABLES[code]['nav.users'], `${code} 查的不是自己的表`);
    if (TABLES[code]['nav.users'] !== en['nav.users']) translated++;
  }
  assert.equal(translated, 12, '有语言的 nav.users 与英文相同（要么漏翻，要么在回落英文）');
  assert.equal(currentCode(), 'id');
  // 落值前先 resolve ⇒ currentCode 永远在 13 个短码之内。全码 `zh-CN` 也**不**特判：
  // 只有后端 `Locale::normalize()` 认它（Accept-Language 的写法），本树偏好里只写短码
  //（切换器的候选项来自 LANGUAGES），多认一种写法就是多一处会漂的对齐规则。
  setCode('zh-CN');
  assert.equal(currentCode(), 'en');
  setCode('en');
});

test('查表：13 种语言的键集都与 en 逐项相同，且每种都真注册了表', () => {
  const enKeys = Object.keys(en).sort();
  assert.deepEqual(Object.keys(zh).sort(), enKeys);
  // 注册表与语言清单必须同源：少注册一种 = 那个语言永远显示英文（静默），多注册一种 = 死行
  assert.deepEqual(
    Object.keys(TABLES).sort(),
    LANGUAGES.map((item) => item.code).sort(),
    'TABLES 与 LANGUAGES 不是同一批语言',
  );
  for (const { code } of LANGUAGES) {
    const table = TABLES[code];
    assert.ok(table, `${code} 没有注册语言表（选中它 = 永远显示英文）`);
    assert.deepEqual(Object.keys(table).sort(), enKeys, `${code} 的键集与 en 不同`);
    // 译文条数下限：全表 666 键，实测最少的一种（fr）也有 633 条与英文不同。
    // 取 600 当「一眼看得出这还是一张真表」的门槛，不写精确相等 —— 具体数字会随措辞改，那不是契约。
    // en 是基准表本身，跳过（拿它比自己恒为 0）。
    if (code === DEFAULT_CODE) continue;
    const translated = enKeys.filter((key) => table[key] !== (en as Record<string, string>)[key]).length;
    assert.ok(translated > 600, `${code} 只有 ${translated} 条与英文不同，疑似整表照抄英文`);
  }
  // 没有空串值：空译文会让界面整块消失，比没翻译更难查。
  // 例外只有 tab.none：它是「这一组没有标签」的**哨兵**，空串就是它的正确取值（TabPage 的回落）。
  const EMPTY_OK = new Set(['tab.none']);
  for (const { code } of LANGUAGES) {
    for (const [key, value] of Object.entries(TABLES[code])) {
      assert.ok(value !== '' || EMPTY_OK.has(key), `${code}.${key} 是空串`);
    }
  }
});

test('占位符：13 种语言逐键的 {…} 集合与 en 相同（丢一个变量，界面上就出现硬编码的 {name}）', () => {
  const ph = (text: string) => (text.match(/\{\w+\}/g) ?? []).sort().join(',');
  for (const { code } of LANGUAGES) {
    for (const [key, value] of Object.entries(TABLES[code])) {
      assert.equal(ph(value), ph((en as Record<string, string>)[key]), `${code}.${key} 的占位符与 en 不同`);
    }
  }
});

test('占位符：{name} 按参数替换；缺参时原样留着（看得见漏了哪个，而不是静默空掉）', () => {
  setCode('en');
  assert.equal(t('app.service_error', { status: 500 }), 'Service error (HTTP 500)');
  setCode('zh');
  assert.equal(t('app.service_error', { status: 404 }), '服务异常（HTTP 404）');
  // 不带参数 ⇒ 占位符原样留在结果里
  assert.equal(t('app.service_error'), '服务异常（HTTP {status}）');
  // 多传的参数无害（同一段落里换个调用点不必改签名）
  assert.equal(t('app.service_error', { status: 500, extra: 'x' }), '服务异常（HTTP 500）');
  setCode('en');
});

test('查表：表外的键回落键名本身（类型上不可达，靠断言硬闯进来验证这条兜底）', () => {
  setCode('en');
  assert.equal(t('nope.missing' as unknown as 'nav.users'), 'nope.missing');
  setCode('zh');
  assert.equal(t('nope.missing' as unknown as 'nav.users'), 'nope.missing');
  setCode('en');
});

test('查表：某个键当前语言没译文时逐键回落英文（en 是逐键兜底，不是整张表缺失才生效）', () => {
  // 这条用例**会改动共享的 TABLES**（删一个键再放回），所以刻意排在上面所有结构断言之后；
  // finally 里按「原样放回」还原：本来就没有的话不能补一个 `undefined` 进去 ——
  // 那会让后面「键集与 en 相同」的断言看不见这次缺失，等于给变异留了条逃逸通道。
  setCode('ja');
  const saved = TABLES.ja['nav.users'];
  delete TABLES.ja['nav.users'];
  try {
    assert.equal(t('nav.users'), 'Users', '单键缺失没有回落英文');
  } finally {
    if (saved === undefined) delete TABLES.ja['nav.users'];
    else TABLES.ja['nav.users'] = saved;
  }
  assert.equal(t('nav.users'), saved, '删掉的键没还原（后面的用例会跟着读到脏表）');
  setCode('en');
});

test('持久化：setCode 落偏好，存的是归一后的短码而不是原样入参', () => {
  const store = stub();
  setCode('ko');
  assert.equal(store[LOCALE_KEY], 'ko');
  // 认不出的码也**先归一后落盘**：否则偏好里留下表外的值，下次进来 resolve 又回落一次，
  // 界面上「上次选的那个」和实际生效的语言对不上
  setCode('xx');
  assert.equal(store[LOCALE_KEY], 'en');
  setCode('en');
});

test('持久化：重新加载模块时读偏好里的码（认不出的码回落 en，不白屏）', () => {
  // 直接 import 会命中 ESM 缓存（本进程里模块只求值一次），用查询串换一个 URL 拿到**新实例**，
  // 复现「刷新页面」：只有真读到了 localStorage，才能证明偏好不是只写不读。
  const reload = async (saved: string) => {
    stub({ [LOCALE_KEY]: saved });
    return (await import(`./index.ts?reload=${encodeURIComponent(saved)}`)).currentCode() as string;
  };
  return (async () => {
    assert.equal(await reload('bn'), 'bn');
    assert.equal(await reload('zz'), 'en', '认不出的码回落 en');
  })();
});

/**
 * 语言要落到 DOM 上（`lang` + `dir`）——**只翻文案不设 `dir`，阿拉伯语界面仍是 LTR 排版**。
 *
 * 本树无 DOM 底座（`node --test`），所以这里注一个 `document` 桩把这条做成**行为级**：
 * 驱动真的 `setCode()`，读 `documentElement` 上的实际写入。比读源码断言强。
 */
test('切到阿拉伯语会把 documentElement 置为 rtl，其余语言是 ltr', () => {
  const stub = { documentElement: { lang: '', dir: '' } };
  (globalThis as unknown as { document: unknown }).document = stub;
  try {
    setCode('ar');
    assert.equal(stub.documentElement.dir, 'rtl', 'ar 必须镜像书写方向');
    assert.equal(stub.documentElement.lang, 'ar');

    setCode('ja');
    assert.equal(stub.documentElement.dir, 'ltr', '日语是 LTR，别把"非拉丁"都当 RTL');
    assert.equal(stub.documentElement.lang, 'ja');

    setCode('zh');
    assert.equal(stub.documentElement.dir, 'ltr');
  } finally {
    delete (globalThis as unknown as { document?: unknown }).document;
  }
});

test('13 种里恰好只有阿拉伯语是 RTL', () => {
  assert.equal(isRtl('ar'), true);
  const rtl = LANGUAGES.filter((l) => isRtl(l.code)).map((l) => l.code);
  assert.deepEqual(rtl, ['ar'], `RTL 语言集合变了：${JSON.stringify(rtl)}`);
});

test('认不出来的码按 ltr 处理（不误镜像）', () => {
  assert.equal(isRtl('klingon'), false);
});
