/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api, type DepositCreated } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';

/** 与后端 DepositController 的 currency 白名单一致 */
const CURRENCIES = ['USD', 'CNY', 'EUR', 'JPY', 'KRW', 'GBP', 'BRL', 'INR'];

/** 只允许跳到真正的 http(s) 链接，其余一律当文本展示（防 javascript: 之类注入） */
const isSafeUrl = (u: string) => /^https?:\/\//i.test(u);

/** 数值为 0 的金额字符串（服务端 DECIMAL(18,4) 下发 "0.0000"，判零不能用 === '0'） */
const isZeroAmount = (v: string) => /^0+(\.0+)?$/.test(v);

/**
 * 展示用格式化，语义同 Angular 树的 money()：仅把后端 DECIMAL（"5000.0000"）渲染成 "5,000.00"。
 * 只在 JSX 输出边界使用；提交路径上的金额字符串仍原样透传，不做任何数值转换。
 */
const money = (v: string): string => {
  const n = Number(v ?? 0);
  return Number.isFinite(n)
    ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : v;
};

export function Deposit() {
  const methods = useAsync(() => api.paymentMethods(), []);
  const [picked, setPicked] = useState('');
  const [currency, setCurrency] = useState('USD');
  const [amount, setAmount] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [created, setCreated] = useState<{ order: DepositCreated; currency: string } | null>(null);

  const list = methods.data?.list ?? [];
  // 未手动选择时默认第一个可用方式，省一个 useEffect
  const methodId = picked || list[0]?.id || '';
  const method = list.find((m) => m.id === methodId) ?? null;
  const unlimited = !!method && isZeroAmount(method.max_amount);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setErr(null);
    setBusy(true);
    try {
      const order = await api.createDeposit({
        amount: amount.trim(),
        currency,
        payment_method_id: methodId,
      });
      setCreated({ order, currency });
      if (isSafeUrl(order.checkout_url)) {
        window.open(order.checkout_url, '_blank', 'noopener,noreferrer');
      }
    } catch (e2) {
      setErr(e2 instanceof ApiError ? `${e2.message}（${e2.code}）` : '提交失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="stack">
        <p className="label">充值</p>
        <h1 className="h1">
          充值
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          下单后在新窗口完成支付，到账平台币以订单为准。<Link to="/wallet">返回钱包</Link>
        </p>
      </section>

      <section className="card" aria-label="充值下单">
        {methods.loading && <Loading />}
        {!methods.loading && methods.error && (
          <ErrorBox message={methods.error} onRetry={methods.reload} />
        )}
        {!methods.loading && !methods.error && list.length === 0 && (
          <div className="state">
            <p className="state__k">暂无可用支付方式</p>
          </div>
        )}

        {!methods.loading && !methods.error && list.length > 0 && (
          <form onSubmit={submit} className="stack">
            {err && (
              <p className="err" role="alert">
                {err}
              </p>
            )}

            <label className="field">
              <span>支付方式</span>
              <select
                className="input"
                name="payment_method_id"
                required
                value={methodId}
                onChange={(e) => setPicked(e.target.value)}
              >
                {list.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name}（{money(m.min_amount)} ~ {isZeroAmount(m.max_amount) ? '不限' : money(m.max_amount)}）
                  </option>
                ))}
              </select>
            </label>

            <label className="field">
              <span>币种</span>
              <select
                className="input"
                name="currency"
                value={currency}
                onChange={(e) => setCurrency(e.target.value)}
              >
                {CURRENCIES.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            </label>

            <label className="field">
              <span>金额</span>
              <input
                className="input"
                name="amount"
                type="text"
                inputMode="decimal"
                autoComplete="off"
                required
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
            </label>

            {method && (
              <p className="small muted" style={{ margin: 0 }}>
                限额 {money(method.min_amount)} ~ {unlimited ? '不限' : money(method.max_amount)}，{currency}
                最多两位小数（JPY/KRW 取整）
              </p>
            )}

            <button type="submit" className="btn btn--primary btn--block" disabled={busy}>
              {busy && <span className="spin" aria-hidden="true" />}
              提交充值
            </button>
          </form>
        )}
      </section>

      {created && (
        <section className="card" aria-label="充值订单" role="status">
          <p className="label">下单成功</p>
          <div className="list" style={{ marginTop: 12 }}>
            <div className="li">
              <span className="small muted">订单号</span>
              <span className="mono">{created.order.order_no}</span>
            </div>
            <div className="li">
              <span className="small muted">到账平台币</span>
              <span className="mono">{money(created.order.platform_amount)}</span>
            </div>
            <div className="li">
              <span className="small muted">支付金额</span>
              <span className="mono">
                {money(created.order.amount)} {created.currency}
              </span>
            </div>
          </div>

          {isSafeUrl(created.order.checkout_url) ? (
            <a
              className="btn btn--primary btn--block"
              style={{ marginTop: 16 }}
              href={created.order.checkout_url}
              target="_blank"
              rel="noopener noreferrer"
            >
              前往支付
            </a>
          ) : (
            <p className="mono" style={{ marginTop: 16, wordBreak: 'break-all' }}>
              {created.order.checkout_url || '支付链接未返回，请稍后在订单中查看'}
            </p>
          )}

          <p className="small muted" style={{ marginBottom: 0 }}>
            请于 {created.order.expires_at} 前完成支付
          </p>
        </section>
      )}
    </>
  );
}
