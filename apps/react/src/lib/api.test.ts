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

test('充值/提现/兑换写操作：方法、URL 与请求体形状（金额字符串原样透传）', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;

  reply = { ok: true, code: 0, data: { list: [] } };
  await api.paymentMethods();

  reply = { ok: true, code: 0, data: { order_no: 'DEP1' } };
  await api.createDeposit({ amount: '10.50', currency: 'USD', payment_method_id: 'pm1' });

  reply = { ok: true, code: 0, data: { order_no: 'WTH1' } };
  await api.applyWithdraw({ platform_amount: '20.0000', method: 'paypal', account_info: 'a@b.c' });

  reply = { ok: true, code: 0, data: { rate: '1.5' } };
  await api.exchangeQuote({ game_id: 'g1', currency_id: 'c1', direction: 'out', platform_amount: '300' });

  reply = { ok: true, code: 0, data: { exchange_id: 'e1' } };
  await api.exchangeBuy({ game_id: 'g1', currency_id: 'c1', direction: 'in', platform_amount: '5.25' });
  await api.exchangeSell({ game_id: 'g1', currency_id: 'c1', direction: 'out', platform_amount: '300' });

  const shape = (i: number) => ({
    url: calls[i]!.url,
    method: calls[i]!.init?.method,
    body: calls[i]!.init?.body ? JSON.parse(String(calls[i]!.init?.body)) : undefined,
  });

  assert.deepEqual(shape(0), { url: '/api/v1/payment/methods', method: undefined, body: undefined });
  assert.deepEqual(shape(1), {
    url: '/api/v1/deposit/create',
    method: 'POST',
    body: { amount: '10.50', currency: 'USD', payment_method_id: 'pm1' },
  });
  assert.deepEqual(shape(2), {
    url: '/api/v1/withdraw/apply',
    method: 'POST',
    body: { platform_amount: '20.0000', method: 'paypal', account_info: 'a@b.c' },
  });
  assert.deepEqual(shape(3), {
    url: '/api/v1/exchange/quote',
    method: 'POST',
    body: { game_id: 'g1', currency_id: 'c1', direction: 'out', platform_amount: '300' },
  });
  assert.deepEqual(shape(4), {
    url: '/api/v1/exchange/buy',
    method: 'POST',
    body: { game_id: 'g1', currency_id: 'c1', direction: 'in', platform_amount: '5.25' },
  });
  assert.deepEqual(shape(5), {
    url: '/api/v1/exchange/sell',
    method: 'POST',
    body: { game_id: 'g1', currency_id: 'c1', direction: 'out', platform_amount: '300' },
  });
  assert.equal(calls.length, 6);
});

test('写操作失败时透出服务端 code/message（如 502 网关不可用）', async () => {
  tokens.set('t1', 'r1');
  reply = { ok: false, code: 502, message: 'Payment gateway unavailable, please retry' };
  await assert.rejects(
    () => api.createDeposit({ amount: '1.00', currency: 'USD', payment_method_id: 'pm1' }),
    (e: unknown) =>
      e instanceof ApiError && e.code === 502 && e.message === 'Payment gateway unavailable, please retry',
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
