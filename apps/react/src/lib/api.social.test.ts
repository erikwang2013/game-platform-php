/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 工单 / 好友 / 聊天 / 组队这一批的契约用例。
 * 从 api.test.ts 分出来纯粹是因为那个文件撞了 500 行上限；两边共用 apiStub.ts 的替身。
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { ApiError, api, language, tokens } from './api.ts';
import { calls, installFetch } from './apiStub.ts';

let reply: { ok: boolean; code: number; message?: string; data?: unknown } = { ok: true, code: 0, data: {} };
installFetch(() => reply);

test('工单：列表/详情/建单/回复的方法与请求体', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;

  // 回包是 PagedLite：有 last_page，**没有 per_page**（按 Paged<T> 解会读成 undefined）
  reply = {
    ok: true,
    code: 0,
    data: { items: [{ id: 'T1', type: 'deposit', subject: '充值没到账', status: 'open', priority: 0, reply_count: 2, created_at: '2026-10-01 10:00:00' }], total: 1, page: 1, last_page: 1 },
  };
  const list = await api.tickets({ page: 1 });
  assert.equal(calls[0]!.url, '/api/v1/ticket/list?page=1');
  assert.equal(calls[0]!.init?.method, undefined); // GET
  assert.equal(list.items[0]!.reply_count, 2);
  // @ts-expect-error 这个端点确实不回 per_page
  assert.equal(list.per_page, undefined);

  reply = { ok: true, code: 0, data: { id: 'T1', type: 'deposit', subject: 's', content: 'c', status: 'open', priority: 0, replies: [], created_at: '2026-10-01 10:00:00' } };
  await api.ticket('T1');
  assert.equal(calls[1]!.url, '/api/v1/ticket/T1');

  reply = { ok: true, code: 0, data: { id: 'T2' } };
  await api.createTicket({ type: 'withdraw', subject: '提现失败', content: '订单号 X' });
  assert.equal(calls[2]!.url, '/api/v1/ticket/create');
  assert.equal(calls[2]!.init?.method, 'POST');
  assert.deepEqual(JSON.parse(String(calls[2]!.init?.body)), {
    type: 'withdraw',
    subject: '提现失败',
    content: '订单号 X',
  });

  reply = { ok: true, code: 0, data: { id: 'R1' } };
  await api.replyTicket('T2', '补充说明');
  assert.equal(calls[3]!.url, '/api/v1/ticket/T2/reply');
  assert.deepEqual(JSON.parse(String(calls[3]!.init?.body)), { content: '补充说明' });
});

test('工单：关闭后回复被拒，服务端原因原样透出', async () => {
  tokens.set('t1', 'r1');
  calls.length = 0;
  // TicketController:146 对 status=closed 直接 422
  reply = { ok: true, code: 422, message: 'Ticket is closed' };
  await assert.rejects(
    () => api.replyTicket('T1', '再问一句'),
    (e: unknown) => e instanceof ApiError && e.code === 422 && e.message === 'Ticket is closed',
  );
});

test('好友：request 送 friend_id、accept/reject 送 request_id（两个 id 语义不同）', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;
  reply = { ok: true, code: 0, data: { list: [] } };

  await api.friends();
  await api.friendRequests();
  assert.equal(calls[0]!.url, '/api/v1/friend/list');
  assert.equal(calls[1]!.url, '/api/v1/friend/requests');

  reply = { ok: true, code: 0, data: { id: 'F1' } };
  await api.friendRequest('U9');
  assert.equal(calls[2]!.url, '/api/v1/friend/request');
  // 发起申请送的是**对方用户**的 hashid
  assert.deepEqual(JSON.parse(String(calls[2]!.init?.body)), { friend_id: 'U9' });

  reply = { ok: true, code: 0, data: {} };
  await api.friendAccept('REL1');
  await api.friendReject('REL2');
  // 接受/拒绝送的是**好友关系**的 hashid，字段名也不同 —— 送成 friend_id 会 404
  assert.deepEqual(JSON.parse(String(calls[3]!.init?.body)), { request_id: 'REL1' });
  assert.deepEqual(JSON.parse(String(calls[4]!.init?.body)), { request_id: 'REL2' });
  assert.equal(calls[3]!.url, '/api/v1/friend/accept');
  assert.equal(calls[4]!.url, '/api/v1/friend/reject');

  await api.friendRemove('U9');
  assert.deepEqual(JSON.parse(String(calls[5]!.init?.body)), { friend_id: 'U9' });
  assert.equal(calls[5]!.url, '/api/v1/friend/remove');

  await api.friendSearch('ali');
  assert.equal(calls[6]!.url, '/api/v1/friend/search?q=ali');
  await api.friendSearch('');
  // 空 q 不带参数（服务端空 q 直接回空列表，别送 ?q= 空串）
  assert.equal(calls[7]!.url, '/api/v1/friend/search');
});

test('聊天：会话/记录/发送的 URL 与请求体', async () => {
  tokens.set('t1', 'r1');
  language.set('en');
  calls.length = 0;

  reply = { ok: true, code: 0, data: { list: [] } };
  await api.conversations();
  assert.equal(calls[0]!.url, '/api/v1/chat/conversations');
  assert.equal(calls[0]!.init?.method, undefined); // GET

  // messages 是 GET，且对端 hashid 在**路径**里（不是 query）
  reply = { ok: true, code: 0, data: { items: [], total: 0, page: 1, last_page: 1 } };
  await api.chatMessages('U9', { page: 2 });
  assert.equal(calls[1]!.url, '/api/v1/chat/messages/U9?page=2');
  assert.equal(calls[1]!.init?.method, undefined);

  reply = { ok: true, code: 0, data: { id: 'M1', created_at: '2026-10-01 12:00:00' } };
  await api.sendChat('U9', '在吗');
  assert.equal(calls[2]!.url, '/api/v1/chat/send');
  assert.deepEqual(JSON.parse(String(calls[2]!.init?.body)), { to_user_id: 'U9', content: '在吗' });
});

/* 本条原先还钉了两处：markChatRead 的请求体 `{from_user_id}`（select 字段名不是 peer_id）、
   chatUnreadTotal 的 URL `/chat/unread-total`。两个包装 2026-10-02 撤下（零消费者，理由见
   api.ts 的同名墓碑注释）⇒ 没有包装就没有契约可测，两处断言随之撤下；上面三条逐字未动。 */

test('聊天：非好友发送被拒（403）时原样透出服务端原因', async () => {
  tokens.set('t1', 'r1');
  calls.length = 0;
  // ChatController:124 非好友发消息回 403
  reply = { ok: true, code: 403, message: 'Only friends can send messages' };
  await assert.rejects(
    () => api.sendChat('U9', '你好'),
    (e: unknown) => e instanceof ApiError && e.code === 403 && e.message.includes('Only friends'),
  );
});

/* 组/公会的两条契约用例 2026-10-01 随 Group.tsx 一并撤下（没有包装了就没有契约可测）。
   端点本身仍是通的，恢复条件见 types.ts 的组队/公会墓碑注释。 */
