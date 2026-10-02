/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../lib/api.ts';
import { useAuth } from '../lib/auth.tsx';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { useAvatar } from '../lib/avatar.ts';
import { t } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';
import { ErrorBox, Loading } from '../components/States.tsx';
import { msgText, type Msg } from '../lib/message.ts';
import { DeletePanel, ExportPanel } from './MePanels.tsx';

export function Me() {
  // 只为订阅语言变更引起的重渲染；文案求值走模块级的 t()
  useI18n();
  const { user, logout, refreshProfile } = useAuth();
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [busy, setBusy] = useState(false);

  // 昵称是账号里唯一能在本页改的字段（换头像要选图上传统，本页没做；上传能力见 lib/upload.ts）
  // 头像落库值可能是上传后的相对地址，<img src> 带不上 token ⇒ 走 useAvatar 取字节
  const meAvatar = useAvatar(user?.avatar);
  const [nickOpen, setNickOpen] = useState(false);
  const [nick, setNick] = useState('');
  const [nickBusy, setNickBusy] = useState(false);
  const [nickMsg, setNickMsg] = useState<Msg | null>(null);

  const openNick = () => {
    setNick(user?.nickname ?? '');
    setNickMsg(null);
    setNickOpen(true);
  };

  const saveNick = async () => {
    if (nickBusy) return;
    const v = nick.trim();
    if (v.length === 0) {
      setNickMsg({ ok: false, key: 'me.nickname_required' });
      return;
    }
    setNickBusy(true);
    setNickMsg(null);
    try {
      await api.updateProfile({ nickname: v });
      // 以服务端返回的值为准：改完重新拉资料，别让本地输入当第二真值源
      await refreshProfile();
      setNickMsg({ ok: true, key: 'app.saved' });
      setNickOpen(false);
    } catch (e) {
      setNickMsg({ ok: false, err: e });
    } finally {
      setNickBusy(false);
    }
  };

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

  return (
    <>
      <h1 className="h1">
        {t('nav.me')}
        <span style={{ color: 'var(--orange)' }}>.</span>
      </h1>

      <div className="shell--split">
        <section className="stack">
          <p className="label">{t('me.account_info')}</p>
          <div className="card card--flat">
            <div className="row" style={{ gap: 16 }}>
              <div
                className="cover"
                style={{ width: 72, height: 72, aspectRatio: '1 / 1', margin: 0, flexShrink: 0 }}
              >
                {meAvatar ? (
                  <img src={meAvatar} alt="" />
                ) : (
                  <span className="cover__ph" style={{ fontSize: 24 }}>
                    {(user?.nickname || user?.username || '?').slice(0, 1)}
                  </span>
                )}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <p className="h3" style={{ margin: 0 }}>
                  {user?.nickname || user?.username}
                </p>
                <p className="small muted" style={{ margin: '4px 0 0' }}>
                  @{user?.username}
                </p>
              </div>
              {!nickOpen && (
                <button type="button" className="btn btn--sm" onClick={openNick}>
                  {t('me.change_nickname')}
                </button>
              )}
            </div>

            {nickMsg && (
              <p
                className={nickMsg.ok ? 'small' : 'err'}
                role={nickMsg.ok ? 'status' : 'alert'}
                style={{ margin: '12px 0 0' }}
              >
                {msgText(nickMsg, 'me.save_failed')}
              </p>
            )}

            {nickOpen && (
              <div className="stack" style={{ marginTop: 14, maxWidth: 380 }}>
                <label className="field">
                  <span>{t('me.nickname_label')}</span>
                  <input
                    className="input"
                    autoComplete="off"
                    maxLength={50}
                    value={nick}
                    onChange={(e) => {
                      setNick(e.target.value);
                      setNickMsg(null);
                    }}
                  />
                </label>
                <div className="row" style={{ gap: 10 }}>
                  <button
                    type="button"
                    className="btn btn--sm btn--primary"
                    disabled={nickBusy}
                    onClick={saveNick}
                  >
                    {nickBusy ? t('app.saving') : t('app.save')}
                  </button>
                  <button
                    type="button"
                    className="btn btn--sm"
                    disabled={nickBusy}
                    onClick={() => {
                      setNickOpen(false);
                      setNickMsg(null);
                    }}
                  >
                    {t('app.cancel')}
                  </button>
                </div>
              </div>
            )}

            <div className="list" style={{ marginTop: 18 }}>
              <div className="li">
                <span className="small muted">{t('me.user_id')}</span>
                <span className="mono">{user?.id ?? '—'}</span>
              </div>
              <div className="li">
                <span className="small muted">{t('me.email')}</span>
                <span className="small">{user?.email || t('me.not_bound')}</span>
              </div>
              <div className="li">
                <span className="small muted">{t('me.registered_at')}</span>
                <span className="small">{dt(user?.created_at)}</span>
              </div>
              <div className="li">
                <span className="small muted">{t('me.kyc')}</span>
                {/* 不在这里显示状态：那要多拉一次 identityStatus，状态本身在认证页上更完整 */}
                <Link className="small" to="/kyc">
                  {t('app.view')}
                </Link>
              </div>
              <div className="li">
                <span className="small muted">{t('me.two_factor')}</span>
                <Link className="small" to="/security">
                  {t('me.manage')}
                </Link>
              </div>
            </div>

            <button
              type="button"
              className="btn btn--block"
              style={{ marginTop: 18 }}
              onClick={onLogout}
            >
              {t('app.logout_full')}
            </button>
          </div>
        </section>

        <section className="stack">
          <div className="between">
            <p className="label" style={{ flex: 1 }}>
              {t('me.notices')}
            </p>
            {unread.data && unread.data.count > 0 && (
              <span className="pill pill--orange">
                {t('me.unread_count', { count: unread.data.count })}
              </span>
            )}
          </div>

          <button
            type="button"
            className="btn btn--sm"
            disabled={busy || !unread.data?.count}
            onClick={() => markRead()}
          >
            {t('me.mark_all_read')}
          </button>

          {notices.loading && <Loading />}
          {!notices.loading && notices.error && (
            <ErrorBox message={notices.error} onRetry={notices.reload} />
          )}
          {!notices.loading && !notices.error && notices.data && notices.data.items.length === 0 && (
            <div className="state">
              <p className="state__k">{t('me.no_notices')}</p>
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
                        <span className="pill pill--plain">{n.type}</span> {dt(n.created_at)}
                      </p>
                    </div>
                    {!n.is_read && (
                      <button
                        type="button"
                        className="btn btn--sm"
                        disabled={busy}
                        onClick={() => markRead(n.id)}
                      >
                        {t('me.mark_read')}
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
                    {t('app.prev_page')}
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
                    {t('app.next_page')}
                  </button>
                </div>
              )}
            </>
          )}
        </section>
      </div>

      <ExportPanel />

      <DeletePanel />
    </>
  );
}
