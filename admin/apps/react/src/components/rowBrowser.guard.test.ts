/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

/**
 * `RowBrowser` 里**状态切换守卫**的调用点。
 *
 * 为什么用读源码这种土办法：本树没有 DOM 测试底座（无 jsdom / 无 testing-library，
 * `npm test` 跑的是 `node --test`，全部用例都是纯逻辑层）。而 `toggleBlock` 的**唯一**
 * 生效点是组件内的这一次调用——实测过：把它改成 `null`（守卫形同虚设），
 * `npm test` 仍然 65/65 全绿。也就是说光测「配置里那个守卫函数」是假绿，
 * 真正的自伤路径（把自己账号停用）无人看守。
 *
 * 本仓已有同款先例：`service/tests/GameSettlePayoutGuardTest` 也是读控制器源码，
 * 断言关键串存在 **且顺序正确**（`assertLessThan`）。顺序在这里是关键：
 * 守卫一旦挪到发请求之后，就没有任何意义了。
 */
const SOURCE = readFileSync(fileURLToPath(new URL('./RowBrowser.tsx', import.meta.url)), 'utf8');

/** 切出 `const toggle = () => { … };` 的函数体（到下一个个顶格 `  };` 为止） */
const toggleBody = (): string => {
  const start = SOURCE.indexOf('const toggle = () => {');
  assert.notEqual(start, -1, 'RowBrowser 里找不到 toggle()：改名了就同步改本文件');
  const end = SOURCE.indexOf('\n  };', start);
  assert.notEqual(end, -1, 'toggle() 的函数体切不出来（缩进变了？）');
  return SOURCE.slice(start, end);
};

test('守卫调用点存在且只有一处', () => {
  assert.equal(
    SOURCE.split('config.toggleBlock?.(row)').length - 1,
    1,
    'toggleBlock 必须被调用恰好一次——删掉或挪走都意味着「不许停用自己」失去执行点',
  );
});

test('守卫在 toggle() 内部，且排在发请求之前', () => {
  const body = toggleBody();

  const guardAt = body.indexOf('config.toggleBlock?.(row)');
  assert.notEqual(guardAt, -1, '守卫不在 toggle() 里（挪去别处 = 状态切换不再受保护）');

  // 请求的三个出口：update 的 PUT、自定义路径、固定端点。守卫必须早于**全部**出口。
  for (const call of ['void run(', 'config.base', 'custom.path']) {
    const at = body.indexOf(call);
    if (at === -1) continue;
    assert.ok(
      guardAt < at,
      `守卫排在 \`${call}\` 之后：请求先发出去了，守卫等于没有`,
    );
  }
});

test('守卫被拦住时直接返回，不发请求', () => {
  const body = toggleBody();
  const guardAt = body.indexOf('config.toggleBlock?.(row)');
  const after = body.slice(guardAt);

  assert.match(
    after,
    /if \(blocked !== null\) \{[\s\S]*?return;/,
    '守卫命中后必须 return（否则只是弹个提示，请求照发）',
  );
});
