/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { FOCUSABLE_SELECTOR, focusables, trapTab } from './focus-trap.ts';

/**
 * 弹框焦点陷阱的判定层。`Modal` 那边负责把真 DOM 接上，这里喂的是**假 root**：
 * 本树没有 DOM 底座（无 jsdom / testing-library），假 root 是唯一能让判定跑起来的办法。
 *
 * 覆盖边界（如实记）：这里证明的是「给定这份框内元素清单与当前焦点，该不该接手、接手到谁」。
 * 真机上「焦点确实搬过去了」「中间元素之间浏览器自己走」不在本文件内 —— 前者由 `focus()`
 * 的真实实现在浏览器里决定，后者 jsdom 也不执行（angular 那棵树同一处只能钉两端，见
 * form-modal.spec.ts 的注释）；react 两侧的真机读数见 CDP 脚本，不进 CI。
 */

type FakeNode = {
  name: string;
  focused: boolean;
  closest: (selector: string) => FakeNode | null;
  focus: () => void;
};

/** 假元素：只实现判定用得到的两个方法（`closest('[hidden]')` 与 `focus()`）。 */
const node = (name: string, hidden = false): FakeNode => {
  const self: FakeNode = {
    name,
    focused: false,
    closest: (selector) => (selector === '[hidden]' && hidden ? self : null),
    focus: () => {
      self.focused = true;
    },
  };
  return self;
};

/** 假 root：`querySelectorAll` 直接交出「这份清单」（选择器匹配本身由真 DOM 负责）。 */
const box = (items: FakeNode[], seen?: string[]) => {
  const self = {
    querySelectorAll: (selector: string) => {
      seen?.push(selector);
      return items;
    },
    // 真 DOM 里 `root.contains(root)` 是 true（节点包含自己）——「焦点落在宿主自己身上」那条
    // 用例全靠这一条成立，写成 `items.includes` 会让它恒假、把变异喂成假绿
    contains: (other: unknown) => other === self || items.includes(other as FakeNode),
  };
  return self;
};

const asRoot = (fake: ReturnType<typeof box>): ParentNode => fake as unknown as ParentNode;

test('focusables：按 DOM 顺序返回、排掉 [hidden] 子树，且交出去的就是 FOCUSABLE_SELECTOR', () => {
  const close = node('close');
  const hiddenFile = node('hidden-file', true);
  const submit = node('submit');
  const seen: string[] = [];
  const list = focusables(asRoot(box([close, hiddenFile, submit], seen)));

  // 顺序照 DOM；hidden 的那个不能进清单 —— 它正是 FormModal 里两个 `hidden` 的选文件输入，
  // 认它当「首个」会让开框焦点落空（对隐藏元素 focus() 不生效）
  assert.deepEqual(
    list.map((item) => (item as unknown as FakeNode).name),
    ['close', 'submit'],
  );
  assert.deepEqual(seen, [FOCUSABLE_SELECTOR], '查询用的选择器必须是这一个常量，不能另写一份');
  // 排掉 disabled 与 tabindex="-1"：前者 focus() 不生效，后者是程序化目标、不进 tab 序
  assert.match(FOCUSABLE_SELECTOR, /button:not\(\[disabled\]\)/);
  assert.match(FOCUSABLE_SELECTOR, /input:not\(\[disabled\]\)/);
  assert.match(FOCUSABLE_SELECTOR, /\[tabindex\]:not\(\[tabindex="-1"\]\)/);
});

test('末个元素上 Tab 接回首个；首个元素上 Shift+Tab 接回末个', () => {
  const first = node('close');
  const middle = node('body-input');
  const last = node('submit');
  const root = asRoot(box([first, middle, last]));

  assert.equal(trapTab(root, last as unknown as Element, false), first);
  assert.equal(trapTab(root, first as unknown as Element, true), last);
  // 反向：末个上 Shift+Tab / 首个上 Tab 都不该被接手（否则两端判据写反了也能全绿）
  assert.equal(trapTab(root, last as unknown as Element, true), null);
  assert.equal(trapTab(root, first as unknown as Element, false), null);
});

test('圈内中间元素：Tab / Shift+Tab 都不接手（交给浏览器，不能见 Tab 就抢）', () => {
  const first = node('close');
  const middle = node('body-input');
  const last = node('submit');
  const root = asRoot(box([first, middle, last]));

  assert.equal(trapTab(root, middle as unknown as Element, false), null);
  assert.equal(trapTab(root, middle as unknown as Element, true), null);
});

test('焦点不在框内（掉回 BODY / 背景元素）时也接手，否则这一下 Tab 走进背景页', () => {
  const first = node('close');
  const last = node('submit');
  const outside = node('背景页的按钮');
  const root = asRoot(box([first, last]));

  assert.equal(trapTab(root, outside as unknown as Element, false), first);
  assert.equal(trapTab(root, outside as unknown as Element, true), last);
  assert.equal(trapTab(root, null, false), first);
});

test('焦点落在宿主自己身上（tabIndex=-1，点框内非可聚焦区的落点）时，两个方向都拉回框内', () => {
  const first = node('close');
  const middle = node('body-input');
  const last = node('submit');
  const root = box([first, middle, last]);

  // 宿主不在 tab 序里 ⇒ 交给浏览器默认的话 Shift+Tab 会走到背景页（陷阱被一次点击击穿）
  assert.equal(trapTab(asRoot(root), root as unknown as Element, false), first);
  assert.equal(trapTab(asRoot(root), root as unknown as Element, true), last);
});

test('空框：一个可聚焦元素都没有时返回 null（不抛、也不把 Tab 卡死在框里）', () => {
  const root = asRoot(box([]));
  assert.deepEqual(focusables(root), []);
  assert.equal(trapTab(root, null, false), null);
  assert.equal(trapTab(root, node('外面') as unknown as Element, true), null);
});
