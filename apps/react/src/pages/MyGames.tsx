/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api, type PlayLog } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { Modal } from '../components/CaptchaModal.tsx';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';

/**
 * `game_game_play_log.action` 的取值。**两个来源合起来才是全集**，只看哪一个都会漏：
 *  ① 代码实际写的 5 个：`start`（GameController:291，直接建行）、`launch`（同文件 :296，
 *     走 `GamePlayLogService::write`）、`bet`/`settle`/`refund`（ProviderController:105,151,212
 *     与 GameSdkController:96,148,224，经 GamePlayRecorder）。
 *  ② `install/install.sql:576` 的列注释只列了 `start/end/earn/spend` —— 其中 `end`/`earn`/`spend`
 *     **全仓没有写入点**（概率服务 ProbabilityService:19 也按 `earn` 举例），留着兜底旧数据。
 * 服务端按 `action` 筛选是等值匹配，未知值原样透出，不猜。
 */
const ACTION_LABEL: Record<string, string> = {
  start: '开始',
  launch: '启动',
  bet: '下注',
  settle: '结算',
  refund: '退还',
  end: '结束',
  earn: '赢取',
  spend: '消耗',
};

/** 变动是带符号的十进制字符串（正=赚、负=花），只判符号位，不做数值运算 */
const isNegative = (s: string) => s.trim().startsWith('-');

/** 金额为 0 的字符串（服务端 DECIMAL(18,4) 下发 "0.0000"）—— 判零只用字符串，不转数值 */
const isZeroAmount = (v: string) => /^0+(\.0+)?$/.test(v.trim());

/**
 * `metadata` 是写侧 `json_encode` 的**原文**（TEXT 列、模型没有 cast ⇒ 拿到的是字符串不是对象）。
 * 能解析就缩进展示，解析不了原样贴 —— 不猜字段名、也不吞掉内容。
 */
const prettyJson = (raw: string): string => {
  try {
    return JSON.stringify(JSON.parse(raw), null, 2);
  } catch {
    return raw;
  }
};

