/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { api } from './api.ts';
import { countryOptions } from './countryOptions.ts';
import { calls, installFetch } from './apiStub.ts';

let reply: { ok: boolean; code: number; message?: string; data?: unknown } = { ok: true, code: 0, data: {} };
installFetch(() => reply);

/* KYC「国家/地区」下拉：选项来自 `GET /country/list`，且**不在选项里的旧值仍要显示**。
   全套钉子就是为「把取值改回自由文本框」留的：那样第三条的源码断言会红。 */

test('选项补旧值：不在列表里补一条，在列表里/空值不补，且不动入参', () => {
  assert.deepEqual(countryOptions(['CN', 'US'], 'Chna'), ['CN', 'US', 'Chna']);
  assert.deepEqual(countryOptions(['CN', 'US'], 'CN'), ['CN', 'US']);
  assert.deepEqual(countryOptions(['CN'], ''), ['CN']);
  assert.deepEqual(countryOptions(['CN'], '   '), ['CN']);
  // 不能就地改：codes 是从 useAsync 的 data 里 map 出来的，改它等于改缓存
  const codes = ['CN'];
  countryOptions(codes, 'Chna');
  assert.deepEqual(codes, ['CN']);
});

test('api.countries() 打公开端点 /country/list，回包形状原样解出', async () => {
  calls.length = 0;
  reply = {
    ok: true,
    code: 0,
    data: { list: [{ country_code: 'CN', currency: 'CNY', min_deposit: '10.0000' }] },
  };
  assert.deepEqual(await api.countries(), {
    list: [{ country_code: 'CN', currency: 'CNY', min_deposit: '10.0000' }],
  });
  assert.equal(calls[0]!.url, '/api/v1/country/list');
  assert.equal(calls[0]!.init?.method, undefined); // GET；公开端点，不带 body
});

test('Kyc.tsx 真的把 country 接到了下拉（不是留下自由文本框）', () => {
  // 本树没有 DOM 测试基建（`node --test` 只跑 .ts，无 jsdom）⇒ 页面接线只能在源码层钉，
  // 手法与 accountDeletion.test.ts 相同：只切出该字段那一块断言，别处不碰。
  const src = readFileSync(new URL('../pages/Kyc.tsx', import.meta.url), 'utf8');
  // 锚点是**键名**不是旧文案：文案已经进了 i18n 表（本批抽走的东西），键名跨语言稳定。
  // ⚠ 先显式断言「找到了」再断言顺序：`indexOf` 找不到时返回 -1，`slice(-1, …)` 会在一段
  // 荒唐区间上求值而几条 match 仍可能绿 —— 那是「分母塌了但断言仍绿」，键名一写错就中招。
  const start = src.indexOf("t('kyc.field_country')");
  const end = src.indexOf('{PHOTOS.map');
  assert.ok(start >= 0, "Kyc.tsx 里找不到 t('kyc.field_country')");
  assert.ok(end >= 0, 'Kyc.tsx 里找不到 {PHOTOS.map');
  assert.ok(end > start, 'Kyc.tsx 里找不到国家/地区字段块');

  const block = src.slice(start, end);
  assert.match(block, /<select/);
  assert.match(block, /countryOptions\(codes, country\)/);
  assert.doesNotMatch(block, /type="text"/);
  // 选项来自端点，不是页面里写死的表
  assert.match(src, /const countries = useAsync\(\(\) => api\.countries\(\), \[\]\);/);
});
