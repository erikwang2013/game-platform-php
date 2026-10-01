/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Link, useParams } from 'react-router-dom';
import { api } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';

/** 公告是公开接口（无鉴权），未登录也能看 */
export function Announcements() {
  const list = useAsync(() => api.announcements(), []);

  return (
    <>
      <section className="stack">
        <p className="label">平台公告</p>
        <h1 className="h1">
          公告
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          活动、维护与规则变更都会在这里发布。服务端只返回最近 20 条。
        </p>
      </section>

      <section className="stack">
        {list.loading && <Loading />}
        {!list.loading && list.error && <ErrorBox message={list.error} onRetry={list.reload} />}
        {!list.loading && !list.error && list.data && list.data.list.length === 0 && (
          <Empty title="暂无公告" hint="有新公告时会显示在这里" />
        )}
        {!list.loading && !list.error && list.data && list.data.list.length > 0 && (
          <div className="list">
            {list.data.list.map((a) => (
              <Link key={a.id} to={`/announcements/${a.id}`} className="li">
                <div>
                  <p className="li__t" style={{ margin: 0 }}>
                    {a.title}
                  </p>
                  <p className="small muted" style={{ margin: '4px 0 0' }}>
                    <span className="pill pill--plain">{a.type}</span> {dt(a.created_at)}
                  </p>
                </div>
                <span className="small muted" aria-hidden="true">
                  ›
                </span>
              </Link>
            ))}
          </div>
        )}
      </section>
    </>
  );
}

export function AnnouncementDetail() {
  const { hashid = '' } = useParams();
  const detail = useAsync(() => api.announcement(hashid), [hashid]);

  if (detail.loading) return <Loading />;
  if (detail.error) return <ErrorBox message={detail.error} onRetry={detail.reload} />;
  if (!detail.data) return null;

  const a = detail.data;

  return (
    <>
      <Link to="/announcements" className="small" style={{ fontWeight: 700 }}>
        ← 返回公告列表
      </Link>

      <section className="stack">
        <h1 className="h2" style={{ margin: 0 }}>
          {a.title}
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          <span className="pill pill--plain">{a.type}</span> {dt(a.created_at)}
        </p>
      </section>

      {/* 正文按纯文本渲染：服务端存的是运营录入的文本，不解析 HTML */}
      <section className="card card--flat">
        <p style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{a.content}</p>
      </section>
    </>
  );
}
