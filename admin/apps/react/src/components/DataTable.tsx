/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import type { ReactNode } from 'react';
import { Empty, ErrorNote, Loading } from './ui';

export type Row = Record<string, unknown>;
export type Column = {
  key: string;
  label: string;
  render?: (row: Row) => ReactNode;
  align?: 'right';
};

/** 任意值 → 可展示节点。未知结构降级为 "{…}" / "n 项" 而不是白屏。 */
export function cell(value: unknown): ReactNode {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'boolean') return value ? '是' : '否';
  if (typeof value === 'number' || typeof value === 'string') {
    const text = String(value);
    return text.length > 48 ? (
      <span className="ellip" title={text}>
        {text}
      </span>
    ) : (
      text
    );
  }
  if (Array.isArray(value)) return value.length === 0 ? '—' : `${value.length} 项`;
  return '{…}';
}

export function DataTable({
  columns,
  rows,
  loading,
  error,
  onRetry,
  onRowClick,
}: {
  columns: Column[];
  rows: Row[];
  loading?: boolean;
  error?: string | null;
  onRetry?: () => void;
  onRowClick?: (row: Row) => void;
}) {
  if (loading) return <Loading rows={4} />;
  if (error) return <ErrorNote message={error} onRetry={onRetry} />;
  if (rows.length === 0) return <Empty />;
  if (columns.length === 0) return <Empty text="响应中没有可展示的字段" />;

  return (
    <div className="tablewrap">
      <table className={`dt${onRowClick ? ' clickable' : ''}`}>
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column.key} className={column.align === 'right' ? 'right' : undefined}>
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr
              key={index}
              tabIndex={onRowClick ? 0 : undefined}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
              onKeyDown={
                onRowClick
                  ? (event) => {
                      if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        onRowClick(row);
                      }
                    }
                  : undefined
              }
            >
              {columns.map((column) => (
                <td key={column.key} data-label={column.label} className={column.align === 'right' ? 'right' : undefined}>
                  {column.render ? column.render(row) : cell(row[column.key])}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
