/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 组件侧绑定：`const { t, code, setCode } = useI18n()`。
 *
 * 与运行时（`./index.ts`）分两个文件，是为了让 `lib/http.ts` 那条「传输层不依赖 React」
 * 的约束还能成立 —— 本文件是全树唯一 import react 的 i18n 文件。
 *
 * ⚠ **每一个要跟着语言重绘的页面子树，根组件都得自己调一次本 hook。**
 * `admin/apps/react` 曾写着「订阅只挂在布局层即可覆盖全树，`<Outlet/>` 会随之重渲染」，
 * **那句是错的**：真机实测切到中文后顶栏变了而页面里的按钮仍是英文。
 * React Router 的 `<Outlet/>` 不会因为父组件重渲染就把路由子树重绘一遍。
 *
 * 所以：页面里**不要在模块顶层预先算好文案**（`const NAV = [{ label: t(...) }]`），
 * 那样会冻在首次求值的语言上；页面组件本身要订阅，然后放渲染期现算。
 */
import { useCallback, useSyncExternalStore } from 'react';
import { api } from '../lib/api.ts';
import { currentCode, revisionOf, setCode, subscribe, t } from './index.ts';

/**
 * 切语言 = 本地落值 + **告诉服务端**。
 *
 * 本地那半截（`./index.ts` 的 `setCode`）管界面与 `X-Language`；这一枪管**服务端**：
 * `LanguageController::switch:53-58` 在已登录时把 `user.language` 一起改掉 ⇒
 * 站外消息（邮件/推送）才跟着换语言。只切本地的症状是「界面切了，邮件还是旧语言」。
 *
 * fire-and-forget：失败**不回滚**界面（语言偏好是用户意图，不是服务端事务），也不 await
 * —— 界面必须瞬时切过去，不该等一个网络往返。
 */
export function useI18n(): { code: string; setCode: (code: string) => void; t: typeof t } {
  // 订阅修订号（不是一个语言码字符串）：表异步到货时语言码没变，靠它才能把页面重绘一遍
  useSyncExternalStore(subscribe, revisionOf);

  const change = useCallback((code: string) => {
    setCode(code);
    api.switchLanguage(code).catch(() => {
      /* 未登录 / 离线 / 后端 422：界面照切，下次切换再试 */
    });
  }, []);

  // 修订号变了就重渲染，这里现读当前语言 —— 与 `t()` 读的是同一份模块级真值，不可能各说各话
  return { code: currentCode(), setCode: change, t };
}
