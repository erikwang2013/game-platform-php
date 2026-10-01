/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ApiError, api, type Tournament, type TournamentDetail } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { tournamentTypeLabel } from '../lib/labels.ts';
import { Modal } from '../components/CaptchaModal.tsx';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';

const PER_PAGE = 20;

type Status = 'upcoming' | 'active' | 'ended';

const TABS: { key: Status; label: string }[] = [
  { key: 'upcoming', label: '即将开始' },
  { key: 'active', label: '进行中' },
  { key: 'ended', label: '已结束' },
];

const EMPTY_TITLE: Record<Status, string> = {
  upcoming: '暂无即将开始的赛事',
  active: '当前没有进行中的赛事',
  ended: '还没有已结束的赛事',
};

/**
 * 赛事：列表 + 详情弹框 + 报名（三个端点都在这页）。
 *
 * 口径取自 `TournamentController`，四条都是读码核过的，不是口味：
 *  1. **默认落在 `upcoming`，不是服务端默认的 `active`**：`join()` 的第一道闸是
 *     `start_at <= now` ⇒ 400 'Tournament has already started'，而 `active` 的定义就是
 *     `start_at <= now <= end_at` ⇒ **进行中的赛事一条都报不进**。落地就摆在唯一报得进的那栏。
 *  2. 报名按钮**只在开赛前**摆。这只是本机时钟的本地预判，真闸在服务端 ——
 *     被拒（已开赛/已报名/满员/未启用）时把服务端 message 原样透出，不吞成「操作失败」。
 *  3. 报名成功后 **`my_entry` 与 `player_count` 都由服务端算**：重拉详情 + 重拉列表，
 *     不在前端把人数 +1、也不自己造一条报名记录。
 *  4. `list` 被 FeatureFlag `tournament` 把着，关掉时回 `code:503`；
 *     而 `detail()` **压根不查这个开关** ⇒ 别拿「详情打得开」当「功能开着」的证据。
 *     开关关掉时列表把那句服务端原文透出来，不渲染成「暂无赛事」。
 *
 * ⚠ 时间字段是 **ISO8601 UTC**（`Tournament` 模型 cast 成 datetime、控制器原样透传），
 * 与其它接口的 `Y-m-d H:i:s` 墙钟串不同形 ⇒ 展示与比较都必须过 `dt()` / `new Date()`，
 * 直接贴到 DOM 上会差 8 小时。
 */
