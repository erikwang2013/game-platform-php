/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * aetherupload 两步协议的契约用例。
 *
 * 用的是**自己这份 fetch 替身**（不是 apiStub 的信封替身）：这两个端点回的
 * 是 `{error, savedPath}` 而**不是** `{code,message,data}` —— 拿信封替身测等于
 * 把「响应形状」这个最容易写错的东西换成假的。
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { tokens } from './api.ts';
import { installLocalStorage } from './apiStub.ts';
import {
  ACCEPT,
  FILE_PREFIX,
  MAX_BYTES,
  UploadError,
  chunks,
  chunkBody,
  failText,
  fileBlob,
  fileUrl,
  preprocessBody,
  readUrl,
  uploadImage,
  type Pre,
} from './upload.ts';

installLocalStorage();

/* ---------------- fetch 替身：按 URL 分发，自己记录每次调用 ---------------- */

type Hit = { url: string; body: unknown; headers: Headers };
let hits: Hit[] = [];
/** 每次 preprocess / uploading 的回包，用例里改写 */
let preReply: Record<string, unknown> = {};
let upReply: Record<string, unknown> = {};
/** fileBlob 用：GET 的回包 */
let getReply: { blob: Blob; ok?: boolean } = { blob: new Blob([], { type: 'image/png' }) };

Object.defineProperty(globalThis, 'fetch', {
  configurable: true,
  writable: true,
  value: (url: string, init?: RequestInit) => {
    hits.push({
      url,
      body: init?.body,
      headers: new Headers(init?.headers),
    });
    if (init?.method === 'POST') {
      const reply = url.endsWith('/preprocess') ? preReply : upReply;
      return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(reply) });
    }
    return Promise.resolve({
      ok: getReply.ok ?? true,
      status: 200,
      blob: () => Promise.resolve(getReply.blob),
    });
  },
});

const reset = () => {
  hits = [];
  preReply = {};
  upReply = {};
};

/** FormData → 普通对象（值是 File 的保留 File 本身，好断言文件名） */
const formEntries = (fd: unknown): Record<string, unknown> => {
  assert.ok(fd instanceof FormData, 'body 必须是 FormData');
  const out: Record<string, unknown> = {};
  for (const [k, v] of fd) out[k] = v;
  return out;
};

/* ---------------- 纯函数 ---------------- */

test('preprocessBody：字段名与服务端 validator 一一对应，resource_hash 必须在场（空串）', () => {
  const p = preprocessBody({ name: 'a.png', size: 1234 });
  assert.equal(p.get('resource_name'), 'a.png');
  assert.equal(p.get('resource_size'), '1234');
  assert.equal(p.get('locale'), 'zh');
  assert.equal(p.get('group'), 'image');
  // ⚠ 判据是 has 不是 get：服务端规则是 present，缺键与空串在 get() 上都回 ''，只有 has 分得开
  assert.equal(p.has('resource_hash'), true);
  assert.equal(p.get('resource_hash'), '');
});

test('chunkBody：chunk_index 从 1 起，其余元数据原样带回', () => {
  const pre: Pre = {
    chunkSize: 100,
    groupSubDir: '202610',
    resourceTempBaseName: 'abc',
    resourceExt: 'png',
    savedPath: '',
  };
  const b = chunkBody(pre, 1, 3);
  // 0 起会被服务端当成重传直接吞掉（lastChunkIndex + 1 校验），这条钉的就是「不是 0」
  assert.equal(b.get('chunk_index'), '1');
  assert.equal(b.get('chunk_total'), '3');
  assert.equal(b.get('resource_ext'), 'png');
  assert.equal(b.get('resource_temp_basename'), 'abc');
  assert.equal(b.get('group_subdir'), '202610');
  assert.equal(b.get('group'), 'image');
  assert.equal(b.get('locale'), 'zh');
  assert.equal(b.has('resource_hash'), true);
});

