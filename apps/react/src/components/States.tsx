/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

export function Loading({ label = '加载中' }: { label?: string }) {
  return (
    <div className="state" role="status" aria-live="polite">
      <div className="row" style={{ justifyContent: 'center' }}>
        <span className="spin" aria-hidden="true" />
        <span className="state__k" style={{ margin: 0 }}>
          {label}
        </span>
      </div>
      <div className="bar" style={{ marginTop: 16 }} />
    </div>
  );
}

export function ErrorBox({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="state" role="alert">
      <p className="state__k">出错了</p>
      <p className="muted" style={{ marginTop: 0 }}>
        {message}
      </p>
      {onRetry && (
        <button type="button" className="btn btn--sm" onClick={onRetry}>
          重试
        </button>
      )}
    </div>
  );
}

export function Empty({ title = '暂无数据', hint }: { title?: string; hint?: string }) {
  return (
    <div className="state">
      <p className="state__k">{title}</p>
      {hint && <p className="muted small" style={{ margin: 0 }}>{hint}</p>}
    </div>
  );
}
