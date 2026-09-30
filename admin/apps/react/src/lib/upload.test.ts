/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import {
  PREPROCESS_PATH,
  UPLOADING_PATH,
  chunkFields,
  chunkRanges,
  displayUrl,
  preprocessFields,
  uploadImage,
} from './upload.ts';

/**
 * 字段名/取值就是与插件的接口契约（`UploadController` 按名字取，写错只回一句「参数无效」）：
 * deepEqual 同时钉住键集合与取值，多一个少一个都红。
 */
test('preprocessFields：字段名照服务端契约（hash 必须传空串、group=image）', () => {
  assert.deepEqual(preprocessFields('封面 图.png', 204800), {
    resource_name: '封面 图.png',
    resource_size: '204800',
    resource_hash: '',
    locale: 'zh_CN',
    group: 'image',
  });
});

test('chunkFields：与 preprocess 响应里的键对齐，chunk_index 从 1 起（服务端只收上一块 +1）', () => {
  const pre = { chunkSize: 1000000, groupSubDir: '202610', resourceTempBaseName: 'tmp123', resourceExt: 'png' };
  assert.deepEqual(chunkFields(pre, 1, 3), {
    resource_ext: 'png',
    chunk_total: '3',
    chunk_index: '1',
    resource_temp_basename: 'tmp123',
    group_subdir: '202610',
    locale: 'zh_CN',
    group: 'image',
    resource_hash: '',
  });
  // 响应缺字段（不该发生）时给空串，而不是 undefined 进 FormData
  assert.deepEqual(chunkFields({}, 2, 2), {
    resource_ext: '',
    chunk_total: '2',
    chunk_index: '2',
    resource_temp_basename: '',
    group_subdir: '',
    locale: 'zh_CN',
    group: 'image',
    resource_hash: '',
  });
});

test('chunkRanges：按服务端 chunkSize 切、整块数不尾随空块；拿不到 chunkSize 就整文件一块', () => {
  // 0 字节：仍发一块（服务端 filterBySize 自己拒；客户端不另立规则）
  assert.deepEqual(chunkRanges(0, 1000000), [[0, 0]]);
  assert.deepEqual(chunkRanges(500, 1000000), [[0, 500]]);
  // 整除：不尾随空块（空块会被服务端当成 chunkIndex > last+1 之外的怪状态）
  assert.deepEqual(chunkRanges(1000000, 1000000), [[0, 1000000]]);
  assert.deepEqual(chunkRanges(2500000, 1000000), [
    [0, 1000000],
    [1000000, 2000000],
    [2000000, 2500000],
  ]);
  assert.deepEqual(chunkRanges(700, 0), [[0, 700]]);
});

test('displayUrl：绝对 URL（C 端用户也要能看这张封面，相对路径会指到 C 端主机上）', () => {
  assert.equal(
    displayUrl('image_202610_ab12cd.png', 'http://admin.games.test'),
    'http://admin.games.test/admin/v1/aetherupload/display/image_202610_ab12cd.png',
  );
});

/** 一步打桩记录。 */
type Call = { url: string; auth: string | null; form: Record<string, unknown> | string };

/**
 * 跑 uploadImage，同时把 globalThis.fetch 换成按 url 出响应的桩（api.ts 还会去 localStorage
 * 读令牌 —— node 没有这个全局，一并给个常量桩）。跑完必还原，别漏到别的用例上。
 */
async function run(reply: (url: string, index: number) => Record<string, unknown>, file: File, origin: string): Promise<{ calls: Call[]; savedPath?: string; error?: unknown }> {
  const realFetch = globalThis.fetch;
  const calls: Call[] = [];
  globalThis.fetch = (async (url: string, init: RequestInit = {}) => {
    const headers = (init.headers ?? {}) as Record<string, string>;
    const body = init.body;
    calls.push({
      url: String(url),
      auth: headers.Authorization ?? null,
      form:
        body instanceof FormData ? Object.fromEntries(body.entries()) : body instanceof URLSearchParams ? [...body.entries()].map(([k, v]) => `${k}=${v}`).join('&') : String(body ?? ''),
    });
    return new Response(JSON.stringify(reply(String(url), calls.length)), { status: 200, headers: { 'content-type': 'application/json' } });
  }) as typeof fetch;
  Object.defineProperty(globalThis, 'localStorage', { value: { getItem: () => 'test-token' }, configurable: true });
  try {
    return { calls, savedPath: await uploadImage(file, origin) };
  } catch (cause) {
    return { calls, error: cause };
  } finally {
    globalThis.fetch = realFetch;
    Reflect.deleteProperty(globalThis, 'localStorage');
  }
}

test('uploadImage：preprocess → 逐块 uploading，返回绝对展示 URL，且带 Bearer 头', async () => {
  const file = new File([new Uint8Array(2500)], 'cover.png', { type: 'image/png' });
  const { calls, savedPath } = await run(
    (url, index) =>
      url === PREPROCESS_PATH
        ? { error: 0, chunkSize: 1000, groupSubDir: '202610', resourceTempBaseName: 'tmp1', resourceExt: 'png', savedPath: '' }
        : { error: 0, savedPath: index === 4 ? 'image_202610_hash.png' : '' },
    file,
    'http://admin.games.test',
  );

  assert.equal(savedPath, 'http://admin.games.test/admin/v1/aetherupload/display/image_202610_hash.png');
  assert.equal(calls.length, 4); // preprocess + 3 块（2500 字节 / chunkSize 1000）
  assert.equal(calls[0].url, PREPROCESS_PATH);
  assert.equal(calls[0].form, 'resource_name=cover.png&resource_size=2500&resource_hash=&locale=zh_CN&group=image');
  assert.deepEqual(
    calls.slice(1).map((call) => [call.form.chunk_index, call.form.chunk_total]),
    [
      ['1', '3'],
      ['2', '3'],
      ['3', '3'],
    ],
  );
  assert.deepEqual([...calls].map((call) => call.auth), ['Bearer test-token', 'Bearer test-token', 'Bearer test-token', 'Bearer test-token']);
  assert.deepEqual([...calls].map((call) => call.url).slice(1), [UPLOADING_PATH, UPLOADING_PATH, UPLOADING_PATH]);
});

test('uploadImage：服务端 error 原样上抛（非 0 时它是给人看的句子，不换成自编文案）', async () => {
  const file = new File([new Uint8Array(10)], 'cover.png', { type: 'image/png' });
  const { error } = await run(() => ({ error: '文件类型不允许', chunkSize: 0 }), file, 'http://admin.games.test');
  assert.equal((error as Error).message, '文件类型不允许');
});
