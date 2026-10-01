/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { DEFAULT_CODE, LANGUAGES, LOCALE_KEY, currentCode, resolve, setCode, t } from './index.ts';
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

test('查表：当前语言命中自己的表，其余 11 种（无表）回落英文', () => {
  setCode('en');
  assert.equal(t('nav.users'), 'Users');
  setCode('zh');
  assert.equal(t('nav.users'), '用户');
  // ja/ko/… 不建表：查不到走 en（与两棵 flutter 的现状一致，是已拍板路径的已知代价）
  setCode('ja');
  assert.equal(t('nav.users'), 'Users');
  assert.equal(currentCode(), 'ja');
  // 落值前先 resolve ⇒ currentCode 永远在 13 个短码之内。全码 `zh-CN` 也**不**特判：
  // 只有后端 `Locale::normalize()` 认它（Accept-Language 的写法），本树偏好里只写短码
  //（切换器的候选项来自 LANGUAGES），多认一种写法就是多一处会漂的对齐规则。
  setCode('zh-CN');
  assert.equal(currentCode(), 'en');
  setCode('en');
});

test('查表：en 与 zh 的键集逐项相同（另一重是同名类型在 tsc 上的强制）', () => {
  assert.deepEqual(Object.keys(zh).sort(), Object.keys(en).sort());
  // 两张表的键都不带空串值：空译文会让界面整块消失，比没翻译更难查。
  // 例外只有 tab.none：它是「这一组没有标签」的**哨兵**，空串就是它的正确取值（TabPage 的回落）。
  const EMPTY_OK = new Set(['tab.none']);
  for (const [key, value] of Object.entries(zh)) assert.ok(value !== '' || EMPTY_OK.has(key), `zh.${key} 是空串`);
  for (const [key, value] of Object.entries(en)) assert.ok(value !== '' || EMPTY_OK.has(key), `en.${key} 是空串`);
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
