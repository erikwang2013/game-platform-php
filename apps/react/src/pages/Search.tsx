/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';

const PER_PAGE = 20;

/**
 * 全局搜索。q 走 URL query，页面可直接分享/刷新。
 *
 * ⚠ 回包与其它列表**不同形**：只有 {list,total,page,per_page}，**没有 last_page**。
 * 所以这里用 `page * per_page < total` 自己算有没有下一页，别去读 last_page（恒 undefined）。
 *
 * 只搜游戏：服务端把 type=user 标为 admin 用途，C 端不该拿它检索其他用户。
 */
export function Search() {
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const page = Math.max(1, Number(params.get('page') ?? 1) || 1);

  // 输入框是未提交的草稿，与已生效的 q 分开：改字不该立刻打请求
  const [draft, setDraft] = useState(q);

  const res = useAsync(
    () => (q.trim() ? api.searchGames(q.trim(), { page, per_page: PER_PAGE }) : Promise.resolve(null)),
    [q, page],
  );

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const v = draft.trim();
    // 同一关键词重复提交不重置页码，否则在第 3 页按回车会跳回第 1 页
    if (v === q) return;
    setParams(v ? { q: v } : {});
  };

  const goPage = (p: number) => setParams({ q, page: String(p) });

  const items = res.data?.list ?? [];
  const total = res.data?.total ?? 0;
  const lastPage = Math.max(1, Math.ceil(total / PER_PAGE));

  return (
    <>
      <section className="stack">
        <p className="label">全局搜索</p>
        <h1 className="h1">
          搜索
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>

        <form className="search" onSubmit={submit}>
          <input
            className="input"
            name="q"
            type="search"
            placeholder="搜索游戏名称或简介"
            autoComplete="off"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
          />
          <button type="submit" className="btn btn--primary">
            搜索
          </button>
        </form>
      </section>

      {q.trim() === '' ? (
        <Empty title="输入关键词开始搜索" hint="支持按游戏名称与简介匹配" />
      ) : (
        <section className="stack">
          {res.loading && <Loading />}
          {!res.loading && res.error && <ErrorBox message={res.error} onRetry={res.reload} />}
          {!res.loading && !res.error && items.length === 0 && (
            <Empty title={`没有找到与「${q}」相关的游戏`} hint="换个关键词试试" />
          )}
          {!res.loading && !res.error && items.length > 0 && (
            <>
              <p className="small muted" style={{ margin: 0 }}>
                共 {total} 个结果
              </p>
              <div className="list">
                {items.map((g) => (
                  <Link className="li" key={g.id} to={`/game/${g.id}`}>
                    <div>
                      <p className="li__t" style={{ margin: 0 }}>
                        {g.name}
                      </p>
                      <p className="small muted" style={{ margin: '4px 0 0' }}>
                        {g.description || '暂无简介'}
                      </p>
                    </div>
                  </Link>
                ))}
              </div>

              {lastPage > 1 && (
                <div className="between">
                  <button
                    type="button"
                    className="btn btn--sm"
                    disabled={page <= 1 || res.loading}
                    onClick={() => goPage(page - 1)}
                  >
                    上一页
                  </button>
                  <span className="small muted">
                    {page} / {lastPage}
                  </span>
                  <button
                    type="button"
                    className="btn btn--sm"
                    disabled={page >= lastPage || res.loading}
                    onClick={() => goPage(page + 1)}
                  >
                    下一页
                  </button>
                </div>
              )}
            </>
          )}
        </section>
      )}
    </>
  );
}
