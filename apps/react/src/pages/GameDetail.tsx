/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { ApiError, api } from '../lib/api.ts';
import { useAuth } from '../lib/auth.tsx';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';

export function GameDetail() {
  const { hashid = '' } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const detail = useAsync(() => api.gameDetail(hashid), [hashid]);
  const [session, setSession] = useState<{ session_id: string; api_endpoint: string | null } | null>(
    null,
  );
  const [launching, setLaunching] = useState(false);
  const [launchErr, setLaunchErr] = useState<string | null>(null);

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
      setLaunchErr(e instanceof ApiError ? e.message : '启动失败，请稍后重试');
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
        ← 返回游戏库
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
            {user ? '启动游戏' : '登录后启动'}
          </button>

          {launchErr && <p className="err" role="alert">{launchErr}</p>}

          {session && (
            <div className="card card--flat">
              <p className="card__fill fill-yellow" style={{ margin: '-18px -18px 14px' }}>
                <span>会话已创建</span>
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
            <p className="label">支持币种</p>
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
                        {c.spread_pct ? ` · 点差 ${c.spread_pct}%` : ''}
                      </p>
                    </div>
                    <span className="mono">{c.exchange_rate ?? '—'}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="muted small">暂无币种信息</p>
            )}
          </div>
        </section>
      </div>
    </>
  );
}
