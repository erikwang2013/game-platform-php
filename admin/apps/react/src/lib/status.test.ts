/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { LANGUAGES, TABLES, setCode, t } from '../i18n/index.ts';
import { en } from '../i18n/en.ts';
import type { Column } from '../components/DataTable.tsx';
import { STATUS_ENUMS, statusEnumsFor, statusText, withStatusLabels } from './status.ts';

/**
 * 状态列的「码 → 文案」表。钉的是**值域本身**，不是「有没有映射」：
 * 每一条映射的取值都逐点核过 install/install.sql 的列注释与写入侧代码（见 status.ts 顶部注释）。
 * 这张表写错的后果是**静默**的 —— 多一个少一个码，只是那一格回落成数字，页面不报错。
 */

/** 每个枚举的**完整**值域：多一个码 = 线上出现了没被翻译的值；少一个码 = 有值永远露原始编码 */
const DOMAINS: Record<string, Record<string, string>> = {
  // 0/1 布尔族：DDL 注释 + 写入侧一律 `(int)` 0|1（见 status.ts 逐条出处）
  enabled: { '1': 'st.enabled', '0': 'st.disabled' },
  listing: { '1': 'st.listed', '0': 'st.unlisted' },
  publish: { '1': 'st.published', '0': 'st.draft' },
  user_state: { '1': 'st.active', '0': 'st.banned' },
  // 群组：1=正常 0=解散（**不是**「启用/停用」，措辞不能照抄别的布尔族）
  group_state: { '1': 'st.group_active', '0': 'st.group_dissolved' },
  // 服务器：4 个码，且顺序不是数值序（0 维护不是「第一个」）
  server: { '0': 'st.server_maintenance', '1': 'st.server_normal', '2': 'st.server_hot', '3': 'st.server_new' },
  // 活动：2=已结束（DDL 只注释了 0/1，2 由写入侧代码补，最容易漏）
  activity: { '0': 'st.disabled', '1': 'st.enabled', '2': 'st.ended' },
  // 风控簇：数值序与业务序相反
  cluster: { '1': 'st.cluster_watching', '2': 'st.cluster_actioned', '0': 'st.cluster_false_positive' },
  review: { pending: 'st.pending', approved: 'st.approved', rejected: 'st.rejected' },
  // 工单：DDL 注释只列了 open/closed 两个，代码实际写 4 个
  ticket: { open: 'st.ticket_open', waiting: 'st.ticket_waiting', replied: 'st.ticket_replied', closed: 'st.ticket_closed' },
  // 提现：DDL 注释漏了 processing
  withdraw: {
    pending: 'st.pending',
    approved: 'st.approved',
    processing: 'st.processing',
    rejected: 'st.rejected',
    completed: 'st.completed',
  },
  // 打款子状态：空串不在表里（没打款时该格就是空，回落成「—」）
  payout: { processing: 'st.processing', success: 'st.payout_success', failed: 'st.payout_failed' },
  anticheat: { open: 'st.ac_open', confirmed: 'st.ac_confirmed', whitelisted: 'st.ac_whitelisted', closed: 'st.ac_closed' },
};

test('值域逐码对齐后端（表里的码集与上面 DOMAINS 完全一致，不多不少）', () => {
  assert.deepEqual(
    Object.keys(STATUS_ENUMS).sort(),
    Object.keys(DOMAINS).sort(),
    '枚举清单本身变了：新增/删除枚举必须同时核后端并更新本测试',
  );
  for (const [id, domain] of Object.entries(DOMAINS)) {
    assert.deepEqual(
      Object.keys(STATUS_ENUMS[id] ?? {}).sort(),
      Object.keys(domain).sort(),
      `${id} 的码集与核对过的值域不一致`,
    );
    for (const [value, key] of Object.entries(domain)) {
      assert.equal(STATUS_ENUMS[id]?.[value], key, `${id}.${value} 的文案键不是 ${key}`);
    }
  }
});

test('每个映射的键都真在 en 表里（否则整条回落链是「原键透传」的假绿）', () => {
  for (const [id, domain] of Object.entries(STATUS_ENUMS)) {
    for (const key of Object.values(domain)) {
      assert.ok(key in en, `${id} 指向 ${key}，但 en 表里没有这个键 —— 页面会直接显示键名`);
    }
  }
});