export function MyGames() {
  const [page, setPage] = useState(1);
  const assets = useAsync(() => api.gameBalances(), []);
  const logs = useAsync(() => api.playLogs({ page }), [page]);

  // 详情：列表行里没有的字段（变动前后 / 平台币侧 / 会话窗口 / metadata）只在 detail 回。
  // 标题先用手上这行的动作顶着，详情回来再渲染正文，避免弹框空一瞬。
  const [cur, setCur] = useState<PlayLog | null>(null);
  const detail = useAsync(
    () => (cur ? api.playLogDetail(cur.id) : Promise.resolve(null)),
    [cur],
  );

  const games = assets.data?.games ?? [];
  const curLabel = cur ? (ACTION_LABEL[cur.action] ?? cur.action) : '';

  return (
    <>
      <section className="stack">
        <p className="label">我的游戏</p>
        <h1 className="h1">
          游戏资产
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          游戏内货币余额，与平台币分开记账。<Link to="/wallet">返回钱包</Link>
        </p>
      </section>

      <section className="stack">
        {assets.loading && <Loading />}
        {!assets.loading && assets.error && (
          <ErrorBox message={assets.error} onRetry={assets.reload} />
        )}
        {!assets.loading && !assets.error && games.length === 0 && (
          <Empty title="还没有游戏资产" hint="启动一款游戏并兑换游戏币后会显示在这里" />
        )}
        {!assets.loading && !assets.error && games.length > 0 && (
          <div className="grid">
            {games.map((g) => (
              <div key={g.game_id} className="card card--flat">
                <div className="between">
                  <h2 className="h3" style={{ margin: 0 }}>
                    {g.name}
                  </h2>
                  <span className="pill pill--plain">{g.type}</span>
                </div>
                <div className="list" style={{ marginTop: 12 }}>
                  {g.currencies.map((c) => (
                    <div key={c.currency_id} className="li">
                      <div>
                        <p className="li__t" style={{ margin: 0 }}>
                          {c.name}
                        </p>
                        <p className="small muted" style={{ margin: 0 }}>
                          {c.symbol}
                          {isZeroAmount(c.frozen_balance) ? '' : ` · 冻结 ${c.frozen_balance}`}
                        </p>
                      </div>
                      <span className="mono">{c.balance}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      <section className="stack">
        <p className="label">最近战绩</p>

        {logs.loading && <Loading />}
        {!logs.loading && logs.error && <ErrorBox message={logs.error} onRetry={logs.reload} />}
        {!logs.loading && !logs.error && logs.data && logs.data.items.length === 0 && (
          <Empty title="暂无游戏记录" hint="开始一局游戏后会出现记录" />
        )}
        {!logs.loading && !logs.error && logs.data && logs.data.items.length > 0 && (
          <>
            <div className="list">
              {logs.data.items.map((l) => (
                <button
                  key={l.id}
                  type="button"
                  className="li li--start"
                  onClick={() => setCur(l)}
                >
                  <div>
                    <p className="li__t" style={{ margin: 0 }}>
                      <span className="pill pill--plain">{ACTION_LABEL[l.action] ?? l.action}</span>
                    </p>
                    <p className="small muted mono" style={{ margin: '4px 0 0' }}>
                      {l.session_id || '—'} · {dt(l.created_at)}
                    </p>
                  </div>
                  <span
                    className={`amt ${isNegative(l.game_amount_change) ? 'amt--out' : 'amt--in'}`}
                  >
                    {isNegative(l.game_amount_change) ? '' : '+'}
                    {l.game_amount_change}
                  </span>
                </button>
              ))}
            </div>

            {logs.data.last_page > 1 && (
              <div className="between">
                <button
                  type="button"
                  className="btn btn--sm"
                  disabled={page <= 1 || logs.loading}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  上一页
                </button>
                <span className="small muted">
                  {logs.data.page} / {logs.data.last_page}
                </span>
                <button
                  type="button"
                  className="btn btn--sm"
                  disabled={page >= logs.data.last_page || logs.loading}
                  onClick={() => setPage((p) => p + 1)}
                >
                  下一页
                </button>
              </div>
            )}
          </>
        )}
      </section>

      {cur && (
        <Modal title={`记录详情 · ${curLabel}`} onClose={() => setCur(null)}>
          {detail.loading && <Loading />}
          {!detail.loading && detail.error && (
            <ErrorBox message={detail.error} onRetry={detail.reload} />
          )}
          {!detail.loading && !detail.error && detail.data && (
            <>
              {/*
                时间字段两种格式并存（见 types.game.ts 的 PlayLogDetail 注释）：
                `created_at` 是墙钟串、`started_at`/`ended_at` 是 ISO8601 UTC。
                三个都过 `dt()` —— 只对 ISO 那两个做时区换算，墙钟那个行为不变。
              */}
              <div className="list">
                <div className="li">
                  <span className="small muted">记录时间</span>
                  <span className="small">{dt(detail.data.created_at)}</span>
                </div>
                <div className="li">
                  <span className="small muted">会话</span>
                  <span className="mono small">{detail.data.session_id || '—'}</span>
                </div>
                <div className="li">
                  <span className="small muted">变动前</span>
                  <span className="mono">{detail.data.game_amount_before}</span>
                </div>
                <div className="li">
                  <span className="small muted">变动</span>
                  <span
                    className={`mono ${isNegative(detail.data.game_amount_change) ? 'amt--out' : 'amt--in'}`}
                  >
                    {isNegative(detail.data.game_amount_change) ? '' : '+'}
                    {detail.data.game_amount_change}
                  </span>
                </div>
                <div className="li">
                  <span className="small muted">变动后</span>
                  <span className="mono">{detail.data.game_amount_after}</span>
                </div>
                <div className="li">
                  <span className="small muted">平台币变动</span>
                  <span className="mono">{detail.data.platform_amount_change}</span>
                </div>
                {(detail.data.started_at || detail.data.ended_at) && (
                  <div className="li">
                    <span className="small muted">开始 / 结束</span>
                    <span className="small">
                      {dt(detail.data.started_at)} / {dt(detail.data.ended_at)}
                    </span>
                  </div>
                )}
                <div className="li">
                  <span className="small muted">游戏</span>
                  <Link to={`/game/${detail.data.game_id}`} onClick={() => setCur(null)}>
                    {games.find((g) => g.game_id === detail.data?.game_id)?.name || '查看游戏'}
                  </Link>
                </div>
              </div>

              {detail.data.metadata && detail.data.metadata.trim() !== '' && (
                <>
                  <p className="label">自定义数据</p>
                  <pre
                    className="mono small card card--flat"
                    style={{ margin: 0, maxHeight: 220, overflow: 'auto' }}
                  >
                    {prettyJson(detail.data.metadata)}
                  </pre>
                </>
              )}
            </>
          )}
        </Modal>
      )}
    </>
  );
}
