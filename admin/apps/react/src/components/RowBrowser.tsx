/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useState } from 'react';
import type { Query } from '../lib/api';
import { ID_KEYS, pick } from '../lib/format';
import { useApi } from '../lib/hooks';
import { asRows, columnsFrom } from './AutoView';
import { DataTable, type Row } from './DataTable';
import { DetailModal } from './DetailModal';

/**
 * 列表页通用件：列表 + 行点击弹详情。
 * preferred 只影响列的排序，字段在响应里不存在时不会凭空造列。
 */
export function RowBrowser({
  path,
  query,
  preferred,
  detailBase,
  detailTitle = '详情',
}: {
  path: string;
  query?: Query;
  preferred?: string[];
  detailBase?: string;
  detailTitle?: string;
}) {
  const { data, loading, error, reload } = useApi<unknown>(path, query);
  const [selected, setSelected] = useState<string | null>(null);

  const rows = asRows(data) ?? [];
  const columns = columnsFrom(rows, preferred);

  const open = (row: Row) => {
    const id = pick(row, ID_KEYS);
    if (id !== null && id !== undefined && id !== '') setSelected(String(id));
  };

  return (
    <>
      <DataTable
        columns={columns}
        rows={rows}
        loading={loading}
        error={error}
        onRetry={reload}
        onRowClick={detailBase ? open : undefined}
      />
      {selected && detailBase ? (
        <DetailModal path={`${detailBase}/${selected}`} title={`${detailTitle} ${selected}`} onClose={() => setSelected(null)} />
      ) : null}
    </>
  );
}
