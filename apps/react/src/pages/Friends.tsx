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
import type { MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

const nameOf = (u: FriendUser) => u.nickname || u.username;

/**
 * 提示文案的**暂存形**：成功存「键 + 参数」、失败存「原始错误 + 兜底键」，
 * **两边都不存翻好的串** —— `act()` 在 await 之后才落值，存串就把语言冻在点击那一刻
 * （与 `Exchange.tsx` 的 `Msg` 同款；`friends.tab_list` 那类带插值的模板同理）。
 */
type Msg =
  | { ok: true; key: MessageKey; params?: Record<string, string | number> }
  | { ok: false; err: unknown; fallback: MessageKey };

export function Friends() {
  const { t } = useI18n();
  const [tab, setTab] = useState<'list' | 'requests' | 'add'>('list');
  const friends = useAsync(() => api.friends(), []);
  const requests = useAsync(() => api.friendRequests(), []);

  const [busy, setBusy] = useState('');
  const [msg, setMsg] = useState<Msg | null>(null);

  // 搜索是显式触发的（不是输入即搜）：服务端没有防抖，逐字打请求会把 20 条限流撞满
  const [q, setQ] = useState('');
  const [hits, setHits] = useState<FriendUser[] | null>(null);
  const [searching, setSearching] = useState(false);

  /** 只登记「原始错误 + 兜底键」，翻好是渲染时的事（见 `Msg`）。 */
  const err = (e: unknown, fallback: MessageKey) => setMsg({ ok: false, err: e, fallback });

  const act = async (
    key: string,
    fn: () => Promise<unknown>,
    okKey: MessageKey,
    params?: Record<string, string | number>,
  ) => {
    if (busy) return;
    setBusy(key);
    setMsg(null);
    try {
      await fn();
      setMsg({ ok: true, key: okKey, params });
      // 三个列表互相影响（接受申请会同时改好友和申请），统一全刷，省得漏一边
      friends.reload();
      requests.reload();
    } catch (e) {
      err(e, 'error.action_failed');
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
      err(e2, 'error.search_failed');
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
        <p className="label">{t('friends.label')}</p>
        <h1 className="h1">
          {t('nav.friends')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
      </section>

      <section className="stack">
        <div className="tabs" role="tablist">
          {(
            [
              // ⚠ 计数走 `{n}` 参数而不是在源码里拼 `t(...) + ' 3'`：HEAD 那一行是**一条**
              // 模板字面量，`verify-zh.mjs` 的 A 面按整条归一（`好友{}`）找表里的值，
              // 拆开就凑不出它。空计数传空串，渲染与 HEAD 逐字相同（`好友` / `好友 3`）。
              ['list', t('friends.tab_list', { n: friendList.length ? ` ${friendList.length}` : '' })],
              [
                'requests',
                t('friends.tab_requests', { n: reqList.length ? ` ${reqList.length}` : '' }),
              ],
              ['add', t('friends.tab_add')],
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
            {msg.ok
              ? t(msg.key, msg.params)
              : // 服务端的 message 语义比本地兜底键准（如「已经是好友了」这类 422 文案），
                // 有就用它；它不带 code，所以这里不套 `error.with_code`（HEAD 也没有）。
                msg.err instanceof ApiError
                ? msg.err.message
                : t(msg.fallback)}
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
              <Empty title={t('friends.empty_title')} hint={t('friends.empty_hint')} />
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
                        {t('app.message')}
                      </Link>
                      <button
                        type="button"
                        className="btn btn--sm"
                        disabled={busy === f.id}
                        onClick={() =>
                          act(f.id, () => api.friendRemove(f.id), 'friends.removed', {
                            name: nameOf(f),
                          })
                        }
                      >
                        {busy === f.id ? '…' : t('app.delete')}
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
              <Empty title={t('friends.req_empty_title')} hint={t('friends.req_empty_hint')} />
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
                          act(`a${r.id}`, () => api.friendAccept(r.id), 'friends.accepted', {
                            name: nameOf(r.user),
                          })
                        }
                      >
                        {busy === `a${r.id}` ? '…' : t('friends.accept')}
                      </button>
                      <button
                        type="button"
                        className="btn btn--sm"
                        disabled={busy === `r${r.id}`}
                        onClick={() =>
                          act(`r${r.id}`, () => api.friendReject(r.id), 'friends.rejected', {
                            name: nameOf(r.user),
                          })
                        }
                      >
                        {busy === `r${r.id}` ? '…' : t('friends.reject')}
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
                placeholder={t('friends.search_placeholder')}
                autoComplete="off"
                value={q}
                onChange={(e) => setQ(e.target.value)}
              />
              <button type="submit" className="btn btn--primary" disabled={searching || !q.trim()}>
                {searching ? t('friends.searching') : t('app.search')}
              </button>
            </form>

            {hits !== null && hits.length === 0 && (
              <Empty title={t('friends.no_hit_title')} hint={t('friends.no_hit_hint')} />
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
                      <span className="pill pill--plain">{t('friends.already')}</span>
                    ) : (
                      <button
                        type="button"
                        className="btn btn--sm btn--primary"
                        disabled={busy === u.id}
                        onClick={() =>
                          act(u.id, () => api.friendRequest(u.id), 'friends.sent', {
                            name: nameOf(u),
                          })
                        }
                      >
                        {busy === u.id ? '…' : t('friends.add')}
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