test('chunks：整除、有余数、空文件、以及 chunkSize 退化时都不能死循环', () => {
  assert.deepEqual(chunks(250, 100), [
    { start: 0, end: 100 },
    { start: 100, end: 200 },
    { start: 200, end: 250 },
  ]);
  assert.deepEqual(chunks(200, 100), [
    { start: 0, end: 100 },
    { start: 100, end: 200 },
  ]);
  // 空文件也回一块：chunk_total 至少为 1，否则一个请求都不发、savedPath 永远是空
  assert.deepEqual(chunks(0, 100), [{ start: 0, end: 0 }]);
  // 服务端 chunkSize 回 0 / 非数字时退化成单块 —— 不退化就是 for 里 start 永不前进
  assert.deepEqual(chunks(300, 0), [{ start: 0, end: 300 }]);
  assert.deepEqual(chunks(300, Number('abc')), [{ start: 0, end: 300 }]);
});

test('failText：只有 error 恰为 0 才算成功，消息串/401 信封/畸形响应都要分得开', () => {
  assert.equal(failText({ error: 0 }), '');
  assert.equal(failText({ error: '0' }), '');
  assert.equal(failText({ error: '文件类型不被允许' }), '文件类型不被允许');
  // 401 走的是统一信封，**没有 error 字段** —— 按 falsy 判成功会把 undefined 当成功放过去
  assert.match(failText({ code: 401, message: '未登录' }), /登录状态已失效/);
  assert.equal(failText({}), '上传失败，请重试');
  assert.equal(failText({ error: null }), '上传失败，请重试');
});

test('fileUrl / readUrl：落库值是相对地址；读取时只取最后一段拼回前缀', () => {
  assert.equal(fileUrl('image_202610_abc.png'), `${FILE_PREFIX}image_202610_abc.png`);
  // 库里可能存的是「前缀 + savedPath」（C 端写法），也可能存裸 savedPath：两种都要回同一个读取地址
  assert.equal(readUrl(`${FILE_PREFIX}image_202610_abc.png`), `${FILE_PREFIX}image_202610_abc.png`);
  assert.equal(readUrl('image_202610_abc.png'), `${FILE_PREFIX}image_202610_abc.png`);
  // 第三方 OAuth 的历史值是绝对地址，原样用（不能拼前缀）
  assert.equal(readUrl('https://cdn.example.com/a.png'), 'https://cdn.example.com/a.png');
});

test('常量与服务端白名单同值（5MB、无 svg）', () => {
  assert.equal(MAX_BYTES, 5242880);
  assert.equal(ACCEPT.includes('svg'), false);
  assert.ok(ACCEPT.includes('image/webp'));
});

/* ---------------- 两步直传 ---------------- */

test('uploadImage：单块 → 两步各一次，回的是落库值（相对地址）', async () => {
  reset();
  tokens.set('at1', 'rt1');
  preReply = { error: 0, chunkSize: 1000000, groupSubDir: '202610', resourceTempBaseName: 'abc', resourceExt: 'png', savedPath: '' };
  upReply = { error: 0, savedPath: 'image_202610_abc.png' };

  const out = await uploadImage(new File([new Uint8Array(10)], 'a.png', { type: 'image/png' }));

  assert.equal(out, `${FILE_PREFIX}image_202610_abc.png`);
  assert.equal(hits.length, 2);
  assert.equal(hits[0]!.url, '/api/v1/aetherupload/preprocess');
  assert.equal(hits[1]!.url, '/api/v1/aetherupload/uploading');
  // 必须带 token：这两条路由挂的是 UserAuth
  assert.equal(hits[1]!.headers.get('Authorization'), 'Bearer at1');
  // ⚠ 不能自己设 Content-Type：multipart 的 boundary 由 fetch 生成，手工设会把 body 解析坏
  assert.equal(hits[1]!.headers.get('Content-Type'), null);

  const fd = formEntries(hits[1]!.body);
  const chunk = fd['resource_chunk'];
  assert.ok(chunk instanceof File);
  assert.equal(chunk.name, 'a.png'); // 没有 filename 的 part 会被 PHP 解析成普通字段
  assert.equal(chunk.type, 'image/png');
  assert.equal(fd['chunk_index'], '1');
  assert.equal(fd['chunk_total'], '1');
});

