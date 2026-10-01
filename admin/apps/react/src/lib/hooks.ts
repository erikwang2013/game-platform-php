/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { t } from '../i18n/index.ts';
import { ApiError, api, type Query } from './api';
import { useAuth } from './auth';
import { PAGE_SIZE, pageQuery } from './paging';

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
        if (alive) setError(cause instanceof ApiError ? cause.message : t('app.network_error'));
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

export type Paged<T> = Async<T> & {
  page: number;
  setPage: (page: number) => void;
  pageSize: number;
};

/**
 * 带分页的取数：`page` 变化即重取（query 带上 page 与三个别名，见 lib/paging.ts）。
 *
 * `paged=false` 发给**整表端点与裸数组/树**：这些端点一次回全量（没有 total，也没人读 page），
 * 发分页参数要么被忽略、要么真把列表截断（下拉选项源被截断＝选项凭空少一批），故一个参数都不发。
 *
 * 筛选条件变化时回到第 1 页：留在第 3 页看新筛选的结果，多半是空页。
 * 用渲染期同步（React 官方的「props 变了就调整 state」写法）而不是 useEffect —— 后者会先按旧页
 * 发一次必然被丢弃的请求。setPage 的同一次调用里读到的 page 还是旧值，故要判一下再设。
 */
export function usePagedApi<T>(path: string, query?: Query, paged = true, pageSize = PAGE_SIZE): Paged<T> {
  const [page, setPage] = useState(1);
  const key = JSON.stringify(query ?? null);
  const [seen, setSeen] = useState(key);
  if (seen !== key) {
    setSeen(key);
    if (page !== 1) setPage(1);
  }
  const { data, loading, error, reload } = useApi<T>(path, paged ? pageQuery(query, page, pageSize) : query);
  return { data, loading, error, reload, page, setPage, pageSize };
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
