/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useEffect, type ReactNode } from 'react';

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

export function Stat({ label, value, hint, tone }: { label: string; value: ReactNode; hint?: string; tone?: Tone }) {
  return (
    <div className={`stat${tone ? ` t-${tone}` : ''}`}>
      <span className="stat-l">{label}</span>
      <span className="stat-v">{value}</span>
      {hint ? <span className="stat-h">{hint}</span> : null}
    </div>
  );
}

export function Loading({ rows = 3, label = '加载中' }: { rows?: number; label?: string }) {
  return (
    <div className="skel" role="status" aria-label={label}>
      {Array.from({ length: rows }, (_unused, index) => (
        <span key={index} className="skel-row" />
      ))}
    </div>
  );
}

export function Empty({ text = '暂无数据' }: { text?: string }) {
  return (
    <div className="empty">
      <span className="empty-mark" aria-hidden="true">
        ◌
      </span>
      <span>{text}</span>
    </div>
  );
}

export function ErrorNote({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="errnote" role="alert">
      <span className="errnote-t">{message}</span>
      {onRetry ? (
        <button type="button" className="btn btn-sm" onClick={onRetry}>
          重试
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

export function Field({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="field">
      <span className="field-l">{label}</span>
      <span className="field-v">{value}</span>
    </div>
  );
}

export function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
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
        className="modal"
        role="dialog"
        aria-modal="true"
        aria-label={title}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="modal-h">
          <h2 className="h2">{title}</h2>
          <button type="button" className="btn btn-sm" onClick={onClose} aria-label="关闭">
            关闭
          </button>
        </div>
        <div className="modal-b">{children}</div>
      </div>
    </div>
  );
}
