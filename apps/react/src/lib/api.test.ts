/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { ApiError, api, language, setUnauthorizedHandler, tokens } from './api.ts';

/* localStorage / fetch 是浏览器全局，node --test 里用最小替身顶掉；本树无测试依赖，够用即可 */
const store = new Map<string, string>();
Object.defineProperty(globalThis, 'localStorage', {
  configurable: true,
  value: {
    getItem: (k: string) => store.get(k) ?? null,
    setItem: (k: string, v: string) => void store.set(k, v),
    removeItem: (k: string) => void store.delete(k),
    clear: () => store.clear(),
    key: () => null,
    length: 0,
  },
});

const calls: { url: string; init: RequestInit | undefined }[] = [];
let reply: { ok: boolean; code: number; message?: string; data?: unknown } = { ok: true, code: 0, data: {} };
Object.defineProperty(globalThis, 'fetch', {
  configurable: true,
  writable: true,
  value: (url: string, init?: RequestInit) => {
    calls.push({ url, init });
    return Promise.resolve({
      ok: reply.ok,
      json: () => Promise.resolve({ code: reply.code, message: reply.message ?? '', data: reply.data }),
    });
  },
});

test('tokens/language 落 localStorage，language 缺省 en', () => {
  tokens.set('a1', 'r1');
  assert.equal(tokens.access(), 'a1');
  assert.equal(language.get(), 'en');
  language.set('zh-CN');
  assert.equal(language.get(), 'zh-CN');
  tokens.clear();
  assert.equal(tokens.access(), null);
});

test('信封 code=0 解出 data，请求带 X-Language + Authorization', async () => {
  tokens.set('tok', 'r1');
  language.set('zh-CN');
  calls.length = 0;
  reply = { ok: true, code: 0, data: { users: 7 } };
  assert.deepEqual(await api.stats(), { users: 7 });
  assert.equal(calls[0]!.url, '/api/v1/platform/stats');
  const headers = new Headers(calls[0]!.init?.headers);
  assert.equal(headers.get('X-Language'), 'zh-CN');
  assert.equal(headers.get('Authorization'), 'Bearer tok');
});

test('信封 code≠0 抛 ApiError，带服务端 code/message', async () => {
  reply = { ok: true, code: 403, message: '无权限' };
  await assert.rejects(
    () => api.stats(),
    (e: unknown) => e instanceof ApiError && e.code === 403 && e.message === '无权限',
  );
});

test('code=401 且无 refresh_token：清 token、回调登出、抛 401', async () => {
  tokens.set('tok', '');
  let kicked = 0;
  setUnauthorizedHandler(() => {
    kicked += 1;
  });
  calls.length = 0;
  reply = { ok: true, code: 401 };
  await assert.rejects(
    () => api.stats(),
    (e: unknown) => e instanceof ApiError && e.code === 401,
  );
  assert.equal(kicked, 1);
  assert.equal(tokens.access(), null);
  assert.equal(calls.length, 1); // 无 refresh_token ⇒ 不发刷新请求
});
