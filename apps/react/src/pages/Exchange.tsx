/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ApiError,
  api,
  type ExchangeDirection,
  type ExchangeDone,
  type ExchangeQuote,
  type ExchangeRequest,
} from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';

const DIRECTIONS: Array<{ id: ExchangeDirection; label: string; hint: string }> = [
  { id: 'in', label: '买入（平台币 → 游戏币）', hint: '花费平台币，得到游戏币' },
  { id: 'out', label: '卖出（游戏币 → 平台币）', hint: '花费游戏币，得到平台币' },
];

export function Exchange() {
  const games = useAsync(() => api.games({ per_page: 100 }), []);
  const [pickedGame, setPickedGame] = useState('');
  const [pickedCurrency, setPickedCurrency] = useState('');
  const [direction, setDirection] = useState<ExchangeDirection>('in');
  const [amount, setAmount] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [quote, setQuote] = useState<{ req: ExchangeRequest; data: ExchangeQuote } | null>(null);
  const [done, setDone] = useState<ExchangeDone | null>(null);

  const items = games.data?.items ?? [];
  // 未手动选择时回落到第一个游戏/币种，省去 useEffect 同步
  const game = items.find((g) => g.id === pickedGame) ?? items[0] ?? null;
  const currencies = game?.currencies ?? [];
  const currency =
    currencies.find((c) => c.id === pickedCurrency) ?? currencies[0] ?? null;
  const dir = DIRECTIONS.find((d) => d.id === direction) ?? DIRECTIONS[0];

  /** direction='out'（卖）时，后端复用 platform_amount 字段承载「游戏币」数量，勿按字面改名 */
  const buildReq = (): ExchangeRequest => ({
    game_id: game?.id ?? '',
    currency_id: currency?.id ?? '',
    direction,
    platform_amount: amount.trim(),
  });

  // 询价后任何输入变化都会让报价失效，避免按旧价成交
  const fresh =
    quote !== null &&
    quote.req.game_id === (game?.id ?? '') &&
    quote.req.currency_id === (currency?.id ?? '') &&
    quote.req.direction === direction &&
    quote.req.platform_amount === amount.trim();

  // 成交响应的两个金额字段随方向换位（docs/API.md 的 buy/sell）：
  // in 下 platform_amount=支出的平台币、game_amount=到账游戏币；
  // out 下 game_amount=卖出的游戏币、platform_amount=到账平台币。
  // 写死标签 + 写死字段会把 out 的两个数标反。
  const sells = done?.direction === 'out';
  const spentAmount = sells ? done?.game_amount : done?.platform_amount;
  const receivedAmount = sells ? done?.platform_amount : done?.game_amount;

  const doQuote = async () => {
    setErr(null);
    setDone(null);
    setBusy(true);
    try {
      const req = buildReq();
      setQuote({ req, data: await api.exchangeQuote(req) });
    } catch (e) {
      setErr(e instanceof ApiError ? `${e.message}（${e.code}）` : '询价失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  const doExchange = async () => {
    if (!quote) return;
    setErr(null);
    setBusy(true);
    try {
      // 用询价时那份请求下单，保证「看到的价」与「成交的额」一致
      setDone(
        direction === 'in'
          ? await api.exchangeBuy(quote.req)
          : await api.exchangeSell(quote.req),
      );
      setQuote(null);
    } catch (e) {
      setErr(e instanceof ApiError ? `${e.message}（${e.code}）` : '兑换失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="stack">
        <p className="label">兑换</p>
        <h1 className="h1">
          兑换
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          先询价再确认，{dir.hint}。<Link to="/wallet">返回钱包</Link>
        </p>
      </section>

      <section className="card" aria-label="兑换下单">
        {games.loading && <Loading />}
        {!games.loading && games.error && <ErrorBox message={games.error} onRetry={games.reload} />}
        {!games.loading && !games.error && items.length === 0 && (
          <div className="state">
            <p className="state__k">暂无可兑换的游戏</p>
          </div>
        )}

        {!games.loading && !games.error && items.length > 0 && (
          <form
            className="stack"
            onSubmit={(e) => {
              e.preventDefault();
              void doQuote();
            }}
          >
            {err && (
              <p className="err" role="alert">
                {err}
              </p>
            )}

            <label className="field">
              <span>游戏</span>
              <select
                className="input"
                name="game_id"
                value={game?.id ?? ''}
                onChange={(e) => {
                  setPickedGame(e.target.value);
                  setPickedCurrency('');
                  setQuote(null);
                }}
              >
                {items.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name}
                  </option>
                ))}
              </select>
            </label>

            <label className="field">
              <span>游戏币种</span>
              <select
                className="input"
                name="currency_id"
                required
                value={currency?.id ?? ''}
                onChange={(e) => {
                  setPickedCurrency(e.target.value);
                  setQuote(null);
                }}
              >
                {currencies.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}（{c.symbol}）汇率 {c.exchange_rate}
                  </option>
                ))}
              </select>
            </label>

            {currencies.length === 0 && (
              <p className="small muted" style={{ margin: 0 }}>
                该游戏未配置币种，无法兑换。
              </p>
            )}

            <div className="tabs" role="tablist" aria-label="兑换方向">
              {DIRECTIONS.map((d) => (
                <button
                  key={d.id}
                  type="button"
                  role="tab"
                  aria-selected={direction === d.id}
                  className={`tabs__b${direction === d.id ? ' is-on' : ''}`}
                  onClick={() => {
                    setDirection(d.id);
                    setQuote(null);
                  }}
                >
                  {d.id === 'in' ? '买入' : '卖出'}
                </button>
              ))}
            </div>

            <label className="field">
              <span>{direction === 'in' ? '平台币数量' : '游戏币数量'}</span>
              <input
                className="input"
                name="platform_amount"
                type="text"
                inputMode="decimal"
                autoComplete="off"
                required
                value={amount}
                onChange={(e) => {
                  setAmount(e.target.value);
                  setQuote(null);
                }}
              />
            </label>

            <button
              type="submit"
              className="btn btn--block"
              disabled={busy || currencies.length === 0}
            >
              {busy && <span className="spin" aria-hidden="true" />}
              询价
            </button>
          </form>
        )}
      </section>

      {quote && (
        <section className="card" aria-label="询价结果" role="status">
          <p className="label">询价结果</p>
          <div className="list" style={{ marginTop: 12 }}>
            <div className="li">
              <span className="small muted">汇率</span>
              <span className="mono">{quote.data.rate}</span>
            </div>
            <div className="li">
              <span className="small muted">价差</span>
              <span className="mono">{quote.data.spread_pct}%</span>
            </div>
            <div className="li">
              <span className="small muted">价差费用</span>
              <span className="mono">{quote.data.spread_fee}</span>
            </div>
            {'actual_game_amount' in quote.data ? (
              <>
                <div className="li">
                  <span className="small muted">卖出平台币</span>
                  <span className="mono">{quote.data.platform_amount}</span>
                </div>
                <div className="li">
                  <span className="small muted">买入游戏币</span>
                  <span className="mono">{quote.data.game_amount}</span>
                </div>
                <div className="li">
                  <span className="small muted">实际到账游戏币</span>
                  <span className="mono">{quote.data.actual_game_amount}</span>
                </div>
              </>
            ) : (
              <>
                <div className="li">
                  <span className="small muted">卖出游戏币</span>
                  <span className="mono">{quote.data.platform_amount}</span>
                </div>
                <div className="li">
                  <span className="small muted">折算平台币</span>
                  <span className="mono">{quote.data.platform_equivalent}</span>
                </div>
                <div className="li">
                  <span className="small muted">实际到账平台币</span>
                  <span className="mono">{quote.data.actual_platform_amount}</span>
                </div>
              </>
            )}
          </div>

          {fresh ? (
            <button
              type="button"
              className="btn btn--primary btn--block"
              style={{ marginTop: 16 }}
              disabled={busy}
              onClick={() => void doExchange()}
            >
              {busy && <span className="spin" aria-hidden="true" />}
              {direction === 'in' ? '确认买入' : '确认卖出'}
            </button>
          ) : (
            <p className="small muted" style={{ marginBottom: 0, marginTop: 12 }}>
              输入已变更，请重新询价后再确认。
            </p>
          )}
        </section>
      )}

      {done && (
        <section className="card" aria-label="兑换结果" role="status">
          <p className="label">兑换成功</p>
          <div className="list" style={{ marginTop: 12 }}>
            <div className="li">
              <span className="small muted">兑换单号</span>
              <span className="mono">{done.exchange_id}</span>
            </div>
            <div className="li">
              <span className="small muted">方向</span>
              <span className="pill pill--plain">{done.direction === 'in' ? '买入' : '卖出'}</span>
            </div>
            <div className="li">
              <span className="small muted">{sells ? '卖出游戏币' : '支付平台币'}</span>
              <span className="mono">{spentAmount}</span>
            </div>
            <div className="li">
              <span className="small muted">
                {sells ? '到账平台币（已扣点差）' : '到账游戏币（已扣点差）'}
              </span>
              <span className="mono">{receivedAmount}</span>
            </div>
            <div className="li">
              <span className="small muted">价差费用</span>
              <span className="mono">{done.spread_fee}</span>
            </div>
            <div className="li">
              <span className="small muted">成交后平台币余额</span>
              <span className="mono">{done.balance_after}</span>
            </div>
          </div>
        </section>
      )}
    </>
  );
}
