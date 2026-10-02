/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { txLabel } from '../lib/labels.ts';
import { useI18n } from '../i18n/useI18n.ts';
import type { MessageKey } from '../i18n/index.ts';
import { ErrorBox, Loading } from '../components/States.tsx';

type Tab = 'tx' | 'dep' | 'wd' | 'ex';
/**
 * ⚠ `title` / `sub` 是**函数**不是字符串，别改回去。
 *
 * 行是**异步拉回来后存进 state 的**，而文案要在**渲染期**求值才跟得上语言：
 * 存成字符串的那一刻就把语言冻住了，切完语言这一屏一个字都不会变
 * （`useAsync` 的 deps 只有 tab/page，切语言不会重跑拉取）。
 * 把 `t()` / `txLabel()` 留在闭包里、渲染时再调，改的是求值时机不是数据。
 */
type Row = {
  k: string;
  title: () => string;
  sub: () => string;
  amount: string;
  inflow: boolean;
  pill?: string;
};

// 标签保持 2 字：4 个页签在 320px 宽下每格约 70px，4 字标签会被挤断
//
// ⚠ 存**键**不存文案（与 `Layout.tsx` 的 NAV 同款）：模块顶层求值只发生一次，
// 存文案会把它冻在首屏语言上。
const TABS: Array<{ id: Tab; label: MessageKey }> = [
  { id: 'tx', label: 'wallet.tab_tx' },
  { id: 'dep', label: 'tx.deposit' },
  { id: 'wd', label: 'tx.withdraw' },
  { id: 'ex', label: 'tx.exchange' },
];

/** 金额方向只看符号位，不做浮点运算（金额一律字符串透传）。 */
const isNegative = (s: string) => s.trim().startsWith('-');

export function Wallet() {
  const { t } = useI18n();
  const [tab, setTab] = useState<Tab>('tx');
  const [page, setPage] = useState(1);

  const info = useAsync(() => api.wallet(), []);

  const list = useAsync(async () => {
    if (tab === 'tx') {
      const d = await api.transactions({ page });
      return {
        ...d,
        items: d.items.map<Row>((row) => ({
          k: row.id,
          title: () => txLabel(row.type),
          sub: () => row.remark || dt(row.created_at),
          amount: row.amount,
          inflow: !isNegative(row.amount),
        })),
      };
    }
    if (tab === 'dep') {
      const d = await api.deposits({ page });
      return {
        ...d,
        items: d.items.map<Row>((o) => ({
          k: o.order_no,
          title: () => t('wallet.deposit_title', { currency: o.currency }),
          sub: () => dt(o.paid_at || o.created_at),
          amount: o.amount,
          inflow: true,
          pill: o.status,
        })),
      };
    }
    if (tab === 'wd') {
      const d = await api.withdraws({ page });
      return {
        ...d,
        items: d.items.map<Row>((o) => ({
          k: o.order_no,
          title: () => t('tx.withdraw'),
          sub: () => dt(o.created_at),
          amount: o.platform_amount,
          inflow: false,
          pill: o.status,
        })),
      };
    }
    // 兑换记录一律按**平台币那一侧**记收支，与 Exchange 页的头寸口径一致：
    // in（买）下 platform_amount 是支出、out（卖）下它是扣过点差的到账净额。
    const d = await api.exchangeRecords({ page });
    return {
      ...d,
      items: d.items.map<Row>((r) => ({
        k: r.id,
        title: () =>
          t(r.direction === 'in' ? 'wallet.buy_game_currency' : 'wallet.sell_game_currency'),
        sub: () => t('wallet.game_amount_at', { amount: r.game_amount, time: dt(r.created_at) }),
        amount: r.platform_amount,
        inflow: r.direction === 'out',
      })),
    };
  }, [tab, page]);

  const switchTab = (id: Tab) => {
    setTab(id);
    setPage(1);
  };

  return (
    <>
      <section className="stack">
        <p className="label">{t('wallet.title')}</p>
        <h1 className="h1">
          {t('nav.wallet')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>

        {info.loading && <Loading />}
        {!info.loading && info.error && <ErrorBox message={info.error} onRetry={info.reload} />}
        {!info.loading && !info.error && info.data && (
          <div className="grid--stats">
            <div className="stat stat--orange">
              <p className="stat__n">{info.data.balance}</p>
              <p className="stat__k">{t('wallet.available')}</p>
            </div>
            <div className="stat">
              <p className="stat__n">{info.data.frozen_balance}</p>
              <p className="stat__k">{t('wallet.frozen')}</p>
            </div>
            <div className="stat stat--yellow">
              <p className="stat__n">{info.data.total_earned}</p>
              <p className="stat__k">{t('wallet.total_earned')}</p>
            </div>
            <div className="stat">
              <p className="stat__n">{info.data.total_spent}</p>
              <p className="stat__k">{t('wallet.total_spent')}</p>
            </div>
          </div>
        )}

        <div className="row" style={{ gap: 10 }}>
          <Link className="btn btn--primary" to="/wallet/deposit">
            {t('tx.deposit')}
          </Link>
          <Link className="btn" to="/wallet/withdraw">
            {t('tx.withdraw')}
          </Link>
          <Link className="btn" to="/wallet/exchange">
            {t('tx.exchange')}
          </Link>
        </div>
      </section>

      <section className="stack">
        <div className="tabs" style={{ gridTemplateColumns: 'repeat(4, 1fr)' }} role="tablist">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              role="tab"
              aria-selected={tab === item.id}
              className={`tabs__b${tab === item.id ? ' is-on' : ''}`}
              onClick={() => switchTab(item.id)}
            >
              {t(item.label)}
            </button>
          ))}
        </div>

        {list.loading && <Loading />}
        {!list.loading && list.error && <ErrorBox message={list.error} onRetry={list.reload} />}
        {!list.loading && !list.error && list.data && list.data.items.length === 0 && (
          <div className="state">
            <p className="state__k">{t('app.no_records')}</p>
          </div>
        )}
        {!list.loading && !list.error && list.data && list.data.items.length > 0 && (
          <>
            <div className="list">
              {list.data.items.map((r) => (
                <div key={r.k} className="li">
                  <div>
                    <p className="li__t" style={{ margin: 0 }}>
                      {r.title()}
                      {r.pill && (
                        <span className="pill pill--plain" style={{ marginLeft: 8 }}>
                          {r.pill}
                        </span>
                      )}
                    </p>
                    <p className="small muted" style={{ margin: 0 }}>
                      {r.sub()}
                    </p>
                  </div>
                  <span className={`amt ${r.inflow ? 'amt--in' : 'amt--out'}`}>
                    {r.inflow ? '+' : ''}
                    {r.amount}
                  </span>
                </div>
              ))}
            </div>

            {list.data.last_page > 1 && (
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
                  {list.data.page} / {list.data.last_page}
                </span>
                <button
                  type="button"
                  className="btn btn--sm"
                  disabled={page >= list.data.last_page || list.loading}
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
