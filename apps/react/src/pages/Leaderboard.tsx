/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { api, type Leaderboard as Board } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { money } from '../lib/money.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';
import type { MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

/** type/metric → 键。**存键不存文案**：存下来的是渲染期才翻的键，切语言才会跟着变。 */
const TYPE_LABEL: Record<string, MessageKey> = {
  daily: 'leaderboard.type_daily',
  weekly: 'leaderboard.type_weekly',
  monthly: 'leaderboard.type_monthly',
  all: 'leaderboard.type_all',
};

const METRIC_LABEL: Record<string, MessageKey> = {
  earned: 'leaderboard.metric_earned',
  spent: 'leaderboard.metric_spent',
  play_count: 'leaderboard.metric_play_count',
};

/**
 * 榜单分数**按 metric 分流**（与 angular `leaderboard.ts` 的 `score()` 同口径）：
 * `earned`/`spent` 是平台币金额（四位 DECIMAL）⇒ 走 `money()`；`play_count` 是次数⇒ 走计数格式化。
 *
 * 旧实现一律 `Number(v).toLocaleString('en-US', {maximumFractionDigits: 2})`，两个毛病：
 * ① 0.0001 这类分数被印成 `0`（**非零显示成零**）；② 0.005 被进位成 0.01（与记账值反向舍入）。
 * 计数那支保留 `Number()`：次数不是金额（铁律只管金额/价格/精确数据计算），angular 同页也这么写。
 */
const score = (v: string, metric: string): string => {
  if (metric !== 'play_count') return money(v);
  const n = Number(v);
  return Number.isFinite(n) ? n.toLocaleString('en-US', { maximumFractionDigits: 2 }) : v;
};

/**
 * 身份列只展示末 4 个字符。
 *
 * 后端契约（读盘核实，2026-10-02 更新）：每条目**只有三个键** rank / user_id / score，
 * 其中 **`user_id` 已由 `LeaderboardController::ranking` 逐行 `encodeId` 成 hashid 字符串**
 * （之前是裸 BIGINT，打进报文里可枚举 —— 现已符合本仓「跨 API 边界的 ID 一律 hashid」的约定）。
 * 剩下的事实只有一条：接口**不提供昵称/头像** ⇒ 这一列**先天只能显示编号**。
 * `maskedId` 不是"做了个好看的别名"，是接口真没有名字可显示——别在这里编造昵称，
 * 也别为了显示名字去逐条拉 `/user/profile`（N+1，且多数拿不到）。
 * 打码本身现在只是**版面选择**（hashid 十几位，塞不进这一列），不再承担"遮住主键"的职责；
 * 露出的末 4 位是随机字符，不指向任何可用标识。
 */
const maskedId = (id: string) => `#···${String(id).slice(-4)}`;

export function Leaderboard() {
  const { t } = useI18n();
  const boards = useAsync(() => api.leaderboards(), []);
  const [picked, setPicked] = useState('');

  const list = boards.data?.list ?? [];
  // 未手动选择时用第一张榜，省一个 useEffect 同步
  const current: Board | null = list.find((b) => b.id === picked) ?? list[0] ?? null;
  // 键在渲染期现取现翻（不是在取数回调里翻好再存），切语言才会跟着变
  const typeKey = current ? TYPE_LABEL[current.type] : undefined;
  const metricKey = current ? METRIC_LABEL[current.metric] : undefined;

  // 换榜时 useAsync 依赖 hashid 重新拉，不会残留上一张榜的名次
  const rank = useAsync(
    () => (current ? api.leaderboard(current.id) : Promise.resolve(null)),
    [current?.id ?? ''],
  );

  return (
    <>
      <section className="stack">
        <p className="label">{t('nav.leaderboard')}</p>
        <h1 className="h1">
          {t('nav.leaderboard')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          {t('leaderboard.sub')}
        </p>
      </section>

      <section className="stack">
        {boards.loading && <Loading />}
        {!boards.loading && boards.error && (
          <ErrorBox message={boards.error} onRetry={boards.reload} />
        )}
        {!boards.loading && !boards.error && list.length === 0 && (
          <Empty title={t('leaderboard.empty_title')} hint={t('leaderboard.empty_hint')} />
        )}

        {!boards.loading && !boards.error && list.length > 0 && (
          <div className="chips" role="tablist" aria-label={t('leaderboard.pick')}>
            {list.map((b) => (
              <button
                key={b.id}
                type="button"
                role="tab"
                aria-selected={current?.id === b.id}
                className="chip"
                style={
                  current?.id === b.id
                    ? { background: 'var(--ink)', color: 'var(--paper)' }
                    : undefined
                }
                onClick={() => setPicked(b.id)}
              >
                {b.name}
              </button>
            ))}
          </div>
        )}
      </section>

      {current && (
        <section className="stack">
          <div className="row">
            <span className="pill pill--yellow">{typeKey ? t(typeKey) : current.type}</span>
            <span className="pill pill--plain">{metricKey ? t(metricKey) : current.metric}</span>
            {!current.game_id && <span className="pill pill--plain">{t('app.all_platform')}</span>}
          </div>

          {/* 说清「为什么只有编号」：接口按契约就只返回 rank/user_id/score 三个字段，
              没有昵称也没有头像。与其留一列空白让人以为是加载失败，不如写明。 */}
          <p className="small muted" style={{ margin: 0 }}>
            {t('leaderboard.id_note')}
          </p>

          {rank.loading && <Loading />}
          {!rank.loading && rank.error && <ErrorBox message={rank.error} onRetry={rank.reload} />}
          {!rank.loading && !rank.error && rank.data && rank.data.ranking.length === 0 && (
            <Empty title={t('leaderboard.no_rank_title')} hint={t('leaderboard.no_rank_hint')} />
          )}
          {!rank.loading && !rank.error && rank.data && rank.data.ranking.length > 0 && (
            <div className="list">
              {rank.data.ranking.map((r) => (
                <div key={`${r.rank}-${r.user_id}`} className="li">
                  <div className="row" style={{ gap: 12 }}>
                    <span
                      className="mono"
                      style={{ width: 34, fontWeight: 700, color: 'var(--orange)' }}
                    >
                      {r.rank}
                    </span>
                    <span className="mono small muted">{maskedId(r.user_id)}</span>
                  </div>
                  <span className="mono">{score(r.score, current.metric)}</span>
                </div>
              ))}
            </div>
          )}
        </section>
      )}
    </>
  );
}
