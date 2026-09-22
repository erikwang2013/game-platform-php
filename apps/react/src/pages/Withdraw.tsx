/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api, type WithdrawApplied } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';

/** 与后端 WithdrawController 的 method 白名单一致 */
const METHODS = [
  { id: 'paypal', label: 'PayPal' },
  { id: 'bank', label: '银行卡' },
  { id: 'crypto', label: '加密货币' },
];

export function Withdraw() {
  const wallet = useAsync(() => api.wallet(), []);
  const [method, setMethod] = useState('paypal');
  const [amount, setAmount] = useState('');
  const [accountInfo, setAccountInfo] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [done, setDone] = useState<WithdrawApplied | null>(null);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setErr(null);
    setBusy(true);
    try {
      const r = await api.applyWithdraw({
        platform_amount: amount.trim(),
        method,
        account_info: accountInfo.trim(),
      });
      setDone(r);
      setAmount('');
      wallet.reload();
    } catch (e2) {
      setErr(e2 instanceof ApiError ? `${e2.message}（${e2.code}）` : '提交失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="stack">
        <p className="label">提现</p>
        <h1 className="h1">
          提现
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          可用余额{' '}
          <span className="mono">{wallet.loading ? '…' : (wallet.data?.balance ?? '—')}</span>
          {' '}
          <Link to="/wallet">返回钱包</Link>
        </p>
      </section>

      <section className="card" aria-label="提现申请">
        <form onSubmit={submit} className="stack">
          {err && (
            <p className="err" role="alert">
              {err}
            </p>
          )}

          <label className="field">
            <span>提现方式</span>
            <select
              className="input"
              name="method"
              value={method}
              onChange={(e) => setMethod(e.target.value)}
            >
              {METHODS.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.label}
                </option>
              ))}
            </select>
          </label>

          <label className="field">
            <span>提现金额（平台币）</span>
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
            <span>收款账户信息</span>
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
            实际到账 = 提现金额 − 手续费，费率按账号等级与 VIP 计算，以提交结果为准。
          </p>

          <button type="submit" className="btn btn--primary btn--block" disabled={busy}>
            {busy && <span className="spin" aria-hidden="true" />}
            提交申请
          </button>
        </form>
      </section>

      {done && (
        <section className="card" aria-label="提现结果" role="status">
          <p className="label">申请已提交</p>
          <div className="list" style={{ marginTop: 12 }}>
            <div className="li">
              <span className="small muted">订单号</span>
              <span className="mono">{done.order_no}</span>
            </div>
            <div className="li">
              <span className="small muted">状态</span>
              <span className="pill pill--plain">{done.status}</span>
            </div>
            <div className="li">
              <span className="small muted">申请金额</span>
              <span className="mono">{done.platform_amount}</span>
            </div>
            <div className="li">
              <span className="small muted">手续费</span>
              <span className="mono">{done.fee}</span>
            </div>
            <div className="li">
              <span className="small muted">实际到账</span>
              <span className="mono">{done.actual_amount}</span>
            </div>
            <div className="li">
              <span className="small muted">提交后余额</span>
              <span className="mono">{done.balance_after}</span>
            </div>
          </div>
        </section>
      )}
    </>
  );
}