export function Tournaments() {
  const [params, setParams] = useSearchParams();
  const status: Status = TABS.find((t) => t.key === params.get('status'))?.key ?? 'upcoming';
  const page = Math.max(1, Number(params.get('page') ?? 1) || 1);
  // 报名成功后 +1：列表与详情同时重拉（两个 useAsync 共用这个 tick）
  const [tick, setTick] = useState(0);

  // nowMs：**取数那一刻**的本机时钟，供开赛预判用。
  // 不在渲染期读 `Date.now()`——那是渲染副作用（oxlint `react(purity)` 会红）。
  // 代价：页面停留很久时预判会变旧，真闸仍在服务端（见文件头第 2 条）。
  const list = useAsync(async () => {
    const d = await api.tournaments(status, { page, per_page: PER_PAGE });
    return { d, nowMs: Date.now() };
  }, [status, page, tick]);

  const [cur, setCur] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [joinErr, setJoinErr] = useState('');
  const [joining, setJoining] = useState(false);

  const detail = useAsync(() => (cur ? api.tournamentDetail(cur) : Promise.resolve(null)), [cur, tick]);

  const items = list.data?.d.items ?? [];
  const total = list.data?.d.total ?? 0;
  const lastPage = Math.max(1, list.data?.d.last_page ?? 1);
  const nowMs = list.data?.nowMs ?? 0;

  /** 换页签回到第 1 页；第 1 页不写 page，URL 干净 */
  const go = (next: Status, nextPage = 1) =>
    setParams(nextPage > 1 ? { status: next, page: String(nextPage) } : { status: next });

  const close = () => {
    setCur(null);
    setNote('');
    setJoinErr('');
  };

  const openFresh = (id: string) => {
    setNote('');
    setJoinErr('');
    setCur(id);
  };

  const join = async (d: TournamentDetail) => {
    if (joining) return;
    setJoining(true);
    setNote('');
    setJoinErr('');
    try {
      await api.tournamentJoin(d.id);
      setNote('报名成功，开赛后会出现在排行榜里。');
      setTick((n) => n + 1);
    } catch (e) {
      setJoinErr(e instanceof ApiError ? e.message : '报名失败，请稍后重试');
    } finally {
      setJoining(false);
    }
  };

  /**
   * 本地预判：只在开赛前摆按钮。据 `join()` 的第一道闸。
   * 比的是**上次取数时**的本机时钟（可能偏）⇒ 服务端真拒绝时原样透出它的原因。
   * `nowMs` 为 0（列表还没回来）时一律不摆按钮，避免拿未初始化的时钟误判。
   */
  const canJoin = (t: Tournament, now: number) => {
    const ms = new Date(t.start_at ?? '').getTime();
    return now > 0 && Number.isFinite(ms) && ms > now;
  };

  const playerText = (t: Tournament) =>
    t.max_players > 0 ? `${t.player_count} / ${t.max_players} 人` : `${t.player_count} 人`;

  return (
    <>
      <section className="stack">
        <p className="label">赛事</p>
        <h1 className="h1">
          赛事
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          开赛后不能再报名。奖池与人数以服务端为准，报名成功请以页面提示为准。
        </p>
      </section>

      <div className="tabs" style={{ gridTemplateColumns: 'repeat(3, 1fr)' }}>
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            className={`tabs__b${status === t.key ? ' is-on' : ''}`}
            aria-pressed={status === t.key}
            onClick={() => go(t.key)}
          >
            {t.label}
          </button>
        ))}
      </div>

      {list.loading && <Loading />}
      {!list.loading && list.error && <ErrorBox message={list.error} onRetry={list.reload} />}
      {!list.loading && !list.error && items.length === 0 && (
        <Empty title={EMPTY_TITLE[status]} hint="换个标签看看，或稍后再来" />
      )}

      {!list.loading && !list.error && items.length > 0 && (
        <section className="stack">
          <p className="small muted" style={{ margin: 0 }}>
            共 {total} 场赛事
          </p>
          <div className="list">
            {items.map((t) => (
              <button type="button" className="li li--start" key={t.id} onClick={() => openFresh(t.id)}>
                <div style={{ minWidth: 0 }}>
                  <p className="li__t" style={{ margin: 0 }}>
                    {t.name}
                  </p>
                  <p className="small muted" style={{ margin: '4px 0 0' }}>
                    {t.game?.name || '全平台'} · {playerText(t)} · {dt(t.start_at)} 开赛
                  </p>
                  <p className="small muted" style={{ margin: '4px 0 0' }}>
                    奖池 {t.prize_pool} · 报名费 {Number(t.entry_fee) > 0 ? t.entry_fee : '免费'}
                  </p>
                </div>
              </button>
            ))}
          </div>

          {lastPage > 1 && (
            <div className="between">
              <button
                type="button"
                className="btn btn--sm"
                disabled={page <= 1 || list.loading}
                onClick={() => go(status, page - 1)}
              >
                上一页
              </button>
              <span className="small muted">
                {page} / {lastPage}
              </span>
              <button
                type="button"
                className="btn btn--sm"
                disabled={page >= lastPage || list.loading}
                onClick={() => go(status, page + 1)}
              >
                下一页
              </button>
            </div>
          )}
        </section>
      )}

      {cur && (
        <Modal title={detail.data?.name || '赛事详情'} onClose={close}>
          {detail.loading && <Loading />}
          {!detail.loading && detail.error && <ErrorBox message={detail.error} onRetry={detail.reload} />}
          {!detail.loading && !detail.error && detail.data && (
            <>
              {note && (
                <p className="card card--flat" role="status" style={{ margin: 0 }}>
                  {note}
                </p>
              )}

              {/* 金额一律按服务端给的十进制字符串展示，不做前端格式化/运算（与钱包页同口径） */}
              <div className="list">
                <div className="li">
                  <span className="small muted">奖池</span>
                  <span className="mono">{detail.data.prize_pool}</span>
                </div>
                <div className="li">
                  <span className="small muted">报名费</span>
                  <span className="mono">
                    {Number(detail.data.entry_fee) > 0 ? detail.data.entry_fee : '免费'}
                  </span>
                </div>
                <div className="li">
                  <span className="small muted">人数</span>
                  <span>{playerText(detail.data)}</span>
                </div>
                <div className="li">
                  <span className="small muted">时间</span>
                  <span className="small">
                    {dt(detail.data.start_at)} ~ {dt(detail.data.end_at)}
                  </span>
                </div>
                {detail.data.type && (
                  <div className="li">
                    <span className="small muted">类型</span>
                    <span>{tournamentTypeLabel(detail.data.type)}</span>
                  </div>
                )}
                {detail.data.game && (
                  <div className="li">
                    <span className="small muted">游戏</span>
                    <Link to={`/game/${detail.data.game.id}`} onClick={close}>
                      {detail.data.game.name}
                    </Link>
                  </div>
                )}
              </div>
              {detail.data.description && (
                <p className="small muted" style={{ margin: 0, whiteSpace: 'pre-wrap' }}>
                  {detail.data.description}
                </p>
              )}

              {detail.data.my_entry ? (
                <p className="card card--flat" style={{ margin: 0 }}>
                  已报名 · 积分 {detail.data.my_entry.score}
                  {detail.data.my_entry.rank > 0 ? ` · 第 ${detail.data.my_entry.rank} 名` : ''}
                </p>
              ) : canJoin(detail.data, nowMs) ? (
                <button
                  type="button"
                  className="btn btn--primary btn--block"
                  disabled={joining}
                  onClick={() => void join(detail.data as TournamentDetail)}
                >
                  {joining && <span className="spin" aria-hidden="true" />}
                  {joining ? '报名中…' : '报名参赛'}
                </button>
              ) : (
                <p className="small muted" style={{ margin: 0 }}>
                  报名已截止（服务端在开赛后拒收报名）
                </p>
              )}
              {joinErr && (
                <p className="err" role="alert" style={{ margin: 0 }}>
                  {joinErr}
                </p>
              )}

              <p className="label" style={{ margin: '6px 0 0' }}>
                排行榜
              </p>
              {detail.data.leaderboard.length === 0 ? (
                <p className="small muted" style={{ margin: 0 }}>
                  还没有成绩
                </p>
              ) : (
                <div className="list">
                  {detail.data.leaderboard.map((r, i) => (
                    <div className="li" key={`${r.user}-${i}`}>
                      <span className="mono muted" style={{ minWidth: 28 }}>
                        {r.rank && r.rank > 0 ? r.rank : i + 1}
                      </span>
                      <div style={{ minWidth: 0, flex: 1 }}>
                        <p className="li__t" style={{ margin: 0 }}>
                          {r.user}
                        </p>
                      </div>
                      <span className="mono">{r.score}</span>
                    </div>
                  ))}
                </div>
              )}
            </>
          )}
        </Modal>
      )}
    </>
  );
}