test('st.* 键只此一份：en 表里的 st.* 恰好等于所有枚举引用到的键（无死键、无笔误）', () => {
  const used = new Set<string>();
  for (const domain of Object.values(STATUS_ENUMS)) for (const key of Object.values(domain)) used.add(key);
  const declared = Object.keys(en).filter((key) => key.startsWith('st.'));
  assert.deepEqual(declared.sort(), [...used].sort(), 'en 表的 st.* 与枚举引用的键集必须相等');
  // 反向再点一次名：没有这条，`declared` 少一个键会被上一条的 deepEqual 抓到，但**两边同时多**抓不到
  assert.equal(declared.length, 33);
});

/**
 * 与英文逐字相同**而且是正确的**那几条 —— 借词，不是漏改。
 * 名单要显式列出来，否则这条断言要么放跑真漏改（只管非空），要么把「Normal 就是德语词」判成红。
 * 判据是「这个词在该语言里本来就是这么写」，不是「看着像英文」。
 */
const SAME_AS_ENGLISH_OK = new Set(['st.server_normal', 'st.server_maintenance']);

test('13 张语言表里每个 st.* 键都有自己的文案（不是靠 t() 回落英文）', () => {
  for (const { code } of LANGUAGES) {
    const table = TABLES[code];
    for (const key of Object.keys(en).filter((k) => k.startsWith('st.'))) {
      const text = table[key];
      // 直接读表、不经 t()：t() 缺键会静默回落英文，那种「绿」正是这条要防的假绿
      assert.ok(typeof text === 'string' && text.length > 0, `${code} 的 ${key} 没落表或为空串`);
      if (code !== 'en' && !SAME_AS_ENGLISH_OK.has(key)) {
        assert.notEqual(text, en[key], `${code} 的 ${key} 与英文逐字相同（复制粘贴漏改？）`);
      }
    }
  }
  // 白名单本身也要钉：删掉一条不许静默通过（上面的循环靠它放行）
  for (const key of SAME_AS_ENGLISH_OK) assert.ok(key in en, `白名单里的 ${key} 不存在`);
});

test('同一枚举内各值的文案互不相同（两个码显示同一句话＝运营看不出区别）', () => {
  for (const [id, domain] of Object.entries(DOMAINS)) {
    const values = Object.values(domain);
    assert.equal(new Set(values).size, values.length, `${id} 内有多对值共用一个文案键`);
    for (const { code } of LANGUAGES) {
      const texts = values.map((key) => TABLES[code][key]);
      assert.equal(new Set(texts).size, texts.length, `${code} 的 ${id} 里有两个码显示同一句话：${texts.join(' / ')}`);
    }
  }
});

test('statusText：命中映射取当前语言的文案，数字码与字符串码等价', () => {
  setCode('zh');
  assert.equal(statusText('listing', 1), t('st.listed'));
  assert.equal(statusText('listing', '1'), t('st.listed'), '后端 JSON 里是数字，别只认字符串');
  assert.equal(statusText('listing', 0), t('st.unlisted'));
  // 当前语言真的生效：换语言取另一张表
  setCode('id');
  assert.equal(statusText('listing', 0), TABLES.id['st.unlisted']);
  setCode('en');
  assert.equal(statusText('listing', 0), en['st.unlisted']);
});

test('statusText：表外的值原样透传，不吞成空白（后端加了新码要看得见）', () => {
  setCode('zh');
  assert.equal(statusText('listing', 2), '2');
  assert.equal(statusText('listing', 'archived'), 'archived');
  // 打款列没打款时是空串/null，两种都该走 cellText 的空值口径
  assert.equal(statusText('payout', ''), '—');
  assert.equal(statusText('payout', null), '—');
  assert.equal(statusText('payout', undefined), '—');
  // 0 是**有值**：某个枚举没有 '0' 这个码时，0 不该被当成空
  assert.equal(statusText('review', 0), '0');
  assert.equal(statusText('payout', 0), '0');
});

