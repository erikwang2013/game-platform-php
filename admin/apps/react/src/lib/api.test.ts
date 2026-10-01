/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 归一化的**接线**测试：`toWallClock` 自己的用例在 `format.test.ts`，这里钉的是
 * 「它真的挂在了信封解析上」。
 *
 * 为什么要单开一条：纯函数全绿而 reviver 忘了挂 / 挂错了层，正是这类修复最典型的失败模式
 * （改一处、漏一处，界面照旧显示 `2026-09-16T16:38:51.000000Z`）—— 那时 format.test.ts
 * 依然是绿的，因为它只证明函数会算，不证明有人调它。
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { api } from './api.ts';

/** 最小 localStorage 桩：`send()` 要读令牌，Node 里没有这个全局。 */
function stubStorage(): () => void {
  const store = new Map<string, string>();
  const real = (globalThis as Record<string, unknown>).localStorage;
  (globalThis as Record<string, unknown>).localStorage = {
    getItem: (k: string) => store.get(k) ?? null,
    setItem: (k: string, v: string) => void store.set(k, v),
    removeItem: (k: string) => void store.delete(k),
  };
  return () => {
    (globalThis as Record<string, unknown>).localStorage = real;
  };
}

const envelope = (data: unknown): Response =>
  new Response(JSON.stringify({ code: 0, message: 'ok', data }), {
    headers: { 'content-type': 'application/json' },
  });

test('api：响应体里的 ISO-UTC 时间串被归一成服务端墙钟串（reviver 确实挂上了）', async () => {
  const realFetch = globalThis.fetch;
  const restoreStorage = stubStorage();
  globalThis.fetch = async () =>
    envelope({
      list: [
        {
          id: '1',
          created_at: '2025-12-31T16:00:00.000000Z',
          start_at: '2026-01-02 03:04:05',
          note: 'T16:00:00Z 出现在句子中间不该被当成时间戳',
          name: 'a',
        },
      ],
      total: 1,
    });
  try {
    const data = await api<{ list: Record<string, unknown>[] }>('/admin/v1/x');
    const row = data.list[0];
    assert.equal(row.created_at, '2026-01-01 00:00:00', 'ISO 串没被归一 ⇒ 界面上仍是机器串');
    // 那 8 个不带 cast 的列本来就是墙钟串：一格都不能动（动了是拿新缺陷换旧缺陷）
    assert.equal(row.start_at, '2026-01-02 03:04:05');
    assert.equal(row.name, 'a');
    // 半截像时间戳的普通文本（登录后的操作日志 message 里就可能有）不许被改
    assert.equal(row.note, 'T16:00:00Z 出现在句子中间不该被当成时间戳');
  } finally {
    globalThis.fetch = realFetch;
    restoreStorage();
  }
});

test('api：信封的 message 与 code 不受归一化影响', async () => {
  const realFetch = globalThis.fetch;
  const restoreStorage = stubStorage();
  globalThis.fetch = async () => envelope(null);
  try {
    const data = await api<null>('/admin/v1/y');
    assert.equal(data, null);
  } finally {
    globalThis.fetch = realFetch;
    restoreStorage();
  }
});
