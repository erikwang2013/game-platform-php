/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ApiError, api, type Game } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { useI18n } from '../i18n/useI18n.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';

type Suggest = { id: string; name: string; slug: string };

export function Home() {
  const navigate = useNavigate();
  const { t } = useI18n();
  const stats = useAsync(() => api.stats(), []);

  const [items, setItems] = useState<Game[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [listLoading, setListLoading] = useState(true);
  // ⚠ 存的是**错误对象**不是翻好的文案：存文案会把语言冻在失败那一刻，
  // 切完语言这一行还是旧语言（`t()` 必须在渲染期求值）
  const [listErr, setListErr] = useState<unknown>(null);
  const [keyword, setKeyword] = useState('');
  const [tick, setTick] = useState(0);

  const [q, setQ] = useState('');
  const [sug, setSug] = useState<Suggest[]>([]);

  useEffect(() => {
    let alive = true;
    setListLoading(true);
    setListErr(null);
    api
      .games({ page, per_page: 9, keyword: keyword || undefined })
      .then((d) => {
        if (!alive) return;
        setItems((prev) => (page === 1 ? d.items : [...prev, ...d.items]));
        setLastPage(d.last_page || 1);
      })
      .catch((e: unknown) => {
        if (!alive) return;
        setListErr(e);
      })
      .finally(() => alive && setListLoading(false));
    return () => {
      alive = false;
    };
  }, [page, keyword, tick]);

  // 联想词：输入 2 字以上，防抖 250ms
  useEffect(() => {
    const term = q.trim();
    if (term.length < 2) {
      setSug([]);
      return;
    }
    const timer = setTimeout(() => {
      api
        .suggest(term)
        .then((r) => setSug(r.suggestions ?? []))
        .catch(() => setSug([]));
    }, 250);
    return () => clearTimeout(timer);
  }, [q]);

  const search = (e: FormEvent) => {
    e.preventDefault();
    setSug([]);
    setKeyword(q.trim());
    setPage(1);
  };

  return (
    <>
      <section className="stack">
        <p className="label">{t('home.overview')}</p>
        <h1 className="h1">
          {t('home.hero_title')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        {stats.loading && <Loading />}
        {!stats.loading && stats.error && <ErrorBox message={stats.error} onRetry={stats.reload} />}
        {!stats.loading && !stats.error && stats.data && (
          <div className="grid--stats">
            <div className="stat stat--orange">
              <p className="stat__n">{stats.data.total_games}</p>
              <p className="stat__k">{t('home.stat_games')}</p>
            </div>
            <div className="stat">
              <p className="stat__n">{stats.data.total_users}</p>
              <p className="stat__k">{t('home.stat_players')}</p>
            </div>
            <div className="stat stat--yellow">
              <p className="stat__n">{stats.data.today_game_plays}</p>
              <p className="stat__k">{t('home.stat_plays_today')}</p>
            </div>
            <div className="stat">
              <p className="stat__n">{stats.data.active_users_7d}</p>
              <p className="stat__k">{t('home.stat_active_7d')}</p>
            </div>
          </div>
        )}
      </section>

      <section className="stack">
        <p className="label">{t('home.discover')}</p>
        <div className="chips">
          <Link className="chip" to="/games">
            {t('nav.my_games')}
          </Link>
          <Link className="chip" to="/announcements">
            {t('home.announcements')}
          </Link>
          <Link className="chip" to="/leaderboard">
            {t('nav.leaderboard')}
          </Link>
        </div>
      </section>

      <section className="stack">
        <p className="label">{t('home.find_games')}</p>
        <div className="search">
          <form onSubmit={search} className="row" style={{ flexWrap: 'nowrap' }}>
            <input
              className="input"
              placeholder={t('home.search_placeholder')}
              aria-label={t('home.search_label')}
              value={q}
              onChange={(e) => setQ(e.target.value)}
              onBlur={() => setTimeout(() => setSug([]), 150)}
            />
            <button type="submit" className="btn btn--ink">
              {t('app.search')}
            </button>
          </form>
          {sug.length > 0 && (
            <ul className="suggest">
              {sug.map((s) => (
                <li key={s.id}>
                  <button type="button" onClick={() => navigate(`/game/${s.id}`)}>
                    {s.name}
                    <span className="mono muted" style={{ marginLeft: 8 }}>
                      {s.slug}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

      </section>

      <section className="stack">
        <p className="label">
          {t('home.all_games')}
          {keyword && (
            <span className="pill pill--plain">{t('home.keyword', { keyword })}</span>
          )}
        </p>

        {listErr != null && (
          <ErrorBox
            message={listErr instanceof ApiError ? listErr.message : t('error.load_failed')}
            onRetry={() => setTick((n) => n + 1)}
          />
        )}
        {!listErr && items.length === 0 && listLoading && <Loading />}
        {!listErr && items.length === 0 && !listLoading && (
          <Empty title={t('home.no_result')} hint={t('home.no_result_hint')} />
        )}

        {items.length > 0 && (
          <div className="grid">
            {items.map((g) => (
              <Link key={g.id} to={`/game/${g.id}`} className="card card--link">
                <div className="cover">
                  {g.cover_image ? (
                    <img src={g.cover_image} alt="" loading="lazy" />
                  ) : (
                    <span className="cover__ph">{g.name.slice(0, 2)}</span>
                  )}
                </div>
                <div className="between">
                  <h3 className="h3">{g.name}</h3>
                  <span className="pill pill--yellow">{g.type}</span>
                </div>
                {g.categories && g.categories.length > 0 && (
                  <div className="chips" style={{ marginTop: 8 }}>
                    {g.categories.slice(0, 3).map((c) => (
                      <span key={c.slug} className="chip">
                        {c.name}
                      </span>
                    ))}
                  </div>
                )}
                {g.description && (
                  <p className="small muted" style={{ margin: '8px 0 0' }}>
                    {g.description.length > 64 ? `${g.description.slice(0, 64)}…` : g.description}
                  </p>
                )}
              </Link>
            ))}
          </div>
        )}

        {page < lastPage && (
          <button
            type="button"
            className="btn btn--block"
            disabled={listLoading}
            onClick={() => setPage((p) => p + 1)}
          >
            {listLoading && <span className="spin" aria-hidden="true" />}
            {t('app.load_more')}
          </button>
        )}
      </section>
    </>
  );
}
