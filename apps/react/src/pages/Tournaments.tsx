/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ApiError, api, type Tournament, type TournamentDetail } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { tournamentTypeLabel } from '../lib/labels.ts';
import { money, moneyIsZero } from '../lib/money.ts';
import { Modal } from '../components/CaptchaModal.tsx';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';
import type { MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

const PER_PAGE = 20;

type Status = 'upcoming' | 'active' | 'ended';

/** 存**键**不存文案：模块级常量不在渲染期，存文案就等于把语言冻在模块加载那一刻。 */
const TABS: { key: Status; label: MessageKey }[] = [
  { key: 'upcoming', label: 'tourney.tab_upcoming' },
  { key: 'active', label: 'tourney.tab_active' },
  { key: 'ended', label: 'tourney.tab_ended' },
];

const EMPTY_TITLE: Record<Status, MessageKey> = {
  upcoming: 'tourney.empty_upcoming',
  active: 'tourney.empty_active',
  ended: 'tourney.empty_ended',
};

/**
 * 报名费：0 = 免费。只做**展示**判定，不参与任何金额运算（金额加减乘除一律在服务端 bcmath）。
 *
 * 原先两处写的是 `Number(t.entry_fee) > 0 ? t.entry_fee : '免费'` —— 金额列过数值转型，
 * 违反铁律；且「非免费」那支直接吐后端原文（`10.0650`），与 angular 同页的 `money()`（`10.065`）
 * 对同一个值印不同字符串。判零走 `moneyIsZero`（与 money() 同一套纯字符串判据），
 * 与 angular `tournaments.ts` 的 `feeText()` 逐字对齐。
 */
const feeText = (v: string, t: (k: MessageKey) => string): string =>
  moneyIsZero(v) ? t('tourney.free') : money(v);

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
  const { t } = useI18n();
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
  // note 存**键**、joinErr 存**错误对象**：取数回调里就翻好再把串存进 state，
  // 等于把文案冻在「那一刻的语言」上，之后切语言这一句不会跟着变。
  const [note, setNote] = useState<MessageKey | null>(null);
  const [joinErr, setJoinErr] = useState<unknown>(null);
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
    setNote(null);
    setJoinErr(null);
  };

  const openFresh = (id: string) => {
    setNote(null);
    setJoinErr(null);
    setCur(id);
  };

  const join = async (d: TournamentDetail) => {
    if (joining) return;
    setJoining(true);
    setNote(null);
    setJoinErr(null);
    try {
      await api.tournamentJoin(d.id);
      setNote('tourney.note_joined');
      setTick((n) => n + 1);
    } catch (e) {
      setJoinErr(e);
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

  const playerText = (row: Tournament) =>
    row.max_players > 0
      ? t('tourney.players_max', { current: row.player_count, max: row.max_players })
      : t('tourney.players', { count: row.player_count });

  return (
    <>
      <section className="stack">
        <p className="label">{t('nav.tournaments')}</p>
        <h1 className="h1">
          {t('nav.tournaments')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          {t('tourney.sub')}
        </p>
      </section>

      <div className="tabs" style={{ gridTemplateColumns: 'repeat(3, 1fr)' }}>
        {/* 形参从 t 改名 tab：进这个作用域后 `t` 是页签对象，会把 i18n 的 `t` 遮住 */}
        {TABS.map((tab) => (
          <button
            key={tab.key}
            type="button"
            className={`tabs__b${status === tab.key ? ' is-on' : ''}`}
            aria-pressed={status === tab.key}
            onClick={() => go(tab.key)}
          >
            {t(tab.label)}
          </button>
        ))}
      </div>

      {list.loading && <Loading />}
      {!list.loading && list.error && <ErrorBox message={list.error} onRetry={list.reload} />}
      {!list.loading && !list.error && items.length === 0 && (
        <Empty title={t(EMPTY_TITLE[status])} hint={t('tourney.empty_hint')} />
      )}

      {!list.loading && !list.error && items.length > 0 && (
        <section className="stack">
          <p className="small muted" style={{ margin: 0 }}>
            {t('tourney.count', { count: total })}
          </p>
          <div className="list">
            {/* 形参从 t 改名 row：同 TABS，别让行对象遮住 i18n 的 `t` */}
            {items.map((row) => (
              <button
                type="button"
                className="li li--start"
                key={row.id}
                onClick={() => openFresh(row.id)}
              >
                <div style={{ minWidth: 0 }}>
                  <p className="li__t" style={{ margin: 0 }}>
                    {row.name}
                  </p>
                  <p className="small muted" style={{ margin: '4px 0 0' }}>
                    {t('tourney.list_meta', {
                      game: row.game?.name || t('app.all_platform'),
                      players: playerText(row),
                      time: dt(row.start_at),
                    })}
                  </p>
                  <p className="small muted" style={{ margin: '4px 0 0' }}>
                    {/* 奖池仍原样透传（本树 Wallet 同口径）；报名费必须过 money()：原先的
                        `Number(row.entry_fee) > 0` 既违铁律、又与 angular 印不同串（见 lib/money.ts） */}
                    {t('tourney.pool_fee', {
                      pool: row.prize_pool,
                      fee: feeText(row.entry_fee, t),
                    })}
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
                {t('app.prev_page')}
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
                {t('app.next_page')}
              </button>
            </div>
          )}
        </section>
      )}

      {cur && (
        <Modal title={detail.data?.name || t('tourney.detail_title')} onClose={close}>
          {detail.loading && <Loading />}
          {!detail.loading && detail.error && <ErrorBox message={detail.error} onRetry={detail.reload} />}
          {!detail.loading && !detail.error && detail.data && (
            <>
              {note && (
                <p className="card card--flat" role="status" style={{ margin: 0 }}>
                  {t(note)}
                </p>
              )}

              {/* 金额不做任何**数值**运算（加减乘除在服务端 bcmath）；展示格式化统一走
                  lib/money.ts 的纯字符串实现 —— 旧注释写「不做前端格式化」，而它下面两行
                  一行原样透传、一行过 Number()，自己跟自己打架，故改写为实际口径。 */}
              <div className="list">
                <div className="li">
                  <span className="small muted">{t('tourney.prize_pool')}</span>
                  <span className="mono">{detail.data.prize_pool}</span>
                </div>
                <div className="li">
                  <span className="small muted">{t('tourney.entry_fee')}</span>
                  <span className="mono">{feeText(detail.data.entry_fee, t)}</span>
                </div>
                <div className="li">
                  <span className="small muted">{t('tourney.players_col')}</span>
                  <span>{playerText(detail.data)}</span>
                </div>
                <div className="li">
                  <span className="small muted">{t('tourney.time')}</span>
                  <span className="small">
                    {dt(detail.data.start_at)} ~ {dt(detail.data.end_at)}
                  </span>
                </div>
                {detail.data.type && (
                  <div className="li">
                    <span className="small muted">{t('tourney.type')}</span>
                    <span>{tournamentTypeLabel(detail.data.type)}</span>
                  </div>
                )}
                {detail.data.game && (
                  <div className="li">
                    <span className="small muted">{t('tourney.game')}</span>
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
                  {t('tourney.entered', { score: detail.data.my_entry.score })}
                  {detail.data.my_entry.rank > 0
                    ? t('tourney.rank_suffix', { rank: detail.data.my_entry.rank })
                    : ''}
                </p>
              ) : canJoin(detail.data, nowMs) ? (
                <button
                  type="button"
                  className="btn btn--primary btn--block"
                  disabled={joining}
                  onClick={() => void join(detail.data as TournamentDetail)}
                >
                  {joining && <span className="spin" aria-hidden="true" />}
                  {joining ? t('tourney.joining') : t('tourney.join')}
                </button>
              ) : (
                <p className="small muted" style={{ margin: 0 }}>
                  {t('tourney.join_closed')}
                </p>
              )}
              {joinErr ? (
                <p className="err" role="alert" style={{ margin: 0 }}>
                  {joinErr instanceof ApiError ? joinErr.message : t('tourney.join_failed')}
                </p>
              ) : null}

              <p className="label" style={{ margin: '6px 0 0' }}>
                {t('nav.leaderboard')}
              </p>
              {detail.data.leaderboard.length === 0 ? (
                <p className="small muted" style={{ margin: 0 }}>
                  {t('tourney.no_scores')}
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
