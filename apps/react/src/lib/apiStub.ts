/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * api.test.ts / api.social.test.ts 共用的最小替身。
 *
 * 抽出来是为了两个测试文件各带一份 30 行 stub 的重复，以及 api.test.ts 破 500 行。
 * `node --test` 每个文件一个进程、没有共享 setup，所以顶层这里做副作用注册是安全的：
 * 两个进程各跑一次，互不影响。
 *
 * `getReply` 用回调而不是直接读变量：各测试文件自己持有 `let reply` 并在用例里改写它，
 * 回调闭包每次调用时重新读，改写立刻生效（若把值拷进来，改的就是快照了）。
 *
 * ⚠ 本文件**不能** import `node:*`：tsconfig.app.json 只把 `src` 下所有 `*.test.ts` 排除出 tsc，
 * 而这个文件名不以 .test.ts 结尾 ⇒ 它在 tsc 的 program 里，而 program 的 types 仅 `vite/client`，
 * 解析不了 node: 说明符，`tsc -b` 会直接红（实测 err TS2591）。
 */
/** `installFetch()` 收的假响应形状 —— **不导出**：调用方传字面量，零处按名引用本名。 */
type Reply = { ok: boolean; code: number; message?: string; data?: unknown };

export const calls: { url: string; init: RequestInit | undefined }[] = [];

/* localStorage 是浏览器全局，node --test 里用最小替身顶掉；本树无测试依赖，够用即可 */
const store = new Map<string, string>();

/**
 * 单独导出：upload.test.ts 要用**自己那份 fetch 替身**（上传响应不是 `{code,message,data}`
 * 信封，而是 `{error,savedPath}`），但仍需要这里的 localStorage —— api.ts 的
 * `language.get()` / `tokens.access()` 都读它，缺了会直接抛。
 */
export function installLocalStorage(): void {
  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    value: {
      getItem: (k: string) => store.get(k) ?? null,
      setItem: (k: string, v: string) => void store.set(k, v),
      removeItem: (k: string) => void store.delete(k),
      clear: () => store.clear(),
      key: () => null,
      length: 0,
    },
  });
}

installLocalStorage();

export function installFetch(getReply: () => Reply): void {
  Object.defineProperty(globalThis, 'fetch', {
    configurable: true,
    writable: true,
    value: (url: string, init?: RequestInit) => {
      calls.push({ url, init });
      const r = getReply();
      return Promise.resolve({
        ok: r.ok,
        json: () => Promise.resolve({ code: r.code, message: r.message ?? '', data: r.data }),
      });
    },
  });
}
