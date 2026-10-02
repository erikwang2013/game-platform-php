/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';
import { useI18n } from '../i18n/useI18n.ts';

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
  const { t } = useI18n();
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
        <p className="label">{t('search.title')}</p>
        <h1 className="h1">
          {t('app.search')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>

        <form className="search" onSubmit={submit}>
          <input
            className="input"
            name="q"
            type="search"
            placeholder={t('search.placeholder')}
            autoComplete="off"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
          />
          <button type="submit" className="btn btn--primary">
            {t('app.search')}
          </button>
        </form>
      </section>

      {q.trim() === '' ? (
        <Empty title={t('search.empty_title')} hint={t('search.empty_hint')} />
      ) : (
        <section className="stack">
          {res.loading && <Loading />}
          {!res.loading && res.error && <ErrorBox message={res.error} onRetry={res.reload} />}
          {!res.loading && !res.error && items.length === 0 && (
            <Empty title={t('search.no_result', { q })} hint={t('search.no_result_hint')} />
          )}
          {!res.loading && !res.error && items.length > 0 && (
            <>
              <p className="small muted" style={{ margin: 0 }}>
                {t('search.count', { total })}
              </p>
              <div className="list">
                {items.map((g) => (
                  <Link className="li" key={g.id} to={`/game/${g.id}`}>
                    <div>
                      <p className="li__t" style={{ margin: 0 }}>
                        {g.name}
                      </p>
                      <p className="small muted" style={{ margin: '4px 0 0' }}>
                        {g.description || t('search.no_desc')}
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
                    {t('app.prev_page')}
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
                    {t('app.next_page')}
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
