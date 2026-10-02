/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState, type MouseEvent, type ReactNode } from 'react';
import { ApiError, api } from '../lib/api.ts';
import {
  CANVAS_H,
  CANVAS_W,
  describeCaptcha,
  toImageCoords,
  type CaptchaData,
  type Click,
} from '../lib/captcha.ts';
import { Loading } from './States.tsx';
import { useI18n } from '../i18n/useI18n.ts';

/**
 * 通用弹窗外壳（遮罩点击 / Esc 关闭），全树唯一的弹框出口，验证码框复用它。
 * `sm` = 贴合内容的小弹框（验证码画布原生 300×200，宽弹框会把画布拉伸）。
 */
export function Modal({
  title,
  onClose,
  sm,
  children,
}: {
  title: string;
  onClose: () => void;
  sm?: boolean;
  children: ReactNode;
}) {
  const { t } = useI18n();

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
        className={`modal${sm ? ' modal--sm' : ''}`}
        role="dialog"
        aria-modal="true"
        aria-label={title}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="modal-h">
          <h2 className="h3">{title}</h2>
          <button type="button" className="btn btn--sm" onClick={onClose} aria-label={t('app.close')}>
            {t('app.close')}
          </button>
        </div>
        <div className="modal-b">{children}</div>
      </div>
    </div>
  );
}

/** 点击验证码弹框：挂载即现取新图，标满点数才允许确认。业务请求由 useCaptcha 的调用方负责。 */
export function CaptchaModal({
  onConfirm,
  onCancel,
}: {
  onConfirm: (proof: { captcha_key: string; clicks: Click[] }) => void;
  onCancel: () => void;
}) {
  const { t } = useI18n();
  const [data, setData] = useState<CaptchaData | null>(null);
  const [loading, setLoading] = useState(true);
  // 存**错误对象**而不是翻好的串：在 promise 回调里翻，等于把文案冻在取图那一刻的语言上
  const [err, setErr] = useState<unknown>(null);
  const [size, setSize] = useState({ w: CANVAS_W, h: CANVAS_H });
  const [dots, setDots] = useState<Click[]>([]);
  const [tick, setTick] = useState(0);

  const { imgSrc, required, hint } = describeCaptcha(data);

  // 开框即现取新图：验证码一次性，复用上一次的必然验不过。
  // 落到 state 全在 promise 回调里（不在 effect 里同步 setState），tick 变化即重取。
  useEffect(() => {
    let alive = true;
    api
      .captcha()
      .then((next) => {
        if (alive) setData(next);
      })
      .catch((cause: unknown) => {
        if (!alive) return;
        setData(null);
        setErr(cause);
      })
      .finally(() => {
        if (alive) setLoading(false);
      });
    return () => {
      alive = false;
    };
  }, [tick]);

  // 换一张：先在事件里复位（标点/错误/尺寸），再让上面的 effect 重取
  const reload = () => {
    setLoading(true);
    setErr(null);
    setData(null);
    setDots([]);
    setSize({ w: CANVAS_W, h: CANVAS_H });
    setTick((n) => n + 1);
  };

  const onPick = (event: MouseEvent<HTMLDivElement>) => {
    const pick = toImageCoords(
      event.clientX,
      event.clientY,
      event.currentTarget.getBoundingClientRect(),
      size,
    );
    // 目标数固定且逐点顺序校验，多点无效，标满即止
    if (pick) setDots((prev) => (prev.length >= required ? prev : [...prev, pick]));
  };

  return (
    <Modal title={t('captcha.title')} onClose={onCancel} sm>
      <p className="small muted" style={{ margin: 0 }}>
        {hint}
      </p>

      <div className="cap" onClick={onPick} role="presentation">
        {imgSrc ? (
          <img
            className="cap-img"
            src={imgSrc}
            alt={t('captcha.tap')}
            draggable={false}
            onLoad={(event) => {
              const el = event.currentTarget;
              // 以图片真实像素为准（画布恒为 300×200，取不到时用其兜底），点击坐标随之等比换算
              if (el.naturalWidth > 0) setSize({ w: el.naturalWidth, h: el.naturalHeight });
            }}
          />
        ) : (
          <div className="cap-ph">{t('captcha.image_unavailable')}</div>
        )}
        {dots.map((dot, index) => (
          <span
            key={`${dot.x}-${dot.y}-${index}`}
            className="cap-dot"
            style={{ left: `${(dot.x / size.w) * 100}%`, top: `${(dot.y / size.h) * 100}%` }}
          >
            {index + 1}
          </span>
        ))}
      </div>

      <div className="cap-bar">
        <span>{t('captcha.marked', { marked: dots.length, required })}</span>
        <span className="row">
          <button
            type="button"
            className="btn btn--sm"
            disabled={!dots.length}
            onClick={() => setDots((prev) => prev.slice(0, -1))}
          >
            {t('captcha.undo')}
          </button>
          <button type="button" className="btn btn--sm" onClick={reload}>
            {t('captcha.refresh')}
          </button>
        </span>
      </div>

      {loading && <Loading label={t('captcha.loading')} />}
      {err ? (
        <p className="small" style={{ color: 'var(--neg)', margin: 0 }}>
          {err instanceof ApiError ? err.message : t('captcha.load_failed')}
        </p>
      ) : null}

      <div className="row" style={{ marginTop: 14 }}>
        <button type="button" className="btn btn--sm" onClick={onCancel}>
          {t('app.cancel')}
        </button>
        <button
          type="button"
          className="btn btn--primary"
          style={{ flex: 1 }}
          disabled={loading || dots.length !== required}
          onClick={() => onConfirm({ captcha_key: data?.key ?? '', clicks: dots })}
        >
          {t('app.confirm')}
        </button>
      </div>
    </Modal>
  );
}
