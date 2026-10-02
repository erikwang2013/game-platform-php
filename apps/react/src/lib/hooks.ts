/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useCallback, useEffect, useState } from 'react';
import { t } from '../i18n/index.ts';
import { ApiError } from './api.ts';

/** `useAsync()` 的返回形状 —— **不导出**：全树零处按名引用（`Wallet.tsx` 只用 `useAsync` 本身）。 */
interface Async<T> {
  data: T | null;
  loading: boolean;
  error: string | null;
  reload: () => void;
}

/** 极简数据请求：加载 / 错误 / 数据三态 + 手动重载。 */
export function useAsync<T>(fn: () => Promise<T>, deps: unknown[]): Async<T> {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  // 存**抛出来的原始对象**，不存翻好的串：`setError(t(...))` 是在 promise 回调里翻的，
  // 会把文案冻在「失败那一刻的语言」上，之后切语言这句不会跟着变。
  const [error, setError] = useState<{ cause: unknown } | null>(null);
  const [tick, setTick] = useState(0);

  const run = useCallback(fn, deps);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    setError(null);
    run()
      .then((d) => alive && setData(d))
      .catch((e: unknown) => alive && setError({ cause: e }))
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, [run, tick]);

  const reload = useCallback(() => setTick((t) => t + 1), []);
  // 翻在这里（渲染期）而不是 catch 里：切语言时才跟着变。对外的类型仍是 string | null，
  // 调用方（`<ErrorBox message={x.error} />`）一处都不用改。
  const message =
    error === null
      ? null
      : error.cause instanceof ApiError
        ? error.cause.message
        : t('error.load_failed');
  return { data, loading, error: message, reload };
}
