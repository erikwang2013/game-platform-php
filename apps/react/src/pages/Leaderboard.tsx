/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { api, type Leaderboard as Board } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';

const TYPE_LABEL: Record<string, string> = {
  daily: '日榜',
  weekly: '周榜',
  monthly: '月榜',
  all: '总榜',
};

const METRIC_LABEL: Record<string, string> = {
  earned: '兑换收入',
  spent: '兑换支出',
  play_count: '开局次数',
};

/** 榜单以平台币 / 次数计分，展示时统一保留两位；分数是十进制字符串，不做数值运算 */
const score = (v: string) => {
  const n = Number(v);
  return Number.isFinite(n) ? n.toLocaleString('en-US', { maximumFractionDigits: 2 }) : v;
};

/**
 * 身份列只展示后 4 位。
 *
 * ⚠ 这是**展示层的选择，不等于后端已经修好**。后端契约（读盘核实，
 * `packages/platform-common/src/service/LeaderboardService.php`）每条目**只有三个键**：
 *   ['rank' => …, 'user_id' => $row->user_id, 'score' => …]
 * 两条硬事实：
 *  1. `user_id` **没过 `encodeId`**，报文里就是**裸 BIGINT**。打码只是不去显示它，
 *     **不消除报文里的原始主键** —— 打开 devtools 照样看得到。这跟本仓「跨 API 边界的
 *     ID 一律 hashid」的约定不符（同族已有 risk/rule/list 与 analytics 两例）。
 *  2. 接口**不提供昵称/头像**，所以这一列**先天只能显示编号**。
 *     `maskedId` 不是"做了个好看的别名"，是接口真没有名字可显示——别在这里编造昵称，
 *     也别为了显示名字去逐条拉 `/user/profile`（N+1，且多数拿不到）。
 * 要真正修好得改 `service`/`packages`（encodeId + 带上昵称），不在本树范围内。
 */
const maskedId = (id: number) => `#···${String(id).slice(-4)}`;

export function Leaderboard() {
  const boards = useAsync(() => api.leaderboards(), []);
  const [picked, setPicked] = useState('');

  const list = boards.data?.list ?? [];
  // 未手动选择时用第一张榜，省一个 useEffect 同步
  const current: Board | null = list.find((b) => b.id === picked) ?? list[0] ?? null;

  // 换榜时 useAsync 依赖 hashid 重新拉，不会残留上一张榜的名次
  const rank = useAsync(
    () => (current ? api.leaderboard(current.id) : Promise.resolve(null)),
    [current?.id ?? ''],
  );

  return (
    <>
      <section className="stack">
        <p className="label">排行榜</p>
        <h1 className="h1">
          排行榜
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          榜单数据由服务端缓存，榜单一小时刷新一次。
        </p>
      </section>

      <section className="stack">
        {boards.loading && <Loading />}
        {!boards.loading && boards.error && (
          <ErrorBox message={boards.error} onRetry={boards.reload} />
        )}
        {!boards.loading && !boards.error && list.length === 0 && (
          <Empty title="暂无排行榜" hint="运营开启后即可在这里查看" />
        )}

        {!boards.loading && !boards.error && list.length > 0 && (
          <div className="chips" role="tablist" aria-label="选择榜单">
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
            <span className="pill pill--yellow">{TYPE_LABEL[current.type] ?? current.type}</span>
            <span className="pill pill--plain">
              {METRIC_LABEL[current.metric] ?? current.metric}
            </span>
            {!current.game_id && <span className="pill pill--plain">全平台</span>}
          </div>

          {/* 说清「为什么只有编号」：接口按契约就只返回 rank/user_id/score 三个字段，
              没有昵称也没有头像。与其留一列空白让人以为是加载失败，不如写明。 */}
          <p className="small muted" style={{ margin: 0 }}>
            榜单接口只返回名次、编号与分数，不提供昵称与头像，故此处以编号后四位标识玩家。
          </p>

          {rank.loading && <Loading />}
          {!rank.loading && rank.error && <ErrorBox message={rank.error} onRetry={rank.reload} />}
          {!rank.loading && !rank.error && rank.data && rank.data.ranking.length === 0 && (
            <Empty title="榜单暂无数据" hint="有玩家上榜后会显示在这里" />
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
                  <span className="mono">{score(r.score)}</span>
                </div>
              ))}
            </div>
          )}
        </section>
      )}
    </>
  );
}
