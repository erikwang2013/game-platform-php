/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 弹框焦点管理的**判定逻辑**（行为口径照 admin/apps/angular 的 `ModalFocus` 指令：
 * 开框聚焦首个可聚焦元素 / Tab 圈在框内 / Esc 关框 / 卸载还给打开者）。
 *
 * 为什么判定要单独一层：本树没有 DOM 底座（无 jsdom / 无 testing-library，`npm test` 跑的是
 * `node --test`，全是纯逻辑用例），只有把「该聚焦谁」摘成收 root 的函数才喂得动假对象。
 * `Modal` 那边只负责把真 DOM 与事件接上。
 */

/**
 * 框内「可聚焦」元素。与 angular 那份同一个选择器：disabled 与 `tabindex="-1"` 都要排掉
 * —— 前者 `focus()` 不生效，后者是**程序化**焦点目标，圈进来会让 Tab 停在一个不进 tab 序的位置。
 */
export const FOCUSABLE_SELECTOR =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * 框内可聚焦元素，按 DOM 顺序。
 * `[hidden]` 子树必须排掉：image / file 那两个选文件的原生输入就是 `hidden` 的
 * （FormModal 里两处），对隐藏元素 `focus()` 不生效 ⇒ 只认它当「首个」会让开框焦点落空、留在 BODY。
 */
export function focusables(root: ParentNode): HTMLElement[] {
  return [...root.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)].filter((el) => el.closest('[hidden]') === null);
}

/**
 * 一次 Tab 该落到谁：返回**要接手聚焦**的元素，`null` = 不动（中间元素之间的正常前进交给浏览器）。
 * 焦点不在框内时也算「要接手」：来源有二 —— 落在**宿主自己**身上（`tabIndex={-1}` 接住的
 * 鼠标点击落点），或宿主没有兜底属性时掉回 BODY。两者都不接手的话，这一下 Tab 就走进背景页。
 * 框内一个可聚焦元素都没有时返回 `null`：此时没有能接的落点，硬 `preventDefault` 会把 Tab 卡死。
 */
export function trapTab(root: ParentNode, active: Element | null, shiftKey: boolean): HTMLElement | null {
  const list = focusables(root);
  if (list.length === 0) return null;
  const first = list[0];
  const last = list[list.length - 1];
  // `active !== root`：宿主自己（`tabIndex={-1}`，鼠标点框内非可聚焦区的落点）**不在 tab 序里**，
  // 当它算「在框内」的话，从它 Shift+Tab 会按浏览器默认走到背景页 —— 同一个洞的另一半。
  const inBox = active !== null && active !== root && root.contains(active);
  if (shiftKey && (!inBox || active === first)) return last;
  if (!shiftKey && (!inBox || active === last)) return first;
  return null;
}