test('statusEnumsFor：端点认列，认不出就返回 null（不猜）', () => {
  assert.deepEqual(statusEnumsFor('/admin/v1/game/list'), { status: 'listing' });
  assert.deepEqual(statusEnumsFor('/admin/v1/game/server/list'), { status: 'server' });
  assert.deepEqual(statusEnumsFor('/admin/v1/platform/user/list'), { status: 'user_state' });
  assert.deepEqual(statusEnumsFor('/admin/v1/withdraw/orders'), { status: 'withdraw', payout_status: 'payout' });
  assert.equal(statusEnumsFor('/admin/v1/dashboard'), null);
  assert.equal(statusEnumsFor('/admin/v1/no/such/list'), null);
});

test('statusEnumsFor：/admin/v1/search 一条路径两个值域，按 type 分（不靠猜）', () => {
  // 同一端点，游戏是 1=上架/0=下架，用户是 1=正常/0=封禁 —— 取错就是把「封禁」显示成「下架」
  assert.deepEqual(statusEnumsFor('/admin/v1/search', { type: 'user' }), { status: 'user_state' });
  assert.deepEqual(statusEnumsFor('/admin/v1/search', { type: 'game' }), { status: 'listing' });
  // 缺 type 时不能崩：按游戏那一支走（与 search.tsx 的默认页签一致）
  assert.deepEqual(statusEnumsFor('/admin/v1/search'), { status: 'listing' });
  assert.deepEqual(statusEnumsFor('/admin/v1/search', {}), { status: 'listing' });
});

test('statusEnumsFor 引用的每个枚举 id 都真在表里（写错 id ＝ 静默露原始码）', () => {
  const paths = [
    '/admin/v1/game/list',
    '/admin/v1/game/server/list',
    '/admin/v1/game/category/list',
    '/admin/v1/leaderboard/list',
    '/admin/v1/achievement/list',
    '/admin/v1/activities/list',
    '/admin/v1/announcement/list',
    '/admin/v1/platform/user/list',
    '/admin/v1/identity/list',
    '/admin/v1/ticket/list',
    '/admin/v1/user',
    '/admin/v1/role',
    '/admin/v1/payment/method/list',
    '/admin/v1/coupon/list',
    '/admin/v1/risk/rule/list',
    '/admin/v1/anticheat/events',
    '/admin/v1/risk/clusters',
    '/admin/v1/cdn/provider/list',
    '/admin/v1/country/config/list',
    '/admin/v1/groups',
    '/admin/v1/withdraw/orders',
    ['/admin/v1/search', { type: 'user' }] as const,
  ];
  for (const entry of paths) {
    const [path, query] = Array.isArray(entry) ? entry : [entry, undefined];
    const enums = statusEnumsFor(path, query as Record<string, unknown> | undefined);
    assert.ok(enums !== null, `${path} 认不出枚举`);
    for (const id of Object.values(enums)) {
      assert.ok(STATUS_ENUMS[id] !== undefined, `${path} 指向不存在的枚举 ${id}`);
    }
  }
});

test('withStatusLabels：列改名为影子列，行里的原值一个字节都不动', () => {
  const columns: Column[] = [
    { key: 'id', label: 'f.id' },
    { key: 'status', label: 'status' },
    { key: 'amount', label: 'f.amount', align: 'right' },
  ];
  const row = { id: '7', status: 0, amount: '12.00' };
  const before = JSON.stringify(row);

  const next = withStatusLabels(columns, { status: 'listing' });

  assert.deepEqual(
    next.map((column) => column.key),
    ['id', 'status_label', 'amount'],
    '影子列必须紧挨原位、原名让出',
  );
  // 返回值不是字符串的列**原对象**透传：行尾动作 / 树标记那些 render 不能被重建
  assert.equal(next[0], columns[0]);
  assert.equal(next[2], columns[2]);
  setCode('zh');
  assert.equal(next[1]?.render?.(row), t('st.unlisted'));
  assert.equal(next[1]?.label, 'status', '表头文案沿用原来那条键');
  assert.equal(JSON.stringify(row), before, '行对象不许被写：行内动作 / 批量审核 / 启停都读原值');
  // 端点没有状态列时**同一个数组**返回，谁也不动
  assert.equal(withStatusLabels(columns, null), columns);
});

test('withStatusLabels：表外的值走透传，不是空白', () => {
  const next = withStatusLabels([{ key: 'status', label: 'status' }], { status: 'listing' });
  assert.equal(next[0]?.render?.({ status: 9 }), '9');
  assert.equal(next[0]?.render?.({}), '—');
});
