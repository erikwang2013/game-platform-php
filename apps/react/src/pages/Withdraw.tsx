/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api, type WithdrawApplied } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { useCaptcha } from '../lib/useCaptcha.tsx';
import type { MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

/**
 * 与后端 WithdrawController 的 method 白名单一致。
 *
 * ⚠ 表里存的是**键**不是文案（与 `Exchange.tsx` 的 `DIRECTIONS`、`Layout.tsx` 的 `NAV` 同款）：
 * 模块顶层只求值一次，写成 `t(...)` 会把这三种方式名冻在首屏语言上，切语言后下拉框一个字都不变。
 */
const METHODS: Array<{ id: string; label: MessageKey }> = [
  { id: 'paypal', label: 'withdraw.method_paypal' },
  { id: 'bank', label: 'withdraw.method_bank' },
  { id: 'crypto', label: 'withdraw.method_crypto' },
];

/** 错误文案的**暂存形**：存「原始错误 + 兜底键」，**不存翻好的串**（理由见 `Exchange.tsx` 的 `Msg`）。 */
type Msg = { err: unknown; fallback: MessageKey };

export function Withdraw() {
  const { t } = useI18n();
  const wallet = useAsync(() => api.wallet(), []);
  const [method, setMethod] = useState('paypal');
  const [amount, setAmount] = useState('');
  const [accountInfo, setAccountInfo] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<Msg | null>(null);
  const [done, setDone] = useState<WithdrawApplied | null>(null);
  const cap = useCaptcha();

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    setErr(null);
    // 先过本地必填校验，再弹验证码框
    if (!amount.trim() || !accountInfo.trim()) return;
    const proof = await cap.ask();
    if (!proof) return; // 用户取消
    setBusy(true);
    try {
      const r = await api.applyWithdraw({
        platform_amount: amount.trim(),
        method,
        account_info: accountInfo.trim(),
        ...proof,
      });
      setDone(r);
      setAmount('');
      wallet.reload();
    } catch (e2) {
      setErr({ err: e2, fallback: 'error.submit_failed' });
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="stack">
        <p className="label">{t('tx.withdraw')}</p>
        <h1 className="h1">
          {t('tx.withdraw')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          {t('wallet.available')}{' '}
          <span className="mono">{wallet.loading ? '…' : (wallet.data?.balance ?? '—')}</span>
          {' '}
          <Link to="/wallet">{t('app.back_wallet')}</Link>
        </p>
      </section>

      <section className="card" aria-label={t('withdraw.form_aria')}>
        <form onSubmit={submit} className="stack">
          {err && (
            <p className="err" role="alert">
              {err.err instanceof ApiError
                ? t('error.with_code', { message: err.err.message, code: err.err.code })
                : t(err.fallback)}
            </p>
          )}

          <label className="field">
            <span>{t('withdraw.method')}</span>
            <select
              className="input"
              name="method"
              value={method}
              onChange={(e) => setMethod(e.target.value)}
            >
              {METHODS.map((m) => (
                <option key={m.id} value={m.id}>
                  {t(m.label)}
                </option>
              ))}
            </select>
          </label>

          <label className="field">
            <span>{t('withdraw.amount')}</span>
            <input
              className="input"
              name="platform_amount"
              type="text"
              inputMode="decimal"
              autoComplete="off"
              required
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
            />
          </label>

          <label className="field">
            <span>{t('withdraw.account')}</span>
            <textarea
              className="input"
              name="account_info"
              rows={3}
              required
              value={accountInfo}
              onChange={(e) => setAccountInfo(e.target.value)}
            />
          </label>

          <p className="small muted" style={{ margin: 0 }}>
            {t('withdraw.hint')}
          </p>

          <button type="submit" className="btn btn--primary btn--block" disabled={busy}>
            {busy && <span className="spin" aria-hidden="true" />}
            {t('withdraw.submit')}
          </button>
        </form>
      </section>

      {done && (
        <section className="card" aria-label={t('withdraw.done_aria')} role="status">
          <p className="label">{t('withdraw.done')}</p>
          <div className="list" style={{ marginTop: 12 }}>
            <div className="li">
              <span className="small muted">{t('app.order_no')}</span>
              <span className="mono">{done.order_no}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('app.status')}</span>
              <span className="pill pill--plain">{done.status}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('withdraw.amount_applied')}</span>
              <span className="mono">{done.platform_amount}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('withdraw.fee')}</span>
              <span className="mono">{done.fee}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('withdraw.actual')}</span>
              <span className="mono">{done.actual_amount}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('withdraw.balance_after')}</span>
              <span className="mono">{done.balance_after}</span>
            </div>
          </div>
        </section>
      )}

      {cap.modal}
    </>
  );
}
