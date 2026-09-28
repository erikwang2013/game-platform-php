/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../lib/api.ts';
import { deleteErrorMessage, deleteUnknownMessage, deleteVerdict } from '../lib/accountDeletion.ts';
import { useAuth } from '../lib/auth.tsx';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';

export function Me() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [busy, setBusy] = useState(false);

  const notices = useAsync(() => api.notices({ page }), [page]);
  const unread = useAsync(() => api.unreadCount(), []);

  const refresh = () => {
    notices.reload();
    unread.reload();
  };

  const markRead = async (id?: string) => {
    setBusy(true);
    try {
      await api.markRead(id);
      refresh();
    } catch {
      // 标记失败保留原状态，用户可重试
    } finally {
      setBusy(false);
    }
  };

  const onLogout = () => {
    logout();
    navigate('/');
  };

  const [delOpen, setDelOpen] = useState(false);
  const [delPw, setDelPw] = useState('');
  const [delYes, setDelYes] = useState('');
  const [delBusy, setDelBusy] = useState(false);
  const [delError, setDelError] = useState<string | null>(null);

  const cancelDelete = () => {
    setDelPw('');
    setDelYes('');
    setDelError(null);
    setDelOpen(false);
  };

  const submitDelete = async () => {
    if (delBusy) return;
    setDelBusy(true);
    setDelError(null);
    try {
      await api.deleteAccount(delPw, delYes);
    } catch (e) {
      setDelBusy(false);
      setDelError(deleteErrorMessage(e));
      return;
    }
    // 成功不以「请求发出去了」为准：回读确认账号真的取不到
    let gone: boolean;
    try {
      gone = await api.accountGone();
    } catch (e) {
      setDelBusy(false);
      setDelError(deleteUnknownMessage(e));
      return;
    }
    setDelBusy(false);
    const verdict = deleteVerdict(gone);
    if (!verdict.ok) {
      setDelError(verdict.message);
      return;
    }
    setDelPw('');
    setDelYes('');
    logout();
    navigate('/');
  };

  return (
    <>
      <h1 className="h1">
        我的
        <span style={{ color: 'var(--orange)' }}>.</span>
      </h1>

      <div className="shell--split">
        <section className="stack">
          <p className="label">账号信息</p>
          <div className="card card--flat">
            <div className="row" style={{ gap: 16 }}>
              <div
                className="cover"
                style={{ width: 72, height: 72, aspectRatio: '1 / 1', margin: 0, flexShrink: 0 }}
              >
                {user?.avatar ? (
                  <img src={user.avatar} alt="" />
                ) : (
                  <span className="cover__ph" style={{ fontSize: 24 }}>
                    {(user?.nickname || user?.username || '?').slice(0, 1)}
                  </span>
                )}
              </div>
              <div>
                <p className="h3" style={{ margin: 0 }}>
                  {user?.nickname || user?.username}
                </p>
                <p className="small muted" style={{ margin: '4px 0 0' }}>
                  @{user?.username}
                </p>
              </div>
            </div>

            <div className="list" style={{ marginTop: 18 }}>
              <div className="li">
                <span className="small muted">用户 ID</span>
                <span className="mono">{user?.id ?? '—'}</span>
              </div>
              <div className="li">
                <span className="small muted">邮箱</span>
                <span className="small">{user?.email || '未绑定'}</span>
              </div>
              <div className="li">
                <span className="small muted">注册时间</span>
                <span className="small">{user?.created_at || '—'}</span>
              </div>
            </div>

            <button
              type="button"
              className="btn btn--block"
              style={{ marginTop: 18 }}
              onClick={onLogout}
            >
              退出登录
            </button>
          </div>
        </section>

        <section className="stack">
          <div className="between">
            <p className="label" style={{ flex: 1 }}>
              通知
            </p>
            {unread.data && unread.data.count > 0 && (
              <span className="pill pill--orange">{unread.data.count} 条未读</span>
            )}
          </div>

          <button
            type="button"
            className="btn btn--sm"
            disabled={busy || !unread.data?.count}
            onClick={() => markRead()}
          >
            全部标为已读
          </button>

          {notices.loading && <Loading />}
          {!notices.loading && notices.error && (
            <ErrorBox message={notices.error} onRetry={notices.reload} />
          )}
          {!notices.loading && !notices.error && notices.data && notices.data.items.length === 0 && (
            <div className="state">
              <p className="state__k">暂无通知</p>
            </div>
          )}
          {!notices.loading && !notices.error && notices.data && notices.data.items.length > 0 && (
            <>
              <div className="list">
                {notices.data.items.map((n) => (
                  <div key={n.id} className={`li li--start${n.is_read ? '' : ' unread'}`}>
                    <div>
                      <p className="li__t" style={{ margin: 0 }}>
                        {n.title}
                      </p>
                      <p className="small muted" style={{ margin: '4px 0 6px' }}>
                        {n.content}
                      </p>
                      <p className="small muted" style={{ margin: 0 }}>
                        <span className="pill pill--plain">{n.type}</span> {n.created_at}
                      </p>
                    </div>
                    {!n.is_read && (
                      <button
                        type="button"
                        className="btn btn--sm"
                        disabled={busy}
                        onClick={() => markRead(n.id)}
                      >
                        已读
                      </button>
                    )}
                  </div>
                ))}
              </div>

              {notices.data.last_page > 1 && (
                <div className="between">
                  <button
                    type="button"
                    className="btn btn--sm"
                    disabled={page <= 1 || notices.loading}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                  >
                    上一页
                  </button>
                  <span className="small muted">
                    {notices.data.page} / {notices.data.last_page}
                  </span>
                  <button
                    type="button"
                    className="btn btn--sm"
                    disabled={page >= notices.data.last_page || notices.loading}
                    onClick={() => setPage((p) => p + 1)}
                  >
                    下一页
                  </button>
                </div>
              )}
            </>
          )}
        </section>
      </div>

      <section className="stack" style={{ marginTop: 32 }} aria-label="注销账号">
        <p className="label">注销账号</p>
        <div className="card card--flat">
          <div className="between">
            <p className="h3" style={{ margin: 0 }}>
              注销账号
            </p>
            <span className="pill pill--orange">不可撤销</span>
          </div>
          <p className="small muted" style={{ margin: '10px 0 0', maxWidth: '62ch' }}>
            注销后该账号无法再登录，个人资料会被匿名化。账号内余额需先自行提现清零，否则服务端会拒绝注销。
          </p>

          {delError && (
            <p className="err" role="alert" style={{ marginTop: 14 }}>
              {delError}
            </p>
          )}

          {delOpen ? (
            <div className="stack" style={{ marginTop: 16, maxWidth: 380 }}>
              <label className="field">
                <span>当前密码</span>
                <input
                  className="input"
                  type="password"
                  autoComplete="current-password"
                  placeholder="请输入当前密码"
                  value={delPw}
                  onChange={(e) => {
                    setDelPw(e.target.value);
                    setDelError(null);
                  }}
                />
              </label>
              <label className="field">
                <span>确认注销（输入 yes）</span>
                <input
                  className="input mono"
                  autoComplete="off"
                  placeholder="yes"
                  value={delYes}
                  onChange={(e) => {
                    setDelYes(e.target.value);
                    setDelError(null);
                  }}
                />
              </label>
              <div className="row" style={{ gap: 10 }}>
                <button
                  type="button"
                  className="btn btn--sm"
                  disabled={delBusy}
                  onClick={submitDelete}
                >
                  {delBusy ? '注销中…' : '确认注销'}
                </button>
                <button
                  type="button"
                  className="btn btn--sm"
                  disabled={delBusy}
                  onClick={cancelDelete}
                >
                  取消
                </button>
              </div>
            </div>
          ) : (
            <button
              type="button"
              className="btn btn--sm"
              style={{ marginTop: 16 }}
              onClick={() => {
                setDelError(null);
                setDelOpen(true);
              }}
            >
              注销账号
            </button>
          )}
        </div>
      </section>
    </>
  );
}
