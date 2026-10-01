/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { dash, isTimeKey, when } from '../lib/format';
import { DataTable, cell, type Row } from './DataTable';
import { fieldLabelKey, t, type MessageKey } from '../i18n/index.ts';
import { Card, Empty, Stat } from './ui';

/**
 * 键 → 显示名。与表头（`columnsFrom` + `DataTable`）同一条兜底链：`f.<键>` 在表里就用译文，
 * 没有就**原样回键名**（绝不露出查不到的 `f.xxx`）。
 *
 * 为什么要这一步：兜底渲染器原本把 `total_users` / `dau` 这种键直接当标签摆在统计块上，
 * 任何语言下都是裸字段名。加了这一步之后，往 `en.fields.ts` 补一条 `f.<键>` 就自动生效，
 * 不必再给每个端点各写一套渲染。
 */
const labelOf = (key: string): string => t(fieldLabelKey(key) ?? (key as MessageKey));

/** 常见的列表容器键，用于从未确认的响应里找出真正的数组。 */
const LIST_KEYS = [
  'list',
  'items',
  'rows',
  'records',
  'result',
  'data',
  'orders',
  'events',
  'users',
  'games',
  'logs',
  'coupons',
  'currencies',
  'methods',
  'limits',
  'tickets',
  'ips',
  'ranks',
];

function firstArray(obj: Row): unknown[] | null {
  for (const key of LIST_KEYS) if (Array.isArray(obj[key])) return obj[key] as unknown[];
  for (const value of Object.values(obj)) if (Array.isArray(value)) return value as unknown[];
  return null;
}

/** 从任意响应里找出对象数组；找不到返回 null。 */
export function asRows(data: unknown): Row[] | null {
  const raw = Array.isArray(data) ? data : data && typeof data === 'object' ? firstArray(data as Row) : null;
  if (!raw || raw.length === 0) return null;
  const rows = raw.filter((item): item is Row => !!item && typeof item === 'object' && !Array.isArray(item));
  return rows.length > 0 ? rows : null;
}

/**
 * `columnsFrom` 本体在 `lib/columns.ts`：纯逻辑搬出 `.tsx` 才能被 `node --test` 加载
 * （本树的 `--experimental-strip-types` **不认 .tsx**）。这里导入后再导出，既有导入点不用改。
 */
import { columnsFrom, labelsFor } from '../lib/columns.ts';

export { columnsFrom };

/**
 * 兜底渲染器：把「形状未确认」的响应渲染成统计块 / 表格 / 嵌套卡片。
 * 目标是不白屏 —— 认不出的字段显示 "{…}" 或 "n 项"，而不是抛错。
 */
export function AutoView({ data, depth = 0 }: { data: unknown; depth?: number }) {
  if (data === null || data === undefined) return <Empty />;

  if (Array.isArray(data)) {
    if (data.length === 0) return <Empty />;
    const rows = asRows(data);
    if (rows) {
      // 表头也走 labelOf 那条链（统计块早就走了）：否则同一张卡上标签是译文、列头是 fp_masked
      const columns = columnsFrom(rows, [], 8, [], labelsFor(rows));
      return <DataTable columns={columns} rows={rows} />;
    }
    return (
      <div className="chips">
        {data.map((item, index) => (
          <span key={index} className="badge b-muted">
            {cell(item)}
          </span>
        ))}
      </div>
    );
  }

  if (typeof data !== 'object') {
    return (
      <div className="grid grid-4">
        <Stat label={t('common.value')} value={dash(data)} />
      </div>
    );
  }

  const obj = data as Row;
  const scalars = Object.entries(obj).filter(([, value]) => value === null || typeof value !== 'object');
  const nested = Object.entries(obj).filter(([, value]) => value !== null && typeof value === 'object');

  if (scalars.length === 0 && nested.length === 0) return <Empty />;

  return (
    <div className="stack">
      {scalars.length > 0 ? (
        <div className="grid grid-4">
          {scalars.map(([key, value]) => (
            <Stat key={key} label={labelOf(key)} value={isTimeKey(key) ? when(value) : dash(value)} />
          ))}
        </div>
      ) : null}
      {nested.length > 0 && depth >= 1 ? (
        // ponytail: 只展开一层嵌套，再深的字段不递归；需要时把 depth 上限调大
        <p className="muted">{t('table.nested_more', { count: nested.length })}</p>
      ) : null}
      {depth === 0
        ? nested.map(([key, value]) => (
            <Card key={key} title={labelOf(key)}>
              <AutoView data={value} depth={depth + 1} />
            </Card>
          ))
        : null}
    </div>
  );
}
