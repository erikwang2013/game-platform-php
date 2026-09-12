/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { api } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';

type Tab = 'tx' | 'dep' | 'wd';
type Row = { k: string; title: string; sub: string; amount: string; inflow: boolean; pill?: string };

const TABS: Array<{ id: Tab; label: string }> = [
  { id: 'tx', label: '资金流水' },
  { id: 'dep', label: '充值订单' },
  { id: 'wd', label: '提现订单' },
];

/** 金额方向只看符号位，不做浮点运算（金额一律字符串透传）。 */
const isNegative = (s: string) => s.trim().startsWith('-');

export function Wallet() {
  const [tab, setTab] = useState<Tab>('tx');
  const [page, setPage] = useState(1);

  const info = useAsync(() => api.wallet(), []);

  const list = useAsync(async () => {
    if (tab === 'tx') {
      const d = await api.transactions({ page });
      return {
        ...d,
        items: d.items.map<Row>((t) => ({
          k: t.id,
          title: t.type,
          sub: t.remark || t.created_at,
          amount: t.amount,
          inflow: !isNegative(t.amount),
        })),
      };
    }
    if (tab === 'dep') {
      const d = await api.deposits({ page });
      return {
        ...d,
        items: d.items.map<Row>((o) => ({
          k: o.order_no,
          title: `充值 ${o.currency}`,
          sub: o.paid_at || o.created_at,
          amount: o.amount,
          inflow: true,
          pill: o.status,
        })),
      };
    }
    const d = await api.withdraws({ page });
    return {
      ...d,
      items: d.items.map<Row>((o) => ({
        k: o.order_no,
        title: '提现',
        sub: o.created_at,
        amount: o.platform_amount,
        inflow: false,
        pill: o.status,
      })),
    };
  }, [tab, page]);

  const switchTab = (t: Tab) => {
    setTab(t);
    setPage(1);
  };

  return (
    <>
      <section className="stack">
        <p className="label">我的钱包</p>
        <h1 className="h1">
          钱包
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>

        {info.loading && <Loading />}
        {!info.loading && info.error && <ErrorBox message={info.error} onRetry={info.reload} />}
        {!info.loading && !info.error && info.data && (
          <div className="grid--stats">
            <div className="stat stat--orange">
              <p className="stat__n">{info.data.balance}</p>
              <p className="stat__k">可用余额</p>
            </div>
            <div className="stat">
              <p className="stat__n">{info.data.frozen_balance}</p>
              <p className="stat__k">冻结金额</p>
            </div>
            <div className="stat stat--yellow">
              <p className="stat__n">{info.data.total_earned}</p>
              <p className="stat__k">累计收入</p>
            </div>
            <div className="stat">
              <p className="stat__n">{info.data.total_spent}</p>
              <p className="stat__k">累计支出</p>
            </div>
          </div>
        )}
      </section>

      <section className="stack">
        <div className="tabs" style={{ gridTemplateColumns: 'repeat(3, 1fr)' }} role="tablist">
          {TABS.map((t) => (
            <button
              key={t.id}
              type="button"
              role="tab"
              aria-selected={tab === t.id}
              className={`tabs__b${tab === t.id ? ' is-on' : ''}`}
              onClick={() => switchTab(t.id)}
            >
              {t.label}
            </button>
          ))}
        </div>

        {list.loading && <Loading />}
        {!list.loading && list.error && <ErrorBox message={list.error} onRetry={list.reload} />}
        {!list.loading && !list.error && list.data && list.data.items.length === 0 && (
          <div className="state">
            <p className="state__k">暂无记录</p>
          </div>
        )}
        {!list.loading && !list.error && list.data && list.data.items.length > 0 && (
          <>
            <div className="list">
              {list.data.items.map((r) => (
                <div key={r.k} className="li">
                  <div>
                    <p className="li__t" style={{ margin: 0 }}>
                      {r.title}
                      {r.pill && (
                        <span className="pill pill--plain" style={{ marginLeft: 8 }}>
                          {r.pill}
                        </span>
                      )}
                    </p>
                    <p className="small muted" style={{ margin: 0 }}>
                      {r.sub}
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
                  上一页
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
                  下一页
                </button>
              </div>
            )}
          </>
        )}
      </section>
    </>
  );
}
