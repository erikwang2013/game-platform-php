/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 全局搜索（GET /admin/v1/search）—— 后端有端点、本树没有入口，运营只能逐个页面翻。
 *
 * 端点的口径（读 SearchController）：`q` + `type`（只有 `game` / `user` 两种）+ 分页，
 * **每页条数的参数名是 `per_page`**（与其余列表的 `limit` 不同，故 lib/paging.ts 三个别名一起发）。
 * `q` 为空时后端直接回 `{list:[],total:0}`，不报错 —— 界面按「还没搜」渲染，而不是空表。
 *
 * 结果行点开走**各自的详情端点**：游戏 `/admin/v1/game/{hashid}`、用户
 * `/admin/v1/platform/user/{hashid}`（两个端点的响应形状完全不同，交给 DetailModal 的 AutoView）。
 */
import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { RowBrowser } from '../components/RowBrowser';
import { Card, Empty, PageHead } from '../components/ui';
import { t, useI18n, type MessageKey } from '../i18n/index.ts';

/** 两种搜索目标（后端 `type` 的值域，只此两个）。 */
const KINDS: { value: string; label: MessageKey; detail: string; columns: string[] }[] = [
  {
    value: 'game',
    label: 'search.type_game',
    detail: '/admin/v1/game',
    columns: ['id', 'name', 'slug', 'type', 'status', 'region', 'platform', 'created_at'],
  },
  {
    value: 'user',
    label: 'search.type_user',
    detail: '/admin/v1/platform/user',
    columns: ['id', 'username', 'nickname', 'status', 'vip_level', 'created_at'],
  },
];

export function SearchPage() {
  useI18n();
  // 关键词放 URL：顶栏搜索框与结果页共用同一处状态，刷新/回退都不丢（也不需要额外的全局 store）
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const kind = params.get('type') === 'user' ? 'user' : 'game';
  const [draft, setDraft] = useState(q);
  const spec = KINDS.find((item) => item.value === kind) ?? KINDS[0];

  const submit = (event: { preventDefault: () => void }) => {
    event.preventDefault();
    const next = new URLSearchParams();
    if (draft.trim() !== '') next.set('q', draft.trim());
    next.set('type', kind);
    setParams(next);
  };

  return (
    <>
      <PageHead title={t('page.search.title')} sub={t('page.search.sub')} />
      <Card title={t('page.search.title')}>
        <form className="toolbar" onSubmit={submit}>
          <label className="label">
            {t('search.keyword')}
            <input
              className="input"
              type="search"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              placeholder={t('search.placeholder')}
            />
          </label>
          <label className="label">
            {t('f.type')}
            <select
              className="input"
              value={kind}
              onChange={(event) => {
                const next = new URLSearchParams(params);
                next.set('type', event.target.value);
                setParams(next);
              }}
            >
              {KINDS.map((item) => (
                <option key={item.value} value={item.value}>
                  {t(item.label)}
                </option>
              ))}
            </select>
          </label>
          <button className="btn" type="submit">
            {t('common.search')}
          </button>
        </form>
        {q === '' ? (
          // 关键词为空时后端回空列表（不是错误）—— 界面说清「先输入关键词」，别摆一张空表让人以为搜不到
          <Empty text={t('search.empty_keyword')} />
        ) : (
          // key 钉住「关键词 + 类型」：换一次搜索就重挂，分页与旧结果都不会串到新搜索上
          <RowBrowser
            key={`${kind}:${q}`}
            path="/admin/v1/search"
            query={{ q, type: kind }}
            preferred={spec.columns}
            detailBase={spec.detail}
            detailTitle="common.detail"
          />
        )}
      </Card>
    </>
  );
}
