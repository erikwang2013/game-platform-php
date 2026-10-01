/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api, type FriendUser } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';
import { Avatar } from '../components/Avatar.tsx';

const nameOf = (u: FriendUser) => u.nickname || u.username;

export function Friends() {
  const [tab, setTab] = useState<'list' | 'requests' | 'add'>('list');
  const friends = useAsync(() => api.friends(), []);
  const requests = useAsync(() => api.friendRequests(), []);

  const [busy, setBusy] = useState('');
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);

  // 搜索是显式触发的（不是输入即搜）：服务端没有防抖，逐字打请求会把 20 条限流撞满
  const [q, setQ] = useState('');
  const [hits, setHits] = useState<FriendUser[] | null>(null);
  const [searching, setSearching] = useState(false);

  const err = (e: unknown, fallback: string) =>
    setMsg({ ok: false, text: e instanceof ApiError ? e.message : fallback });

  const act = async (key: string, fn: () => Promise<unknown>, okText: string) => {
    if (busy) return;
    setBusy(key);
    setMsg(null);
    try {
      await fn();
      setMsg({ ok: true, text: okText });
      // 三个列表互相影响（接受申请会同时改好友和申请），统一全刷，省得漏一边
      friends.reload();
      requests.reload();
    } catch (e) {
      err(e, '操作失败，请稍后重试');
    } finally {
      setBusy('');
    }
  };

  const doSearch = async (e: FormEvent) => {
    e.preventDefault();
    if (searching || !q.trim()) return;
    setSearching(true);
    setMsg(null);
    try {
      setHits((await api.friendSearch(q.trim())).list);
    } catch (e2) {
      setHits(null);
      err(e2, '搜索失败，请稍后重试');
    } finally {
      setSearching(false);
    }
  };

  const friendList = friends.data?.list ?? [];
  const reqList = requests.data?.list ?? [];
  // 已经是好友的别再给「加好友」按钮（服务端会 422「已存在或待处理」，提示不好看）
  const friendIds = new Set(friendList.map((f) => f.id));

  return (
    <>
      <section className="stack">
        <p className="label">社交</p>
        <h1 className="h1">
          好友
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
      </section>

      <section className="stack">
        <div className="tabs" role="tablist">
          {(
            [
              ['list', `好友${friendList.length ? ` ${friendList.length}` : ''}`],
              ['requests', `申请${reqList.length ? ` ${reqList.length}` : ''}`],
              ['add', '添加'],
            ] as const
          ).map(([k, label]) => (
            <button
              key={k}
              type="button"
              role="tab"
              aria-selected={tab === k}
              className={`tabs__b${tab === k ? ' is-on' : ''}`}
              onClick={() => {
                setTab(k);
                setMsg(null);
              }}
            >
              {label}
            </button>
          ))}
        </div>

        {msg && (
          <p className={msg.ok ? 'small' : 'err'} role={msg.ok ? 'status' : 'alert'}>
            {msg.text}
          </p>
        )}

        {/* ---- 好友 ---- */}
        {tab === 'list' && (
          <>
            {friends.loading && <Loading />}
            {!friends.loading && friends.error && (
              <ErrorBox message={friends.error} onRetry={friends.reload} />
            )}
            {!friends.loading && !friends.error && friendList.length === 0 && (
              <Empty title="还没有好友" hint="去「添加」按用户名搜人" />
            )}
            {!friends.loading && !friends.error && friendList.length > 0 && (
              <div className="list">
                {friendList.map((f) => (
                  <div key={f.id} className="li li--start">
                    <div className="row" style={{ gap: 10 }}>
                      <Avatar stored={f.avatar} name={nameOf(f)} />
                      <div>
                        <p className="li__t" style={{ margin: 0 }}>
                          {nameOf(f)}
                        </p>
                        <p className="small muted" style={{ margin: '2px 0 0' }}>
                          @{f.username}
                        </p>
                      </div>
                    </div>
                    <div className="row" style={{ gap: 8 }}>
                      <Link className="btn btn--sm" to={`/chat/${f.id}`}>
                        发消息
                      </Link>
                      <button
                        type="button"
                        className="btn btn--sm"
                        disabled={busy === f.id}
                        onClick={() => act(f.id, () => api.friendRemove(f.id), `已删除好友 ${nameOf(f)}`)}
                      >
                        {busy === f.id ? '…' : '删除'}
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </>
        )}

        {/* ---- 收到的申请 ---- */}
        {tab === 'requests' && (
          <>
            {requests.loading && <Loading />}
            {!requests.loading && requests.error && (
              <ErrorBox message={requests.error} onRetry={requests.reload} />
            )}
            {!requests.loading && !requests.error && reqList.length === 0 && (
              <Empty title="没有待处理的申请" hint="别人加你时会出现在这里" />
            )}
            {!requests.loading && !requests.error && reqList.length > 0 && (
              <div className="list">
                {reqList.map((r) => (
                  <div key={r.id} className="li li--start">
                    <div className="row" style={{ gap: 10 }}>
                      <Avatar stored={r.user.avatar} name={nameOf(r.user)} />
                      <div>
                        <p className="li__t" style={{ margin: 0 }}>
                          {nameOf(r.user)}
                        </p>
                        <p className="small muted" style={{ margin: '2px 0 0' }}>
                          @{r.user.username} · {dt(r.created_at)}
                        </p>
                      </div>
                    </div>
                    <div className="row" style={{ gap: 8 }}>
                      {/* 传的是**关系 id**（r.id），不是用户 id —— 混用服务端会 404 */}
                      <button
                        type="button"
                        className="btn btn--sm btn--primary"
                        disabled={busy === `a${r.id}`}
                        onClick={() =>
                          act(`a${r.id}`, () => api.friendAccept(r.id), `已接受 ${nameOf(r.user)}`)
                        }
                      >
                        {busy === `a${r.id}` ? '…' : '接受'}
                      </button>
                      <button
                        type="button"
                        className="btn btn--sm"
                        disabled={busy === `r${r.id}`}
                        onClick={() =>
                          act(`r${r.id}`, () => api.friendReject(r.id), `已拒绝 ${nameOf(r.user)}`)
                        }
                      >
                        {busy === `r${r.id}` ? '…' : '拒绝'}
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </>
        )}

        {/* ---- 添加 ---- */}
        {tab === 'add' && (
          <>
            <form className="search" onSubmit={doSearch}>
              <input
                className="input"
                name="q"
                type="search"
                placeholder="按用户名或昵称搜索"
                autoComplete="off"
                value={q}
                onChange={(e) => setQ(e.target.value)}
              />
              <button type="submit" className="btn btn--primary" disabled={searching || !q.trim()}>
                {searching ? '搜索中…' : '搜索'}
              </button>
            </form>

            {hits !== null && hits.length === 0 && (
              <Empty title="没有找到匹配的用户" hint="换个用户名或昵称试试" />
            )}
            {hits !== null && hits.length > 0 && (
              <div className="list">
                {hits.map((u) => (
                  <div key={u.id} className="li li--start">
                    <div className="row" style={{ gap: 10 }}>
                      <Avatar stored={u.avatar} name={nameOf(u)} />
                      <div>
                        <p className="li__t" style={{ margin: 0 }}>
                          {nameOf(u)}
                        </p>
                        <p className="small muted" style={{ margin: '2px 0 0' }}>
                          @{u.username}
                        </p>
                      </div>
                    </div>
                    {friendIds.has(u.id) ? (
                      <span className="pill pill--plain">已是好友</span>
                    ) : (
                      <button
                        type="button"
                        className="btn btn--sm btn--primary"
                        disabled={busy === u.id}
                        onClick={() =>
                          act(u.id, () => api.friendRequest(u.id), `已向 ${nameOf(u)} 发送申请`)
                        }
                      >
                        {busy === u.id ? '…' : '加好友'}
                      </button>
                    )}
                  </div>
                ))}
              </div>
            )}
          </>
        )}
      </section>
    </>
  );
}
