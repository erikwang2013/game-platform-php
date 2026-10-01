/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { ApiError, api, language, setUnauthorizedHandler, tokens } from './api.ts';
import { calls, installFetch } from './apiStub.ts';

let reply: { ok: boolean; code: number; message?: string; data?: unknown } = { ok: true, code: 0, data: {} };
installFetch(() => reply);

test('tokens/language 落 localStorage，language 缺省 zh', () => {
  tokens.set('a1', 'r1');
  assert.equal(tokens.access(), 'a1');
  // 契约变更（2026-10-01）：此处的兜底由 'en' 改成 'zh'。
  // 旧断言 `assert.equal(language.get(), 'en')` 钉的是**缺陷**不是需求：本树生产代码从不写
  // K_LANG，兜底值就是实际发出去的 X-Language ⇒ 'en' 把服务端自己的默认（zh）压掉，
  // 全中文界面里会弹英文文案（例：GamePlayLogController:84 的 'Play log not found'）。
  // 改它 = 把缺陷从契约里摘掉；页面行为未动，只有这一处取值变了。
  assert.equal(language.get(), 'zh');
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

  // 提现/卖出服务端强制点击验证码（买入不强制），proof 与业务字段同级进请求体
  const PROOF = { captcha_key: 'ck1', clicks: [{ x: 10, y: 20 }] };

  reply = { ok: true, code: 0, data: { list: [] } };
  await api.paymentMethods();

  reply = { ok: true, code: 0, data: { order_no: 'DEP1' } };
  await api.createDeposit({ amount: '10.50', currency: 'USD', payment_method_id: 'pm1' });

  reply = { ok: true, code: 0, data: { order_no: 'WTH1' } };
  await api.applyWithdraw({ platform_amount: '20.0000', method: 'paypal', account_info: 'a@b.c', ...PROOF });

  reply = { ok: true, code: 0, data: { rate: '1.5' } };
  await api.exchangeQuote({ game_id: 'g1', currency_id: 'c1', direction: 'out', platform_amount: '300' });

  reply = { ok: true, code: 0, data: { exchange_id: 'e1' } };
  await api.exchangeBuy({ game_id: 'g1', currency_id: 'c1', direction: 'in', platform_amount: '5.25' });
  await api.exchangeSell({ game_id: 'g1', currency_id: 'c1', direction: 'out', platform_amount: '300', ...PROOF });

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
    body: {
      platform_amount: '20.0000',
      method: 'paypal',
      account_info: 'a@b.c',
      captcha_key: 'ck1',
      clicks: [{ x: 10, y: 20 }],
    },
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
    body: {
      game_id: 'g1',
      currency_id: 'c1',
      direction: 'out',
      platform_amount: '300',
      captcha_key: 'ck1',
      clicks: [{ x: 10, y: 20 }],
    },
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

test('未登录请求的 401 是业务错误：原样透出服务端 message，不清 token、不回调登出', async () => {
  tokens.clear();
  let kicked = 0;
  setUnauthorizedHandler(() => {
    kicked += 1;
  });
  calls.length = 0;

  // 登录页密码错误：服务端以信封 401 + 原因返回。此前会走进「会话过期」分支，
  // 把"用户名或密码错误"换成"登录状态已过期，请重新登录"，用户照着重登还是错。
  reply = { ok: true, code: 401, message: 'Invalid username or password' };
  await assert.rejects(
    () => api.login('alice', 'wrong', { captcha_key: 'k', clicks: [{ x: 1, y: 2 }] }),
    (e: unknown) => e instanceof ApiError && e.code === 401 && e.message === 'Invalid username or password',
  );
  assert.equal(kicked, 0);
  assert.equal(calls.length, 1); // 未登录 ⇒ 不发刷新请求

  // 2FA 票据失效同属此类：也不能被当成"会话过期"
  reply = { ok: true, code: 401, message: 'Invalid or expired verification session' };
  await assert.rejects(
    () => api.verify2fa('ticket', '123456'),
    (e: unknown) => e instanceof ApiError && e.code === 401 && e.message === 'Invalid or expired verification session',
  );
  assert.equal(kicked, 0);
});

test('2FA 第二步：POST /2fa/verify，票据与验证码同级进请求体', async () => {
  tokens.clear();
  calls.length = 0;
  reply = { ok: true, code: 0, data: { access_token: 'a2', refresh_token: 'r2' } };

  const data = await api.verify2fa('pending-ticket', '123456');

  assert.equal(data.access_token, 'a2');
  assert.equal(calls[0]!.url, '/api/v1/2fa/verify');
  assert.equal(calls[0]!.init?.method, 'POST');
  assert.deepEqual(JSON.parse(String(calls[0]!.init?.body)), {
    pending_2fa_token: 'pending-ticket',
    code: '123456',
  });
});

test('注销账号：POST /user/delete-account，请求体是 password + 字面量 confirm', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;
  reply = { ok: true, code: 0, data: [] };

  await api.deleteAccount('secret');
  await api.deleteAccount('secret', 'no');

  assert.equal(calls.length, 2);
  assert.equal(calls[0]!.url, '/api/v1/user/delete-account');
  assert.equal(calls[0]!.init?.method, 'POST');
  const body = (i: number) => JSON.parse(String(calls[i]!.init?.body));
  // 缺省 confirm 就是服务端要求的字面量 'yes'
  assert.deepEqual(body(0), { password: 'secret', confirm: 'yes' });
  // 页面把用户输入原样透传，由服务端裁决（'请输入 yes 确认注销'），客户端不替它决定
  assert.deepEqual(body(1), { password: 'secret', confirm: 'no' });
});

test('注销被拒（余额未清零）时原样抛出服务端原因，不吞成通用错误', async () => {
  tokens.set('t1', 'r1');
  reply = { ok: true, code: 422, message: '请先提现所有余额后再注销账号' };
  await assert.rejects(
    () => api.deleteAccount('secret', 'yes'),
    (e: unknown) =>
      e instanceof ApiError && e.code === 422 && e.message === '请先提现所有余额后再注销账号',
  );
});

test('注销后回读：资料接口 401 ⇒ 确认已注销；仍读得到 ⇒ 未注销；无法判定时不吞', async () => {
  tokens.set('t1', 'r1');
  calls.length = 0;

  reply = { ok: true, code: 401, message: '未登录或登录已过期' };
  assert.equal(await api.accountGone(), true);
  assert.equal(calls.length, 1); // retry=false：不回读重试、也不发刷新请求

  reply = { ok: true, code: 0, data: { id: 'U1', username: 'alice' } };
  assert.equal(await api.accountGone(), false);
  assert.equal(calls[1]!.url, '/api/v1/user/profile');
  assert.equal(calls[1]!.init?.method, undefined);

  reply = { ok: false, code: 500, message: '服务器内部错误' };
  await assert.rejects(
    () => api.accountGone(),
    (e: unknown) => e instanceof ApiError && e.code === 500 && e.message === '服务器内部错误',
  );
});

test('2FA 自助：status GET、setup POST 空体、enable 只送 code、disable 送 password+code', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;

  reply = { ok: true, code: 0, data: { enabled: false } };
  assert.deepEqual(await api.twoFactorStatus(), { enabled: false });
  assert.equal(calls[0]!.url, '/api/v1/user/2fa/status');
  assert.equal(calls[0]!.init?.method, undefined); // GET：不带 method

  // setup 每次调用服务端都会作废旧密钥，故必须是 POST 且不得携带任何码
  reply = { ok: true, code: 0, data: { secret: 'ABCDEF234567', qr_url: 'otpauth://totp/x' } };
  const s = await api.twoFactorSetup();
  assert.equal(calls[1]!.url, '/api/v1/user/2fa/setup');
  assert.equal(calls[1]!.init?.method, 'POST');
  assert.deepEqual(JSON.parse(String(calls[1]!.init?.body)), {});
  assert.equal(s.secret, 'ABCDEF234567');

  reply = { ok: true, code: 0, data: { backup_codes: ['aB3xY9kLm2'] } };
  const e = await api.twoFactorEnable('123456');
  assert.equal(calls[2]!.url, '/api/v1/user/2fa/enable');
  assert.deepEqual(JSON.parse(String(calls[2]!.init?.body)), { code: '123456' });
  assert.deepEqual(e.backup_codes, ['aB3xY9kLm2']);

  reply = { ok: true, code: 0, data: {} };
  await api.twoFactorDisable('pw', '654321');
  assert.equal(calls[3]!.url, '/api/v1/user/2fa/disable');
  assert.deepEqual(JSON.parse(String(calls[3]!.init?.body)), { password: 'pw', code: '654321' });
});

