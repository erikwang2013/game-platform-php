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
import { t, type MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

/**
 * 工单类型。服务端校验器是 `in:deposit,withdraw,game,account,other`（TicketController:112），
 * 改这里必须同步改那边，否则提交会被 422 挡下。
 */
const TYPES: Array<{ v: TicketType; label: MessageKey }> = [
  { v: 'deposit', label: 'ticket.type_deposit' },
  { v: 'withdraw', label: 'ticket.type_withdraw' },
  { v: 'game', label: 'ticket.type_game' },
  { v: 'account', label: 'ticket.type_account' },
  { v: 'other', label: 'ticket.type_other' },
];

/**
 * 工单状态。三个值都取自代码实际写入点：
 * open=创建时（TicketController:127）、waiting=用户回复后（:166）、closed=回复守卫里判的终态（:146）。
 * 列注释（install/install.sql:1844）列的是四值：open=新建待受理 / waiting=用户已回复 /
 * replied=管理员已回复 / closed=已关闭 —— 比上面多一个 replied（管理端回复后置上），不冲突。
 * ⚠ 列是 VARCHAR(20)、没有 enum ⇒ 未知值原样透出，别猜。
 */
const STATUS: Record<string, { label: MessageKey; cls: string }> = {
  open: { label: 'ticket.status_open', cls: 'pill--yellow' },
  waiting: { label: 'ticket.status_waiting', cls: 'pill--orange' },
  closed: { label: 'ticket.status_closed', cls: 'pill--plain' },
};

/**
 * 两张表里查不到的值**原样透出**（服务端将来加类型/状态不吞字），查得到的**在渲染期才翻**。
 *
 * ⚠ 存**键**不存文案：模块顶层求值只发生一次，存文案会把它冻在首屏语言上
 * （与 `Wallet.tsx` 的 `TABS`、`Kyc.tsx` 的 `TYPE_LABEL` 同款约定）。
 */
const typeLabel = (v: string) => {
  const k = TYPES.find((x) => x.v === v)?.label;
  return k ? t(k) : v;
};

/** 同上；`status` 列是 VARCHAR(20) 无 enum，未知值原样透出。 */
const statusLabel = (v: string) => {
  const k = STATUS[v]?.label;
  return k ? t(k) : v;
};

/**
 * 提示文案的**暂存形**：失败存「原始错误 + 兜底键」，**不存翻好的串** ——
 * `catch` 在 `await` 之后才落值，存串就把语言冻在提交那一刻（同 `Friends.tsx` 的 `Msg`）。
 */
type Msg = { err: unknown; fallback: MessageKey };

/** 渲染期才翻（本文件用模块级 `t`，组件里那次 `useI18n()` 负责订阅重绘）。 */
const msgText = (m: Msg): string =>
  m.err instanceof ApiError ? m.err.message : t(m.fallback);

/* ---------------- 列表 ---------------- */

export function Tickets() {
  // 只为订阅语言变更引起的重渲染；文案求值走模块级的 t()（同 `Me.tsx`）
  useI18n();
  // 服务端默认 page=1/per_page=20：不带 page 时第 21 张之后的工单**永久不可达**，
  // 且旧版连总数都没有 ⇒ 用户察觉不到自己被截断。分页块与 Wallet.tsx 同形。
  const [page, setPage] = useState(1);
  const list = useAsync(() => api.tickets({ page }), [page]);
  const items = list.data?.items ?? [];
  // 回包是 PagedLite：`page` / `last_page` 都是**可选**（types.ts:300），缺值时按「只有一页」处理
  const lastPage = Math.max(1, list.data?.last_page ?? 1);

  return (
    <>
      <section className="stack">
        <p className="label">{t('ticket.cs')}</p>
        <h1 className="h1">
          {t('ticket.mine')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          {t('ticket.sub')}
        </p>
        <div>
          <Link className="btn btn--sm btn--primary" to="/tickets/new">
            {t('ticket.new_title')}
          </Link>
        </div>
      </section>

      <section className="stack">
        {list.loading && <Loading />}
        {!list.loading && list.error && <ErrorBox message={list.error} onRetry={list.reload} />}
        {!list.loading && !list.error && items.length === 0 && (
          <Empty title={t('ticket.empty_title')} hint={t('ticket.empty_hint')} />
        )}
        {!list.loading && !list.error && items.length > 0 && (
          <>
            <p className="small muted" style={{ margin: 0 }}>
              {t('ticket.count', { count: list.data?.total ?? items.length })}
            </p>
            <div className="list">
              {items.map((row: TicketRow) => (
                <Link className="li li--start" key={row.id} to={`/tickets/${row.id}`}>
                  <div>
                    <p className="li__t" style={{ margin: 0 }}>
                      {row.subject}
                      <span
                        className={`pill ${STATUS[row.status]?.cls ?? 'pill--plain'}`}
                        style={{ marginLeft: 8 }}
                      >
                        {statusLabel(row.status)}
                      </span>
                    </p>
                    <p className="small muted" style={{ margin: '4px 0 0' }}>
                      {t('ticket.list_meta', {
                        type: typeLabel(row.type),
                        count: row.reply_count,
                        time: dt(row.created_at),
                      })}
                    </p>
                  </div>
                </Link>
              ))}
            </div>

            {lastPage > 1 && (
              <div className="between">
                <button
                  type="button"
                  className="btn btn--sm"
                  disabled={page <= 1 || list.loading}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  {t('app.prev_page')}
                </button>
                <span className="small muted">
                  {list.data?.page ?? page} / {lastPage}
                </span>
                <button
                  type="button"
                  className="btn btn--sm"
                  disabled={page >= lastPage || list.loading}
                  onClick={() => setPage((p) => p + 1)}
                >
                  {t('app.next_page')}
                </button>
              </div>
            )}
          </>
        )}
      </section>
    </>
  );
}

/* ---------------- 新建 ---------------- */

export function TicketNew() {
  // 只为订阅语言变更引起的重渲染；文案求值走模块级的 t()（同 `Me.tsx`）
  useI18n();
  const navigate = useNavigate();
  const [type, setType] = useState<TicketType>('other');
  const [subject, setSubject] = useState('');
  const [content, setContent] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<Msg | null>(null);

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
      setMsg({ err, fallback: 'error.submit_failed' });
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="stack">
        <p className="label">{t('ticket.cs')}</p>
        <h1 className="h1">
          {t('ticket.new_title')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
      </section>

      <section className="stack">
        <form className="card card--flat" onSubmit={submit}>
          <label className="field">
            <span>{t('ticket.field_type')}</span>
            <select
              className="input"
              name="type"
              value={type}
              onChange={(e) => setType(e.target.value as TicketType)}
            >
              {TYPES.map((opt) => (
                <option key={opt.v} value={opt.v}>
                  {t(opt.label)}
                </option>
              ))}
            </select>
          </label>

          <label className="field">
            <span>{t('ticket.field_subject')}</span>
            <input
              className="input"
              name="subject"
              type="text"
              maxLength={200}
              placeholder={t('ticket.subject_ph')}
              value={subject}
              onChange={(e) => {
                setSubject(e.target.value);
                setMsg(null);
              }}
            />
          </label>

          <label className="field">
            <span>{t('ticket.field_content')}</span>
            <textarea
              className="input"
              name="content"
              rows={6}
              maxLength={5000}
              placeholder={t('ticket.content_ph')}
              value={content}
              onChange={(e) => {
                setContent(e.target.value);
                setMsg(null);
              }}
            />
          </label>

          {msg && (
            <p className="err" role="alert">
              {msgText(msg)}
            </p>
          )}

          <div className="row" style={{ gap: 10 }}>
            <button
              type="submit"
              className="btn btn--primary"
              disabled={busy || !subject.trim() || !content.trim()}
            >
              {busy ? t('app.submitting') : t('app.submit')}
            </button>
            <Link className="btn btn--sm" to="/tickets">
              {t('app.back')}
            </Link>
          </div>
        </form>
      </section>
    </>
  );
}

/* ---------------- 详情 ---------------- */

export function TicketDetail() {
  // 只为订阅语言变更引起的重渲染；文案求值走模块级的 t()（同 `Me.tsx`）
  useI18n();
  const { hashid = '' } = useParams();
  // 变量名不叫 `t`：那是 i18n 的查表函数，遮住它就查不了表
  const detail = useAsync(() => api.ticket(hashid), [hashid]);
  const [reply, setReply] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<Msg | null>(null);

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
      detail.reload();
    } catch (err) {
      setMsg({ err, fallback: 'error.send_failed' });
    } finally {
      setBusy(false);
    }
  };

  const d: Detail | null = detail.data;

  return (
    <>
      <section className="stack">
        <p className="label">
          <Link to="/tickets">{t('ticket.mine')}</Link> {t('ticket.crumb_detail')}
        </p>
        <h1 className="h1">{d ? d.subject : t('nav.tickets')}</h1>
        {d && (
          <div className="row">
            <span className="pill pill--plain">{typeLabel(d.type)}</span>
            <span className={`pill ${STATUS[d.status]?.cls ?? 'pill--plain'}`}>
              {statusLabel(d.status)}
            </span>
          </div>
        )}
      </section>

      <section className="stack">
        {detail.loading && <Loading />}
        {!detail.loading && detail.error && <ErrorBox message={detail.error} onRetry={detail.reload} />}

        {!detail.loading && !detail.error && d && (
          <>
            <div className="card card--flat">
              <p className="small muted" style={{ margin: 0 }}>
                {t('ticket.submitted_at', { time: dt(d.created_at) })}
              </p>
              <p style={{ margin: '10px 0 0', whiteSpace: 'pre-wrap' }}>{d.content}</p>
            </div>

            {d.replies.length === 0 ? (
              <Empty title={t('ticket.no_reply_title')} hint={t('ticket.no_reply_hint')} />
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
                      {t('ticket.reply_meta', {
                        who: r.is_admin ? t('ticket.cs') : t('ticket.author_me'),
                        time: dt(r.created_at),
                      })}
                    </p>
                    <p style={{ margin: '8px 0 0', whiteSpace: 'pre-wrap' }}>{r.content}</p>
                  </div>
                ))}
              </div>
            )}

            {d.status === 'closed' ? (
              <p className="small muted" style={{ margin: 0 }}>
                {t('ticket.closed_note')}
              </p>
            ) : (
              <form className="card card--flat" onSubmit={send}>
                <label className="field">
                  <span>{t('ticket.field_reply')}</span>
                  <textarea
                    className="input"
                    name="content"
                    rows={3}
                    maxLength={5000}
                    placeholder={t('ticket.reply_ph')}
                    value={reply}
                    onChange={(e) => {
                      setReply(e.target.value);
                      setMsg(null);
                    }}
                  />
                </label>
                {msg && (
                  <p className="err" role="alert">
                    {msgText(msg)}
                  </p>
                )}
                <button type="submit" className="btn btn--sm btn--primary" disabled={busy || !reply.trim()}>
                  {busy ? t('app.sending') : t('app.send')}
                </button>
              </form>
            )}
          </>
        )}
      </section>
    </>
  );
}
