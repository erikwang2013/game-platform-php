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
import { useCaptcha } from '../lib/useCaptcha.tsx';
import type { MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

/**
 * ⚠ 表里存的是**键**不是文案（与 `Layout.tsx` 的 `NAV`、`Wallet.tsx` 的 `TABS` 同款）：
 * 模块顶层只求值一次，写成 `t(...)` 会把这四个串冻在首屏语言上，切语言后
 * 页头那句「先询价再确认，…」一个字都不会变。
 */
const DIRECTIONS: Array<{ id: ExchangeDirection; label: MessageKey; hint: MessageKey }> = [
  { id: 'in', label: 'exchange.dir_in_label', hint: 'exchange.hint_in' },
  { id: 'out', label: 'exchange.dir_out_label', hint: 'exchange.hint_out' },
];

/** 错误文案的**暂存形**：存「原始错误 + 兜底键」，**不存翻好的串**（理由同上，见 `Wallet.tsx` 的 `Row`）。 */
type Msg = { err: unknown; fallback: MessageKey };

export function Exchange() {
  const { t } = useI18n();
  const games = useAsync(() => api.games({ per_page: 100 }), []);
  const [pickedGame, setPickedGame] = useState('');
  const [pickedCurrency, setPickedCurrency] = useState('');
  const [direction, setDirection] = useState<ExchangeDirection>('in');
  const [amount, setAmount] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<Msg | null>(null);
  const [quote, setQuote] = useState<{ req: ExchangeRequest; data: ExchangeQuote } | null>(null);
  const [done, setDone] = useState<ExchangeDone | null>(null);
  const cap = useCaptcha();

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
      setErr({ err: e, fallback: 'exchange.quote_failed' });
    } finally {
      setBusy(false);
    }
  };

  /** 统一收口下单的 busy / 错误 / 报价失效处理 */
  const commit = async (send: () => Promise<ExchangeDone>) => {
    setErr(null);
    setBusy(true);
    try {
      // 用询价时那份请求下单，保证「看到的价」与「成交的额」一致
      setDone(await send());
      setQuote(null);
    } catch (e) {
      // 失败（含 422 验证码错误）：框已关，服务端 message 落在下方错误位，下次确认重取
      setErr({ err: e, fallback: 'exchange.failed' });
    } finally {
      setBusy(false);
    }
  };

  const doExchange = async () => {
    if (!quote) return;
    const req = quote.req;
    // 卖出需验证码（后端强制）；买入不加，故不弹框
    if (direction === 'in') return commit(() => api.exchangeBuy(req));
    const proof = await cap.ask();
    if (!proof) return; // 用户取消
    await commit(() => api.exchangeSell({ ...req, ...proof }));
  };

  return (
    <>
      <section className="stack">
        <p className="label">{t('tx.exchange')}</p>
        <h1 className="h1">
          {t('tx.exchange')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        {/*
          整句进表（`exchange.sub` 带一个 `{hint}` 参数）而不是拆成「前半 + 提示 + 。」三截：
          HEAD 里这三截**同在一行**，JSX 不会在它们之间插空格，而拆开后
          `verify-zh.mjs` 的 A 面（HEAD 逐行取「标签之间的文本」）要的是
          `先询价再确认，{}。` 这**一条**归一化后的串，拆开就凑不出它。
          `{hint}` 传的是**键求值后的文案**（`dir.hint` 存键，见文件头的注释）。
        */}
        <p className="small muted" style={{ margin: 0 }}>
          {t('exchange.sub', { hint: t(dir.hint) })}
          <Link to="/wallet">{t('app.back_wallet')}</Link>
        </p>
      </section>

      <section className="card" aria-label={t('exchange.form_aria')}>
        {games.loading && <Loading />}
        {!games.loading && games.error && <ErrorBox message={games.error} onRetry={games.reload} />}
        {!games.loading && !games.error && items.length === 0 && (
          <div className="state">
            <p className="state__k">{t('exchange.no_games')}</p>
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
                {err.err instanceof ApiError
                  ? t('error.with_code', { message: err.err.message, code: err.err.code })
                  : t(err.fallback)}
              </p>
            )}

            <label className="field">
              <span>{t('exchange.game')}</span>
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
              <span>{t('exchange.currency')}</span>
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
                    {t('exchange.currency_option', {
                      name: c.name,
                      symbol: c.symbol,
                      // `exchange_rate` 是可选的：HEAD 里直接插值，缺值时渲成空串，这里补 `''` 保持同款
                      rate: c.exchange_rate ?? '',
                    })}
                  </option>
                ))}
              </select>
            </label>

            {currencies.length === 0 && (
              <p className="small muted" style={{ margin: 0 }}>
                {t('exchange.no_currency')}
              </p>
            )}

            <div className="tabs" role="tablist" aria-label={t('exchange.direction_aria')}>
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
                  {d.id === 'in' ? t('exchange.buy') : t('exchange.sell')}
                </button>
              ))}
            </div>

            <label className="field">
              <span>{direction === 'in' ? t('exchange.platform_amount') : t('exchange.game_amount')}</span>
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
              {t('exchange.quote')}
            </button>
          </form>
        )}
      </section>

      {quote && (
        <section className="card" aria-label={t('exchange.quote_result')} role="status">
          <p className="label">{t('exchange.quote_result')}</p>
          <div className="list" style={{ marginTop: 12 }}>
            <div className="li">
              <span className="small muted">{t('exchange.rate')}</span>
              <span className="mono">{quote.data.rate}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('exchange.spread')}</span>
              <span className="mono">{quote.data.spread_pct}%</span>
            </div>
            <div className="li">
              <span className="small muted">{t('exchange.spread_fee')}</span>
              <span className="mono">{quote.data.spread_fee}</span>
            </div>
            {'actual_game_amount' in quote.data ? (
              <>
                <div className="li">
                  <span className="small muted">{t('exchange.sell_platform')}</span>
                  <span className="mono">{quote.data.platform_amount}</span>
                </div>
                <div className="li">
                  <span className="small muted">{t('exchange.buy_game')}</span>
                  <span className="mono">{quote.data.game_amount}</span>
                </div>
                <div className="li">
                  <span className="small muted">{t('exchange.actual_game')}</span>
                  <span className="mono">{quote.data.actual_game_amount}</span>
                </div>
              </>
            ) : (
              <>
                <div className="li">
                  <span className="small muted">{t('exchange.sell_game')}</span>
                  <span className="mono">{quote.data.platform_amount}</span>
                </div>
                <div className="li">
                  <span className="small muted">{t('exchange.platform_equivalent')}</span>
                  <span className="mono">{quote.data.platform_equivalent}</span>
                </div>
                <div className="li">
                  <span className="small muted">{t('exchange.actual_platform')}</span>
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
              {direction === 'in' ? t('exchange.confirm_buy') : t('exchange.confirm_sell')}
            </button>
          ) : (
            <p className="small muted" style={{ marginBottom: 0, marginTop: 12 }}>
              {t('exchange.stale')}
            </p>
          )}
        </section>
      )}

      {done && (
        <section className="card" aria-label={t('exchange.done_aria')} role="status">
          <p className="label">{t('exchange.done')}</p>
          <div className="list" style={{ marginTop: 12 }}>
            <div className="li">
              <span className="small muted">{t('exchange.order_no')}</span>
              <span className="mono">{done.exchange_id}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('exchange.direction')}</span>
              <span className="pill pill--plain">{done.direction === 'in' ? t('exchange.buy') : t('exchange.sell')}</span>
            </div>
            <div className="li">
              <span className="small muted">{sells ? t('exchange.sell_game') : t('exchange.pay_platform')}</span>
              <span className="mono">{spentAmount}</span>
            </div>
            <div className="li">
              <span className="small muted">
                {sells ? t('exchange.recv_platform') : t('exchange.recv_game')}
              </span>
              <span className="mono">{receivedAmount}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('exchange.spread_fee')}</span>
              <span className="mono">{done.spread_fee}</span>
            </div>
            <div className="li">
              <span className="small muted">{t('exchange.balance_after')}</span>
              <span className="mono">{done.balance_after}</span>
            </div>
          </div>
        </section>
      )}

      {cap.modal}
    </>
  );
}