test('2FA 自助失败：服务端 message 原样透出（密码错 / 无待启用密钥 404）', async () => {
  tokens.set('t1', 'r1');
  calls.length = 0;

  reply = { ok: true, code: 422, message: '密码错误' };
  await assert.rejects(
    () => api.twoFactorDisable('bad', '123456'),
    (e: unknown) => e instanceof ApiError && e.code === 422 && e.message === '密码错误',
  );

  // enable 前没调 setup：服务端回 404，UI 要能显示原文而不是通用文案
  reply = { ok: true, code: 404, message: '没有待启用的 2FA 设置，请先调用 /setup' };
  await assert.rejects(
    () => api.twoFactorEnable('123456'),
    (e: unknown) =>
      e instanceof ApiError && e.code === 404 && e.message.includes('先调用 /setup'),
  );
});

test('全局搜索：只带 type=game，且回包按 {list,total} 解（没有 last_page）', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;

  // 真实回包没有 last_page：给它一个，断言解出来仍只有 list/total
  reply = { ok: true, code: 0, data: { list: [{ id: 'g1' }], total: 33, page: 2, per_page: 20 } };
  const r = await api.searchGames('扑克', { page: 2 });
  assert.equal(calls[0]!.url, '/api/v1/search?q=%E6%89%91%E5%85%8B&type=game&page=2');
  assert.equal(r.total, 33);
  assert.equal(r.list.length, 1);
  // @ts-expect-error 回包确实没有 last_page —— 用它算总页数会得到 undefined
  assert.equal(r.last_page, undefined);
});

