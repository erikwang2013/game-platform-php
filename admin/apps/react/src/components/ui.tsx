/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useEffect, type ReactNode } from 'react';
import { t } from '../i18n/index.ts';

export type Tone = 'primary' | 'amber' | 'success' | 'danger' | 'muted';

export function PageHead({ title, sub, children }: { title: string; sub?: string; children?: ReactNode }) {
  return (
    <header className="pagehead">
      <div className="pagehead-t">
        <h1 className="h1">{title}</h1>
        {sub ? <p className="sub muted">{sub}</p> : null}
      </div>
      {children ? <div className="pagehead-a">{children}</div> : null}
    </header>
  );
}

export function Card({
  title,
  sub,
  actions,
  children,
}: {
  title?: string;
  sub?: string;
  actions?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="card">
      {title || actions ? (
        <div className="card-h">
          <div>
            {title ? <h2 className="h2">{title}</h2> : null}
            {sub ? <p className="sub muted">{sub}</p> : null}
          </div>
          {actions ? <div className="card-a">{actions}</div> : null}
        </div>
      ) : null}
      <div className="card-b">{children}</div>
    </section>
  );
}

/**
 * 统计块。`icon` / `color` 是给仪表盘那种「后端带图标与强调色」的卡片用的：
 * 图标是装饰（语义由 `label` 承载，故 `aria-hidden`），`color` 只落在图标上 ——
 * 后端给的十六进制当大面积底色会跟令牌体系打架（暗色下尤其）。
 */
export function Stat({
  label,
  value,
  hint,
  tone,
  icon,
  color,
}: {
  label: string;
  value: ReactNode;
  hint?: ReactNode;
  tone?: Tone;
  icon?: ReactNode;
  color?: string;
}) {
  return (
    <div className={`stat${tone ? ` t-${tone}` : ''}`}>
      {icon ? (
        <span className="stat-i" style={color ? { color } : undefined} aria-hidden="true">
          {icon}
        </span>
      ) : null}
      <span className="stat-l">{label}</span>
      <span className="stat-v">{value}</span>
      {hint ? <span className="stat-h">{hint}</span> : null}
    </div>
  );
}

// 缺省值在**每次调用**时求值（不是模块求值期），故这里现取译文是活的；调用方传的是成品文案
export function Loading({ rows = 3, label = t('app.loading') }: { rows?: number; label?: string }) {
  return (
    <div className="skel" role="status" aria-label={label}>
      {Array.from({ length: rows }, (_unused, index) => (
        <span key={index} className="skel-row" />
      ))}
    </div>
  );
}

export function Empty({ text = t('app.no_data') }: { text?: string }) {
  return (
    <div className="empty">
      {/* 吉祥物小骰（Dicey）：纯装饰，语义由下面的文案承载；随 BASE_URL 走子路径部署 */}
      <img className="empty-mark" src={`${import.meta.env.BASE_URL}mascot.svg`} alt="" aria-hidden="true" />
      <span>{text}</span>
    </div>
  );
}

/**
 * 操作结果提示条。tone 缺省 error（服务端拒绝的原话，红框）；
 * 成功类动作（打款/审核通过）传 `ok` —— 把「打款成功」摆进红框会让人以为出了事。
 */
export function ErrorNote({
  message,
  tone = 'error',
  onRetry,
}: {
  message: string;
  tone?: 'error' | 'ok';
  onRetry?: () => void;
}) {
  return (
    <div className={`errnote${tone === 'ok' ? ' ok' : ''}`} role="alert">
      <span className="errnote-t">{message}</span>
      {onRetry ? (
        <button type="button" className="btn btn-sm" onClick={onRetry}>
          {t('common.retry')}
        </button>
      ) : null}
    </div>
  );
}

export function Badge({ children, tone = 'muted' }: { children: ReactNode; tone?: Tone }) {
  return <span className={`badge b-${tone}`}>{children}</span>;
}

export function Tabs({
  tabs,
  value,
  onChange,
}: {
  tabs: { key: string; label: string }[];
  value: string;
  onChange: (key: string) => void;
}) {
  return (
    <div className="tabs" role="tablist">
      {tabs.map((tab) => (
        <button
          key={tab.key}
          type="button"
          role="tab"
          aria-selected={tab.key === value}
          className={`tab${tab.key === value ? ' on' : ''}`}
          onClick={() => onChange(tab.key)}
        >
          {tab.label}
        </button>
      ))}
    </div>
  );
}

/**
 * 分页条（形态照 angular 那棵的 ui-pager）：共 N 条 · 第 X/Y 页 + 上一页/下一页。
 * 只有「真翻得动」的列表才画（见 RowBrowser）：整表端点与树/裸数组没有 total，画上去是骗人。
 */
export function Pager({
  page,
  pages,
  total,
  onJump,
}: {
  page: number;
  pages: number;
  total: number;
  onJump: (page: number) => void;
}) {
  return (
    <div className="pager">
      <span>{t('table.pager', { total, page, pages })}</span>
      <button type="button" className="btn btn-sm" disabled={page <= 1} onClick={() => onJump(page - 1)}>
        {t('table.prev')}
      </button>
      <button type="button" className="btn btn-sm" disabled={page >= pages} onClick={() => onJump(page + 1)}>
        {t('table.next')}
      </button>
    </div>
  );
}

export function Field({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="field">
      <span className="field-l">{label}</span>
      <span className="field-v">{value}</span>
    </div>
  );
}

export function Modal({
  title,
  onClose,
  children,
  size,
}: {
  title: string;
  onClose: () => void;
  children: ReactNode;
  /**
   * `'sm'` = 贴合内容的小弹框。默认 `.modal` 是 `min(760px, 100%)`，表单/详情/导入报表需要它；
   * 登录验证码只有一张 **原生 300×200** 的画布，而 `.cap-img` 是 `width:100%` ⇒ 跟着容器走，
   * 760px 弹框会把它拉伸到 **2.4 倍**（既占满屏又糊）。给小尺寸后弹框 340px、内容 300px
   * ⇒ 画布 1:1。**别把默认值改小**，那会连累另外三个调用点。
   */
  size?: 'sm';
}) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="backdrop" onClick={onClose} role="presentation">
      <div
        className={size === 'sm' ? 'modal modal-sm' : 'modal'}
        role="dialog"
        aria-modal="true"
        aria-label={title}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="modal-h">
          <h2 className="h2">{title}</h2>
          <button type="button" className="btn btn-sm" onClick={onClose} aria-label={t('app.close')}>
            {t('app.close')}
          </button>
        </div>
        <div className="modal-b">{children}</div>
      </div>
    </div>
  );
}