test('uploadImage：文件大于 chunkSize 时按序切块，chunk_index 从 1 递增到 chunk_total', async () => {
  reset();
  tokens.set('at1', 'rt1');
  preReply = { error: 0, chunkSize: 100, groupSubDir: '202610', resourceTempBaseName: 'abc', resourceExt: 'png', savedPath: '' };
  upReply = { error: 0, savedPath: 'image_202610_abc.png' };

  await uploadImage(new File([new Uint8Array(250)], 'a.png', { type: 'image/png' }));

  assert.equal(hits.length, 4); // 1 次 preprocess + 3 块
  const idx = hits.slice(1).map((h) => formEntries(h.body)['chunk_index']);
  assert.deepEqual(idx, ['1', '2', '3']);
  const totals = hits.slice(1).map((h) => formEntries(h.body)['chunk_total']);
  assert.deepEqual(totals, ['3', '3', '3']);
  const sizes = hits.slice(1).map((h) => (formEntries(h.body)['resource_chunk'] as File).size);
  assert.deepEqual(sizes, [100, 100, 50]);
});

test('uploadImage：preprocess 就回了 savedPath（秒传）时不再发块', async () => {
  reset();
  tokens.set('at1', 'rt1');
  preReply = { error: 0, chunkSize: 100, savedPath: 'image_202610_hit.png' };

  const out = await uploadImage(new File([new Uint8Array(10)], 'a.png', { type: 'image/png' }));

  assert.equal(out, `${FILE_PREFIX}image_202610_hit.png`);
  assert.equal(hits.length, 1);
});

test('uploadImage：服务端 error 是消息串时抛 UploadError，原文透出', async () => {
  reset();
  tokens.set('at1', 'rt1');
  preReply = { error: 0, chunkSize: 100, groupSubDir: '202610', resourceTempBaseName: 'abc', resourceExt: 'svg', savedPath: '' };
  upReply = { error: '资源类型不被允许' };

  await assert.rejects(
    () => uploadImage(new File([new Uint8Array(10)], 'a.svg', { type: 'image/svg+xml' })),
    (e: unknown) => e instanceof UploadError && e.message === '资源类型不被允许',
  );
});

test('uploadImage：上传回包里没有 savedPath 时抛错，绝不返回半截地址', async () => {
  reset();
  tokens.set('at1', 'rt1');
  preReply = { error: 0, chunkSize: 100, groupSubDir: '202610', resourceTempBaseName: 'abc', resourceExt: 'png', savedPath: '' };
  upReply = { error: 0 };

  await assert.rejects(
    () => uploadImage(new File([new Uint8Array(10)], 'a.png', { type: 'image/png' })),
    (e: unknown) => e instanceof UploadError && e.message.includes('未返回路径'),
  );
});

/* ---------------- 读取 ---------------- */

test('fileBlob：成功回字节；失败是 HTTP 200 + JSON 信封 ⇒ 必须按 blob.type 分流', async () => {
  reset();
  tokens.set('at1', 'rt1');

  getReply = { blob: new Blob([new Uint8Array([1, 2, 3])], { type: 'image/png' }) };
  const b = await fileBlob('image_202610_abc.png');
  assert.equal(b.type, 'image/png');
  assert.equal(hits[0]!.url, `${FILE_PREFIX}image_202610_abc.png`);
  assert.equal(hits[0]!.headers.get('Authorization'), 'Bearer at1');

  // 403 时服务端回的是信封 JSON，responseType blob 收成 image/png 的图去喂 createObjectURL
  // 就是一张永远碎掉的图 —— 这条钉的是「json 必须当错误抛」
  getReply = { blob: new Blob([JSON.stringify({ code: 403, message: '无权访问该文件' })], { type: 'application/json' }) };
  await assert.rejects(
    () => fileBlob('image_202610_abc.png'),
    (e: unknown) => e instanceof UploadError && e.message === '无权访问该文件',
  );
});
