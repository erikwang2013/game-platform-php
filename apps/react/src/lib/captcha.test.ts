/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { CANVAS_H, CANVAS_W, describeCaptcha, toImageCoords } from './captcha.ts';

test('describeCaptcha：裸 base64 补 data URI 前缀；texts 按 order 升序且不改入参', () => {
  assert.equal(describeCaptcha({ image: 'AAAA' }).imgSrc, 'data:image/png;base64,AAAA');
  assert.equal(
    describeCaptcha({ image: 'data:image/png;base64,AAAA' }).imgSrc,
    'data:image/png;base64,AAAA',
  );
  assert.equal(describeCaptcha({}).imgSrc, undefined);
  assert.equal(describeCaptcha(null).imgSrc, undefined);

  const unordered = {
    extra: {
      texts: [
        { text: 'c', order: 3 },
        { text: 'a', order: 1 },
        { text: 'b', order: 2 },
      ],
    },
  };
  const view = describeCaptcha(unordered);
  assert.deepEqual(
    view.texts.map((item) => item.text),
    ['a', 'b', 'c'],
  );
  assert.equal(unordered.extra.texts[0]?.text, 'c');
  // 服务端逐点比对，点数需精确相等；无提示时回退 2
  assert.equal(view.required, 3);
  assert.equal(view.hint, '按顺序点击：a → b → c');
  assert.equal(describeCaptcha({}).required, 2);
  assert.equal(describeCaptcha(null).hint, '点击图中 2 个位置完成验证');
});

test('toImageCoords：按显示框 → 300×200 画布等比换算', () => {
  // 显示 600×400（2 倍）：框内正中必须是画布正中 150×100
  const box = { left: 20, top: 40, width: 600, height: 400 };
  assert.deepEqual(toImageCoords(320, 240, box, { w: CANVAS_W, h: CANVAS_H }), { x: 150, y: 100 });
  // 点击落点就是显示框左上角 ⇒ 画布 (0,0)
  assert.deepEqual(toImageCoords(20, 40, box, { w: CANVAS_W, h: CANVAS_H }), { x: 0, y: 0 });
  // 非整数倍缩放（1.5 倍）下四舍五入
  const box15 = { left: 0, top: 0, width: 450, height: 300 };
  assert.deepEqual(toImageCoords(225, 300, box15, { w: CANVAS_W, h: CANVAS_H }), { x: 150, y: 200 });
  assert.deepEqual(toImageCoords(100, 100, box15, { w: CANVAS_W, h: CANVAS_H }), { x: 67, y: 67 });
  // 负控：写死 400×250 会让同一个点击算出 200×250，即坐标系统性偏移
  assert.notDeepEqual(
    toImageCoords(320, 240, box, { w: CANVAS_W, h: CANVAS_H }),
    toImageCoords(320, 240, box, { w: 400, h: 250 }),
  );
});

test('toImageCoords：显示框尺寸为 0（图片未加载）时返回 null，不产出 NaN 坐标', () => {
  const dead = { left: 0, top: 0, width: 0, height: 0 };
  assert.equal(toImageCoords(10, 10, dead, { w: CANVAS_W, h: CANVAS_H }), null);
  assert.equal(toImageCoords(10, 10, { ...dead, width: 300 }, { w: CANVAS_W, h: CANVAS_H }), null);
});
