/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { describeCaptcha } from './captcha.ts';

const unordered = {
  extra: {
    texts: [
      { text: 'c', order: 3 },
      { text: 'a', order: 1 },
      { text: 'b', order: 2 },
    ],
  },
};

test('imgSrc：裸 base64 补回 data URI 前缀，已是 data URI 则原样返回', () => {
  assert.equal(describeCaptcha({ image: 'AAAA' }).imgSrc, 'data:image/png;base64,AAAA');
  assert.equal(describeCaptcha({ image: 'data:image/png;base64,AAAA' }).imgSrc, 'data:image/png;base64,AAAA');
  assert.equal(describeCaptcha({}).imgSrc, undefined);
  assert.equal(describeCaptcha(null).imgSrc, undefined);
});

test('texts：按 order 升序，不修改入参顺序', () => {
  const view = describeCaptcha(unordered);
  assert.deepEqual(
    view.texts.map((item) => item.text),
    ['a', 'b', 'c'],
  );
  assert.equal(unordered.extra.texts[0].text, 'c');
});

test('required：与 texts 数量一致（服务端要求精确相等），无提示时回退 2', () => {
  assert.equal(describeCaptcha(unordered).required, 3);
  assert.equal(describeCaptcha({}).required, 2);
  assert.equal(describeCaptcha(null).required, 2);
});

test('hint：有 texts 时按顺序拼接，否则报点数', () => {
  assert.equal(describeCaptcha(unordered).hint, '按顺序点击：a → b → c');
  assert.equal(describeCaptcha({}).hint, '点击图中 2 个位置完成验证');
});
