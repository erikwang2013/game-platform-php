/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api, type DepositCreated } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { money } from '../lib/money.ts';
import { ErrorBox, Loading } from '../components/States.tsx';
import type { MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

/** 与后端 DepositController 的 currency 白名单一致 */
const CURRENCIES = ['USD', 'CNY', 'EUR', 'JPY', 'KRW', 'GBP', 'BRL', 'INR'];

/** 只允许跳到真正的 http(s) 链接，其余一律当文本展示（防 javascript: 之类注入） */
const isSafeUrl = (u: string) => /^https?:\/\//i.test(u);

/**
 * 数值为 0 的金额字符串（服务端 DECIMAL(18,4) 下发 "0.0000"，判零不能用 === '0'）。
 *
 * ⚠ 与 `lib/money.ts` 的 `moneyIsZero()` **不是同一个判据，刻意不合并**：
 *  - 本函数只认 unsigned 十进制零（`^0+(\.0+)?$`），
 *  - `moneyIsZero()` 把**非数字串也当零**（那是 `Number(x) > 0` 拆分前的行为，见其 docblock）。
 * 两者在「后端给了 garbage」这一支上结论相反：本函数判**非零** ⇒ 页面把原文（如 `abc`）印出来，
 * 而 `moneyIsZero` 判零 ⇒ 页面会写「不限」——**把坏值说成不限是更坏的失败模式**，故这里保留严判。
 * （线上不可达：`max_amount` 是 unsigned DECIMAL(18,4)；这条差异只在报文被改坏时显形。）
 */
const isZeroAmount = (v: string) => /^0+(\.0+)?$/.test(v);

/** 错误文案的**暂存形**：存「原始错误 + 兜底键」，**不存翻好的串**（理由见 `Exchange.tsx` 的 `Msg`）。 */
type Msg = { err: unknown; fallback: MessageKey };

export function Deposit() {
  const { t } = useI18n();
  const methods = useAsync(() => api.paymentMethods(), []);
  const [picked, setPicked] = useState('');
  const [currency, setCurrency] = useState('USD');
  const [amount, setAmount] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<Msg | null>(null);
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
      setErr({ err: e2, fallback: 'error.submit_failed' });
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="stack">
        <p className="label">{t('tx.deposit')}</p>
        <h1 className="h1">
          {t('tx.deposit')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          {t('deposit.sub')}
          <Link to="/wallet">{t('app.back_wallet')}</Link>
        </p>
      </section>

      <section className="card" aria-label={t('deposit.form_aria')}>
        {methods.loading && <Loading />}
        {!methods.loading && methods.error && (
          <ErrorBox message={methods.error} onRetry={methods.reload} />
        )}
        {!methods.loading && !methods.error && list.length === 0 && (
          <div className="state">
            <p className="state__k">{t('deposit.no_methods')}</p>
          </div>
        )}

        {!methods.loading && !methods.error && list.length > 0 && (
          <form onSubmit={submit} className="stack">
            {err && (
              <p className="err" role="alert">
                {err.err instanceof ApiError
                  ? t('error.with_code', { message: err.err.message, code: err.err.code })
                  : t(err.fallback)}
              </p>
            )}

            <label className="field">
              <span>{t('deposit.method')}</span>
              <select
                className="input"
                name="payment_method_id"
                required
                value={methodId}
                onChange={(e) => setPicked(e.target.value)}
              >
                {list.map((m) => (
                  <option key={m.id} value={m.id}>
                    {t('deposit.method_option', {
                      name: m.name,
                      min: money(m.min_amount),
                      max: isZeroAmount(m.max_amount) ? t('app.unlimited') : money(m.max_amount),
                    })}
                  </option>
                ))}
              </select>
            </label>

            <label className="field">
              <span>{t('deposit.currency')}</span>
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
              <span>{t('deposit.amount')}</span>
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

            {/*
              整句进表（`deposit.limit` 带 `{min}` / `{max}` / `{currency}` 三个参数）而不是拆成
              「限额 + 区间 + ，」三段：HEAD 里这几截**同在一行**，而拆开后 `verify-zh.mjs` 的 A 面
              要的是 `限额 {} ~ {}，{}` 这**一条**归一化后的串，拆开就凑不出它。
              ⚠ 这段注释只能待在 `{method && (` **外面**：`{…}` 是 JSX 子节点，
              塞进 `(` 里就是「JS 表达式里出现 JSX 表达式容器」，直接是语法错（tsc 实测报在下一行）。

              ⚠ 紧跟着的 `{t('deposit.decimals')}` 与上一条**分属两个 JSX 表达式**，中间那段
              只有空白的文本节点会被 JSX 丢掉 ⇒ 渲染成 `…，USD最多两位小数…`（**无空格**），
              与 HEAD 的折行一致（本文件的两行原本同属一个文本节点，行间换行本身也不产空格）。
            */}
            {method && (
              <p className="small muted" style={{ margin: 0 }}>
                {t('deposit.limit', {
                  min: money(method.min_amount),
                  max: unlimited ? t('app.unlimited') : money(method.max_amount),
                  currency,
                })}
                {t('deposit.decimals')}
              </p>
            )}

            <button type="submit" className="btn btn--primary btn--block" disabled={busy}>
              {busy && <span className="spin" aria-hidden="true" />}
              {t('deposit.submit')}
            </button>
          </form>
        )}
      </section>

      {created && (
        <section className="card" aria-label={t('deposit.order_aria')} role="status">
          <p className="label">{t('deposit.created')}</p>
          <div className="list" style={{ marginTop: 12 }}>
            <div className="li">
              <span className="small muted">{t('app.order_no')}</span>
              <span className="mono">{created.order.order_no}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('deposit.credited')}</span>
              <span className="mono">{money(created.order.platform_amount)}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('deposit.paid_amount')}</span>
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
              {t('deposit.go_pay')}
            </a>
          ) : (
            <p className="mono" style={{ marginTop: 16, wordBreak: 'break-all' }}>
              {created.order.checkout_url || t('deposit.no_link')}
            </p>
          )}

          <p className="small muted" style={{ marginBottom: 0 }}>
            {t('deposit.expires', { time: dt(created.order.expires_at) })}
          </p>
        </section>
      )}
    </>
  );
}