test('邀请：创建分享短码 POST /shares（不带 activity_id 时是空体），visit 是匿名的 /shares/visit', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;

  // 建行时从不写 expires_at ⇒ 服务端回的就是 null，类型也按 nullable 收
  reply = { ok: true, code: 0, data: { short_code: 'aB3xY9kL', expires_at: null } };
  const s = await api.createShare();
  assert.equal(calls[0]!.url, '/api/v1/shares');
  assert.equal(calls[0]!.init?.method, 'POST');
  assert.deepEqual(JSON.parse(String(calls[0]!.init?.body)), {});
  assert.equal(s.short_code, 'aB3xY9kL');
  assert.equal(s.expires_at, null);

  // 带活动 hashid 才把后续转化记进活动进度
  reply = { ok: true, code: 0, data: { short_code: 'Zz9', expires_at: null } };
  await api.createShare('A1');
  assert.deepEqual(JSON.parse(String(calls[1]!.init?.body)), { activity_id: 'A1' });

  reply = { ok: true, code: 0, data: [] };
  await api.visitShare('aB3xY9kL');
  assert.equal(calls[2]!.url, '/api/v1/shares/visit');
  assert.equal(calls[2]!.init?.method, 'POST');
  assert.deepEqual(JSON.parse(String(calls[2]!.init?.body)), { short_code: 'aB3xY9kL' });
  assert.equal(calls.length, 3);
});

test('注册：share_code 只在有码时进请求体；验证码 proof 与业务字段同级', async () => {
  tokens.clear();
  language.set('en');
  calls.length = 0;
  const PROOF = { captcha_key: 'ck1', clicks: [{ x: 1, y: 2 }] };

  reply = { ok: true, code: 0, data: { access_token: 'a', refresh_token: 'r' } };
  await api.register('alice', 'Passw0rd', 'a@b.c', PROOF);
  // 无邀请码 ⇒ 请求体里**不该有 share_code 这个键**（服务端 nullable，多余空串也会被 max:12 放行，
  // 但空串会走 trim 后为假的判断；这里钉的是"不传就不出现"，避免以后被改成恒定送 '')
  assert.deepEqual(JSON.parse(String(calls[0]!.init?.body)), {
    username: 'alice',
    password: 'Passw0rd',
    email: 'a@b.c',
    captcha_key: 'ck1',
    clicks: [{ x: 1, y: 2 }],
  });

  await api.register('bob', 'Passw0rd', 'b@c.d', PROOF, 'aB3xY9kL');
  assert.deepEqual(JSON.parse(String(calls[1]!.init?.body)), {
    username: 'bob',
    password: 'Passw0rd',
    email: 'b@c.d',
    share_code: 'aB3xY9kL',
    captcha_key: 'ck1',
    clicks: [{ x: 1, y: 2 }],
  });
});

test('KYC：状态是 GET、提交是 POST /user/identity/apply，可选照片不传就不出现', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;

  reply = { ok: true, code: 0, data: { status: 'not_submitted' } };
  const s = await api.identityStatus();
  assert.equal(calls[0]!.url, '/api/v1/user/identity/status');
  assert.equal(calls[0]!.init?.method, undefined); // GET
  // 未提交时服务端只回 status 一个字段，其余全缺 —— 按可选类型解，别假设一定有
  assert.equal(s.status, 'not_submitted');
  assert.equal(s.real_name, undefined);

  reply = { ok: true, code: 0, data: [] };
  await api.applyIdentity({
    real_name: '张三',
    id_type: 'id_card',
    id_number: 'X1234',
    id_front_photo: '/api/v1/user/file/image_202610_a.png',
    selfie_photo: '/api/v1/user/file/image_202610_b.png',
  });
  assert.equal(calls[1]!.url, '/api/v1/user/identity/apply');
  assert.equal(calls[1]!.init?.method, 'POST');
  // id_back_photo 是唯一 nullable 的一张：没传就不该有这个键（服务端会写空串，别替它编）
  assert.deepEqual(JSON.parse(String(calls[1]!.init?.body)), {
    real_name: '张三',
    id_type: 'id_card',
    id_number: 'X1234',
    id_front_photo: '/api/v1/user/file/image_202610_a.png',
    selfie_photo: '/api/v1/user/file/image_202610_b.png',
  });
});

test('KYC：已有 pending/approved 时提交回 422，服务端原因原样透出', async () => {
  tokens.set('t1', 'r1');
  calls.length = 0;
  // IdentityController::apply:73 对已存在的 pending/approved 直接 422
  reply = { ok: true, code: 422, message: 'You already have a pending or approved KYC submission' };
  await assert.rejects(
    () =>
      api.applyIdentity({
        real_name: '张三',
        id_type: 'id_card',
        id_number: 'X1234',
        id_front_photo: 'p1',
        selfie_photo: 'p2',
      }),
    (e: unknown) => e instanceof ApiError && e.code === 422 && e.message.includes('already have'),
  );
});
