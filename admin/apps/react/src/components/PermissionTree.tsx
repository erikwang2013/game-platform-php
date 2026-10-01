/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useMemo, useState, type ReactNode } from 'react';
import { t } from '../i18n/index.ts';
import { checkState, conduct, joinIds, parseIds, toggle, type TreeNode } from '../lib/tree';

/**
 * 权限树多选（角色表单的「权限」字段）。
 * - 三态：勾中 / 半选 / 未勾；勾父级连带子级全勾，子级全勾时父级自动勾（见 lib/tree.ts 的 conduct/toggle）。
 * - 值仍是「勾中的 hashid 串」（与 multi 字段同形，换行分隔），提交时由 lib/crud.ts 转成数组 ——
 *   角色端点的 permission_ids 收的就是 hashid 数组，且是整表替换 sync。
 * - 折叠态存**被折叠**的 id，缺省空集 = 全展开：权限树不大，先展开省得用户找不到刚授的按钮权限。
 * - 树没拉到之前不要渲染本组件（空树会被当成「本来就没授权」，一勾一存就把已有授权抹了）：
 *   由调用方兜底，见 FormModal 的只读文本框。
 */
export function PermissionTree({
  nodes,
  value,
  onChange,
  disabled = false,
}: {
  nodes: TreeNode[];
  /** 勾中的 id（换行分隔；与 multi 字段同形） */
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
}) {
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(() => new Set());
  // 整棵树只算一次闭包，每个节点的三态都从它读（逐节点各算一次是 O(n²)）
  const conducted = useMemo(() => conduct(nodes, parseIds(value)), [nodes, value]);

  const flip = (id: string, on: boolean): void => onChange(joinIds(toggle(nodes, parseIds(value), id, on)));

  const fold = (id: string, open: boolean): void =>
    setCollapsed((prev) => {
      const next = new Set(prev);
      if (open) next.delete(id);
      else next.add(id);
      return next;
    });

  const branch = (list: TreeNode[]): ReactNode => (
    <ul className="perm">
      {list.map((node) => {
        const state = checkState(node, conducted);
        const open = !collapsed.has(node.id);
        return (
          <li key={node.id}>
            <span className="perm-row">
              {node.children.length > 0 ? (
                <button
                  type="button"
                  className="tgl"
                  aria-expanded={open}
                  aria-label={t(open ? 'common.collapse' : 'common.expand')}
                  onClick={() => fold(node.id, !open)}
                >
                  {open ? '▾' : '▸'}
                </button>
              ) : (
                // 叶子占位：没有箭头也留出同样的宽度，同级节点的勾选框才对得齐
                <span className="tgl" aria-hidden="true" />
              )}
              <label className="perm-label">
                <input
                  type="checkbox"
                  checked={state === 'on'}
                  // 半选（indeterminate）是 DOM 属性、没有对应的 React 属性，用 ref 写；
                  // 每次渲染都写一遍，勾选态一变就同步
                  ref={(el) => {
                    if (el) el.indeterminate = state === 'half';
                  }}
                  disabled={disabled}
                  onChange={(event) => flip(node.id, event.target.checked)}
                />
                {node.label}
                {node.slug === '' ? null : <span className="muted">（{node.slug}）</span>}
              </label>
            </span>
            {open && node.children.length > 0 ? branch(node.children) : null}
          </li>
        );
      })}
    </ul>
  );

  return <div className="perm-tree">{branch(nodes)}</div>;
}
