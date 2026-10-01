/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  ApiError,
  api,
  type TicketDetail as Detail,
  type TicketRow,
  type TicketType,
} from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { Empty, ErrorBox, Loading } from '../components/States.tsx';

/**
 * 工单类型。服务端校验器是 `in:deposit,withdraw,game,account,other`（TicketController:111），
 * 改这里必须同步改那边，否则提交会被 422 挡下。
 */
const TYPES: Array<{ v: TicketType; label: string }> = [
  { v: 'deposit', label: '充值问题' },
  { v: 'withdraw', label: '提现问题' },
  { v: 'game', label: '游戏问题' },
  { v: 'account', label: '账号问题' },
  { v: 'other', label: '其他' },
];

/**
 * 工单状态。三个值都取自代码实际写入点：
 * open=创建时（TicketController:126）、waiting=用户回复后（:165）、closed=回复守卫里判的终态（:145）。
 * ⚠ install/install.sql:1780 的列注释只写了「open/closed」，**漏了 waiting** —— 是注释漂移不是约束
 * （列是 VARCHAR(20)，没有 enum）。未知值原样透出，别猜。
 */
const STATUS: Record<string, { label: string; cls: string }> = {
  open: { label: '待受理', cls: 'pill--yellow' },
  waiting: { label: '待回复', cls: 'pill--orange' },
  closed: { label: '已关闭', cls: 'pill--plain' },
};

const typeLabel = (t: string) => TYPES.find((x) => x.v === t)?.label ?? t;

/* ---------------- 列表 ---------------- */

export function Tickets() {
  const list = useAsync(() => api.tickets(), []);
  const items = list.data?.items ?? [];

  return (
    <>
      <section className="stack">
        <p className="label">客服</p>
        <h1 className="h1">
          我的工单
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          遇到充值、提现或账号问题，在这里提交，客服回复会出现在工单里。
        </p>
        <div>
          <Link className="btn btn--sm btn--primary" to="/tickets/new">
            提交工单
          </Link>
        </div>
      </section>

      <section className="stack">
        {list.loading && <Loading />}
        {!list.loading && list.error && <ErrorBox message={list.error} onRetry={list.reload} />}
        {!list.loading && !list.error && items.length === 0 && (
          <Empty title="还没有工单" hint="有问题可以点上面的「提交工单」" />
        )}
        {!list.loading && !list.error && items.length > 0 && (
          <div className="list">
            {items.map((t: TicketRow) => {
              const st = STATUS[t.status] ?? { label: t.status, cls: 'pill--plain' };
              return (
                <Link className="li li--start" key={t.id} to={`/tickets/${t.id}`}>
                  <div>
                    <p className="li__t" style={{ margin: 0 }}>
                      {t.subject}
                      <span className={`pill ${st.cls}`} style={{ marginLeft: 8 }}>
                        {st.label}
                      </span>
                    </p>
                    <p className="small muted" style={{ margin: '4px 0 0' }}>
                      {typeLabel(t.type)} · {t.reply_count} 条回复 · {dt(t.created_at)}
                    </p>
                  </div>
                </Link>
              );
            })}
          </div>
        )}
      </section>
    </>
  );
}

/* ---------------- 新建 ---------------- */

