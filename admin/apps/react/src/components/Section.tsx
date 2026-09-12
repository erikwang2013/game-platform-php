/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import type { Query } from '../lib/api';
import { useApi } from '../lib/hooks';
import { AutoView } from './AutoView';
import { Card, ErrorNote, Loading } from './ui';

/** 取数 + 三态渲染的一体化卡片，形状未确认的端点全部走这里。 */
export function Section({ title, path, query, sub }: { title: string; path: string; query?: Query; sub?: string }) {
  const { data, loading, error, reload } = useApi<unknown>(path, query);
  return (
    <Card title={title} sub={sub}>
      {loading ? <Loading /> : error ? <ErrorNote message={error} onRetry={reload} /> : <AutoView data={data} />}
    </Card>
  );
}
