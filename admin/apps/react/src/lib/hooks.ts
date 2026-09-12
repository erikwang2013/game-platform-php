/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ApiError, api, type Query } from './api';
import { useAuth } from './auth';

export type Async<T> = {
  data: T | null;
  loading: boolean;
  error: string | null;
  reload: () => void;
};

/** 统一取数：loading / error(取 ApiError.message) / data；query 变化自动重取。 */
export function useApi<T>(path: string, query?: Query): Async<T> {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);

  // query 用 JSON 串做依赖，避免每次渲染传入新对象字面量导致死循环
  const key = JSON.stringify(query ?? null);
  const reload = useCallback(() => setTick((n) => n + 1), []);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    setError(null);
    api<T>(path, { query: (JSON.parse(key) as Query | null) ?? undefined })
      .then((result) => {
        if (alive) setData(result);
      })
      .catch((cause: unknown) => {
        if (alive) setError(cause instanceof ApiError ? cause.message : '网络异常，请稍后重试');
      })
      .finally(() => {
        if (alive) setLoading(false);
      });
    return () => {
      alive = false;
    };
  }, [path, key, tick]);

  return { data, loading, error, reload };
}

/** 退出：先尽力通知服务端吊销令牌，无论成败都清理本地会话并回登录页。 */
export function useSignOut(): () => Promise<void> {
  const { logout } = useAuth();
  const navigate = useNavigate();
  return useCallback(async () => {
    try {
      await api('/admin/v1/profile/logout', { method: 'POST' });
    } catch {
      // 服务端登出失败不影响本地清理
    }
    logout();
    navigate('/login', { replace: true });
  }, [logout, navigate]);
}