export function TicketNew() {
  const navigate = useNavigate();
  const [type, setType] = useState<TicketType>('other');
  const [subject, setSubject] = useState('');
  const [content, setContent] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    setBusy(true);
    setMsg(null);
    try {
      const d = await api.createTicket({ type, subject: subject.trim(), content: content.trim() });
      // 建完直接进详情：工单正文只在 detail 回，留在列表页看不到自己刚写的内容
      navigate(`/tickets/${d.id}`, { replace: true });
    } catch (err) {
      setMsg(err instanceof ApiError ? err.message : '提交失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="stack">
        <p className="label">客服</p>
        <h1 className="h1">
          提交工单
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
      </section>

      <section className="stack">
        <form className="card card--flat" onSubmit={submit}>
          <label className="field">
            <span>问题类型</span>
            <select
              className="input"
              name="type"
              value={type}
              onChange={(e) => setType(e.target.value as TicketType)}
            >
              {TYPES.map((t) => (
                <option key={t.v} value={t.v}>
                  {t.label}
                </option>
              ))}
            </select>
          </label>

          <label className="field">
            <span>标题</span>
            <input
              className="input"
              name="subject"
              type="text"
              maxLength={200}
              placeholder="一句话说明问题"
              value={subject}
              onChange={(e) => {
                setSubject(e.target.value);
                setMsg(null);
              }}
            />
          </label>

          <label className="field">
            <span>详细描述</span>
            <textarea
              className="input"
              name="content"
              rows={6}
              maxLength={5000}
              placeholder="订单号、时间、现象等，越具体处理越快"
              value={content}
              onChange={(e) => {
                setContent(e.target.value);
                setMsg(null);
              }}
            />
          </label>

          {msg && (
            <p className="err" role="alert">
              {msg}
            </p>
          )}

          <div className="row" style={{ gap: 10 }}>
            <button
              type="submit"
              className="btn btn--primary"
              disabled={busy || !subject.trim() || !content.trim()}
            >
              {busy ? '提交中…' : '提交'}
            </button>
            <Link className="btn btn--sm" to="/tickets">
              返回
            </Link>
          </div>
        </form>
      </section>
    </>
  );
}

/* ---------------- 详情 ---------------- */

export function TicketDetail() {
  const { hashid = '' } = useParams();
  const t = useAsync(() => api.ticket(hashid), [hashid]);
  const [reply, setReply] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const send = async (e: FormEvent) => {
    e.preventDefault();
    if (busy || !reply.trim()) return;
    setBusy(true);
    setMsg(null);
    try {
      await api.replyTicket(hashid, reply.trim());
      setReply('');
      // 重新拉详情而不是往本地数组 push：回复是服务端生成的（id/时间/状态都变了），
      // 本地拼一条就是第二真值源，客服回复也会对不上
      t.reload();
    } catch (err) {
      setMsg(err instanceof ApiError ? err.message : '发送失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  const d: Detail | null = t.data;

  return (
    <>
      <section className="stack">
        <p className="label">
          <Link to="/tickets">我的工单</Link> / 详情
        </p>
        <h1 className="h1">{d ? d.subject : '工单'}</h1>
        {d && (
          <div className="row">
            <span className="pill pill--plain">{typeLabel(d.type)}</span>
            <span className={`pill ${(STATUS[d.status] ?? { cls: 'pill--plain' }).cls}`}>
              {(STATUS[d.status] ?? { label: d.status }).label}
            </span>
          </div>
        )}
      </section>

      <section className="stack">
        {t.loading && <Loading />}
        {!t.loading && t.error && <ErrorBox message={t.error} onRetry={t.reload} />}

        {!t.loading && !t.error && d && (
          <>
            <div className="card card--flat">
              <p className="small muted" style={{ margin: 0 }}>
                提交于 {dt(d.created_at)}
              </p>
              <p style={{ margin: '10px 0 0', whiteSpace: 'pre-wrap' }}>{d.content}</p>
            </div>

            {d.replies.length === 0 ? (
              <Empty title="客服还没有回复" hint="回复后会显示在这里" />
            ) : (
              <div className="stack">
                {d.replies.map((r) => (
                  <div
                    key={r.id}
                    className="card card--flat"
                    // 客服回复靠左、自己靠右，纯用样式区分，不加「我」这种文案噪声
                    style={{ marginLeft: r.is_admin ? 0 : 'auto', maxWidth: '86%' }}
                  >
                    <p className="small muted" style={{ margin: 0 }}>
                      {r.is_admin ? '客服' : '我'} · {dt(r.created_at)}
                    </p>
                    <p style={{ margin: '8px 0 0', whiteSpace: 'pre-wrap' }}>{r.content}</p>
                  </div>
                ))}
              </div>
            )}

            {d.status === 'closed' ? (
              <p className="small muted" style={{ margin: 0 }}>
                该工单已关闭，不能再回复。如需继续咨询请另开工单。
              </p>
            ) : (
              <form className="card card--flat" onSubmit={send}>
                <label className="field">
                  <span>追加回复</span>
                  <textarea
                    className="input"
                    name="content"
                    rows={3}
                    maxLength={5000}
                    placeholder="补充说明…"
                    value={reply}
                    onChange={(e) => {
                      setReply(e.target.value);
                      setMsg(null);
                    }}
                  />
                </label>
                {msg && (
                  <p className="err" role="alert">
                    {msg}
                  </p>
                )}
                <button type="submit" className="btn btn--sm btn--primary" disabled={busy || !reply.trim()}>
                  {busy ? '发送中…' : '发送'}
                </button>
              </form>
            )}
          </>
        )}
      </section>
    </>
  );
}
