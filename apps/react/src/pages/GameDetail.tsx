/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { ApiError, api } from '../lib/api.ts';
import { useAuth } from '../lib/auth.tsx';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';
import type { MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

/** 错误文案的**暂存形**：存「原始错误 + 兜底键」，**不存翻好的串**（理由见 `Exchange.tsx` 的 `Msg`）。 */
type Msg = { err: unknown; fallback: MessageKey };

export function GameDetail() {
  const { t } = useI18n();
  const { hashid = '' } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const detail = useAsync(() => api.gameDetail(hashid), [hashid]);
  const [session, setSession] = useState<{ session_id: string; api_endpoint: string | null } | null>(
    null,
  );
  const [launching, setLaunching] = useState(false);
  const [launchErr, setLaunchErr] = useState<Msg | null>(null);

  const launch = async () => {
    if (!user) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    setLaunchErr(null);
    setLaunching(true);
    try {
      setSession(await api.launch(hashid));
    } catch (e) {
      setLaunchErr({ err: e, fallback: 'error.launch_failed' });
    } finally {
      setLaunching(false);
    }
  };

  if (detail.loading) return <Loading />;
  if (detail.error) return <ErrorBox message={detail.error} onRetry={detail.reload} />;
  if (!detail.data) return null;

  const g = detail.data;

  return (
    <>
      <Link to="/" className="small" style={{ fontWeight: 700 }}>
        {t('game.back_library')}
      </Link>

      <div className="shell--split">
        <section className="stack">
          <div className="cover" style={{ aspectRatio: '16 / 9' }}>
            {g.cover_image ? (
              <img src={g.cover_image} alt="" />
            ) : (
              <span className="cover__ph">{g.name.slice(0, 2)}</span>
            )}
          </div>
          <div className="row">
            <span className="pill pill--yellow">{g.type}</span>
            {g.platform && <span className="pill pill--plain">{g.platform}</span>}
            {g.region && <span className="pill pill--plain">{g.region}</span>}
            {g.sdk_version && <span className="pill pill--plain">SDK {g.sdk_version}</span>}
          </div>
          {g.description && <p className="muted">{g.description}</p>}
        </section>

        <section className="stack">
          <h1 className="h2">{g.name}</h1>

          <button
            type="button"
            className="btn btn--primary btn--block"
            disabled={launching}
            onClick={launch}
          >
            {launching && <span className="spin" aria-hidden="true" />}
            {user ? t('game.launch') : t('game.login_to_launch')}
          </button>

          {launchErr && (
            <p className="err" role="alert">
              {launchErr.err instanceof ApiError ? launchErr.err.message : t(launchErr.fallback)}
            </p>
          )}

          {session && (
            <div className="card card--flat">
              <p className="card__fill fill-yellow" style={{ margin: '-18px -18px 14px' }}>
                <span>{t('game.session_created')}</span>
              </p>
              <p className="small muted" style={{ margin: 0 }}>
                Session ID
              </p>
              <p className="mono" style={{ wordBreak: 'break-all', margin: '4px 0 12px' }}>
                {session.session_id}
              </p>
              <p className="small muted" style={{ margin: 0 }}>
                API Endpoint
              </p>
              <p className="mono" style={{ wordBreak: 'break-all', margin: '4px 0 0' }}>
                {session.api_endpoint || '—'}
              </p>
            </div>
          )}

          <div className="stack">
            <p className="label">{t('game.currencies')}</p>
            {g.currencies && g.currencies.length > 0 ? (
              <div className="list">
                {g.currencies.map((c) => (
                  <div key={c.id} className="li">
                    <div>
                      <p className="li__t" style={{ margin: 0 }}>
                        {c.name}
                      </p>
                      <p className="small muted" style={{ margin: 0 }}>
                        {c.symbol}
                        {/* ⚠ 表里那条 `game.spread` 的 zh 值**带一个前导空格** —— 它逐字抄自 HEAD
                            的模板 ` · 点差 ${…}%`，那个空格是 `{c.symbol}` 与点差之间的**分隔符**
                            （两个 JSX 表达式之间的纯空白文本节点会被丢掉），去掉就渲染成
                            `USD· 点差 2%`。同时 `verify-zh.mjs` 的 A 面要的正是整条带空格的串。 */}
                        {c.spread_pct ? t('game.spread', { pct: c.spread_pct }) : ''}
                      </p>
                    </div>
                    <span className="mono">{c.exchange_rate ?? '—'}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="muted small">{t('game.no_currencies')}</p>
            )}
          </div>
        </section>
      </div>
    </>
  );
}
