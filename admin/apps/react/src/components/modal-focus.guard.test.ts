/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

/**
 * `Modal`（components/ui.tsx）的**关框路径接线**：焦点管理（判定层在 lib/focus-trap.test.ts）
 * 与另外两条 —— 脏草稿拦截（FormModal 侧）与开框锁背景滚动。判定全绿而接线被改回去，
 * 是这套改动最可能的坏法。
 *
 * 为什么读源码：本树没有 DOM 底座（无 jsdom / 无 testing-library），组件内部拿不到可渲染环境；
 * `Modal` 又被 DetailModal / FormModal / ImportPanel / LoginPage(验证码) / wallet 五处共用，
 * 退化了不会有别的用例发现。同款先例：components/import.guard.test.ts、rowActions.guard.test.ts
 * （那边记着「把守卫改成 null 仍 65/65 全绿」的实测）。真实覆盖在 CDP 真机上，不进 CI。
 */
const UI = readFileSync(fileURLToPath(new URL('./ui.tsx', import.meta.url)), 'utf8');
const FORM = readFileSync(fileURLToPath(new URL('./FormModal.tsx', import.meta.url)), 'utf8');

/** 出现次数：0 次与 2 次都是坏消息，比 contains 更能分辨 */
const countIn = (source: string, needle: string): number => source.split(needle).length - 1;
const count = (needle: string): number => countIn(UI, needle);

test('Modal：Esc 与 Tab 挂在自己的框上，不再挂 window（套两层框时只有焦点那层该响应）', () => {
  assert.equal(count('onKeyDown={onKey}'), 1, 'Modal 的宿主元素要挂 onKeyDown，且只挂一处');
  assert.equal(
    count("window.addEventListener('keydown'"),
    0,
    'Esc 不能再挂 window：焦点掉在背景页时窗口级监听会把两层框一起关掉',
  );
  assert.equal(count('const onKey = ('), 1, '键盘处理只此一处');
  // 宿主是那张 dialog，不是遮罩：挂遮罩等于没圈（焦点掉回 BODY 时遮罩同样收不到）
  assert.ok(
    UI.indexOf('onKeyDown={onKey}') > UI.indexOf('role="dialog"'),
    'onKeyDown 要挂在 role="dialog" 的框上',
  );
  // 宿主还得**自己可程序化聚焦**：点了框内非可聚焦区，浏览器把焦点交给它而不是 BODY，
  // Esc 才不哑、Tab 才不回背景页。「浏览器真把焦点给了它」只有真机覆盖，这里钉的是那一半的前提。
  assert.equal(count('tabIndex={-1}'), 1, '去掉它，一次点击就把焦点陷阱与宿主级 Esc 一起击穿');
});

test('Modal：开框读 opener 在前、聚焦在后；卸载还焦点给 opener', () => {
  const read = UI.indexOf('document.activeElement instanceof HTMLElement');
  const focus = UI.indexOf('focusables(box.current)[0]?.focus()');
  assert.notEqual(read, -1, '找不到读 opener 的那行：改名了就同步改本文件');
  assert.notEqual(focus, -1, '找不到开框聚焦那行：判定算得再对，没人调它也是零');
  assert.ok(read < focus, '必须先读 opener 再聚焦 —— 反了就永远只还给自己');
  assert.equal(count('return () => opener?.focus();'), 1, '卸载（关框）要把焦点还给打开它的那个元素');
  assert.equal(count('ref={box}'), 1, '框上要挂 ref，判定层才有 root 可查');
});

test('Modal：Tab 的判定走 lib/focus-trap，不在这里另写一份', () => {
  assert.equal(count('trapTab(box.current'), 1);
  assert.equal(count('event.preventDefault()'), 2, 'Esc 与「接手」两条路各要吃一次默认行为');
  // 焦点在不在框内由 trapTab 判；这里再写一次 contains 就是第二真值源
  assert.equal(count('box.current.contains'), 0);
});

test('FormModal：关框先判脏，脏了走 window.confirm；Modal 收到的是守卫过的 close', () => {
  // 初值快照只求值一次：每渲染现算 row 就是第二套换算，与 buildPayload 的「改动比较」会漂
  assert.equal(countIn(FORM, 'const [initial] = useState<Draft>(() => draftFrom(fields, row));'), 1);
  assert.equal(countIn(FORM, 'const [draft, setDraft] = useState<Draft>(initial);'), 1);
  // 判据用**初值快照**比，不是 row：改成 `!== initial[field.name]` 之外的东西就是另一套语义
  assert.equal(countIn(FORM, 'draft[field.name] !== initial[field.name]'), 1);
  assert.equal(countIn(FORM, "window.confirm(t('form.discard_confirm'))"), 1, '脏了要先问一句，问句要有译文键');
  // 接线：Modal 必须收到 close。写成 onClose={onClose} 就等于没护栏（表单照旧一点背景全丢）
  assert.equal(countIn(FORM, '<Modal title={title} onClose={close}>'), 1);
  assert.equal(countIn(FORM, 'onClose={onClose}>'), 0, '直接传原始 onClose = 绕过判脏');
});

test('Modal：开框锁 body 滚动，且用嵌套计数（内层先关不能解掉外层的锁）', () => {
  assert.equal(count('document.body.style.overflow'), 2, '一处锁 + 一处解，多写就是有第二条写 body 样式的路');
  assert.equal(count('locks += 1'), 1);
  assert.equal(count("if (locks === 0) document.body.style.overflow = '';"), 1, '计数归零才解锁');
});
