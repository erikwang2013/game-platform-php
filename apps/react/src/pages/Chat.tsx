/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ApiError, api } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';
import { Avatar } from '../components/Avatar.tsx';

const nameOf = (u: { nickname: string | null; username: string }) => u.nickname || u.username;

/* ---------------- 会话列表 ---------------- */

export function ChatList() {
  const convs = useAsync(() => api.conversations(), []);
  const list = convs.data?.list ?? [];

  return (
    <>
      <section className="stack">
        <p className="label">私信</p>
        <h1 className="h1">
          消息
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          只能和好友聊天。新消息到达后刷新本页即可看到。
        </p>
      </section>

      <section className="stack">
        {convs.loading && <Loading />}
        {!convs.loading && convs.error && <ErrorBox message={convs.error} onRetry={convs.reload} />}
        {!convs.loading && !convs.error && list.length === 0 && (
          <Empty title="还没有会话" hint="去「好友」里点「发消息」开始" />
        )}
        {!convs.loading && !convs.error && list.length > 0 && (
          <div className="list">
            {list.map((c) => (
              <Link className="li li--start" key={c.peer.id} to={`/chat/${c.peer.id}`}>
                <div className="row" style={{ gap: 10 }}>
                  <Avatar stored={c.peer.avatar} name={nameOf(c.peer)} />
                  <div>
                    <p className="li__t" style={{ margin: 0 }}>
                      {nameOf(c.peer)}
                      {c.unread_count > 0 && (
                        <span className="pill pill--orange" style={{ marginLeft: 8 }}>
                          {c.unread_count}
                        </span>
                      )}
                    </p>
                    <p className="small muted" style={{ margin: '2px 0 0' }}>
                      {c.last_message || '（空消息）'}
                    </p>
                  </div>
                </div>
                <span className="small muted">{dt(c.updated_at)}</span>
              </Link>
            ))}
          </div>
        )}
      </section>
    </>
  );
}

/* ---------------- 会话详情 ---------------- */

export function ChatRoom() {
  const { hashid = '' } = useParams();
  /**
   * ⚠ 这个 GET 有副作用：服务端返回前会把对方发来的未读全部置为已读
   * （ChatController.php:99-100）。所以「进入会话」本身就完成了已读，不用再调 markChatRead。
   */
  const msgs = useAsync(() => api.chatMessages(hashid), [hashid]);
  // 对端昵称从会话列表里取（没有「按 hashid 查用户」的端点，所以拿不到就只显示 id）
  const convs = useAsync(() => api.conversations(), []);

  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const send = async (e: FormEvent) => {
    e.preventDefault();
    if (busy || !text.trim()) return;
    setBusy(true);
    setMsg(null);
    try {
      await api.sendChat(hashid, text.trim());
      setText('');
      // 回复后重拉：id/时间由服务端生成，本地 push 一条就是第二真值源
      msgs.reload();
      convs.reload();
    } catch (e2) {
      setMsg(e2 instanceof ApiError ? e2.message : '发送失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  const peer = convs.data?.list.find((c) => c.peer.id === hashid)?.peer ?? null;
  const items = msgs.data?.items ?? [];

  return (
    <>
      <section className="stack">
        <p className="label">
          <Link to="/chat">消息</Link> / 对话
        </p>
        <h1 className="h1">{peer ? nameOf(peer) : '对话'}</h1>
        {/* 没有「按 hashid 查用户」的端点：不在会话列表里的人只能显示编号，别编昵称 */}
        {!peer && !convs.loading && <p className="small muted" style={{ margin: 0 }}>对方 #{hashid}</p>}
      </section>

      <section className="stack">
        {msgs.loading && <Loading />}
        {!msgs.loading && msgs.error && <ErrorBox message={msgs.error} onRetry={msgs.reload} />}
        {!msgs.loading && !msgs.error && items.length === 0 && (
          <Empty title="还没有聊天记录" hint="在下面输入第一条消息" />
        )}
        {!msgs.loading && !msgs.error && items.length > 0 && (
          <div className="stack">
            {items.map((m) => {
              // 服务端只回这两个人的消息（双向 where），所以「不是对端发的」即是我发的。
              // 不拿 Profile.id 比：那要多依赖一层「profile 的 id 与消息里的 from_user_id 同源」的假设
              const mine = m.from_user_id !== hashid;
              return (
                <div
                  key={m.id}
                  className="card card--flat"
                  style={{ marginLeft: mine ? 'auto' : 0, maxWidth: '86%' }}
                >
                  <p className="small muted" style={{ margin: 0 }}>
                    {mine ? '我' : nameOf(peer ?? { nickname: null, username: hashid })} · {dt(m.created_at)}
                  </p>
                  <p style={{ margin: '8px 0 0', whiteSpace: 'pre-wrap' }}>{m.content}</p>
                </div>
              );
            })}
          </div>
        )}

        <form className="card card--flat" onSubmit={send}>
          <label className="field">
            <span>发送消息</span>
            <textarea
              className="input"
              name="content"
              rows={3}
              maxLength={5000}
              placeholder="说点什么…"
              value={text}
              onChange={(e) => {
                setText(e.target.value);
                setMsg(null);
              }}
            />
          </label>
          {msg && (
            <p className="err" role="alert">
              {msg}
            </p>
          )}
          <button type="submit" className="btn btn--sm btn--primary" disabled={busy || !text.trim()}>
            {busy ? '发送中…' : '发送'}
          </button>
        </form>
      </section>
    </>
  );
}
