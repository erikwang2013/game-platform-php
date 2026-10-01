/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 报表：**汇总 KPI + 逐日明细 + 一键导出**（/admin/v1/report/summary | daily | export）。
 *
 * 三个端点共用同一对 `start` / `end`（Y-m-d，缺省最近 30 天），故做成一组：
 * 以前 analytics 页只有 `/report/daily` 一个裸标签页 —— 既没有日期范围（只能看服务端默认的 30 天），
 * 也没有汇总，更没有导出。
 *
 * 后端硬约束：跨度**超过 90 天**直接 `fail(400)`（normalizeDateRange），故提示里写明；
 * 导出给 `format=xlsx`（同一端点也支持 csv，但 xlsx 的表头由后端 `trans()` 按当前语言出，
 * CSV 在 Excel 里还要处理编码，运营拿到就能用）。
 */
import { useState } from 'react';
import { RowBrowser } from '../components/RowBrowser';
import { Section } from '../components/Section';
import { Card, ErrorNote } from '../components/ui';
import { t, useI18n } from '../i18n/index.ts';
import { ApiError } from '../lib/api';
import { downloadFile } from '../lib/download';

/** 逐日明细的列（后端 `rows` 的键顺序）。 */
const DAILY_COLUMNS = [
  'date',
  'new_users',
  'deposit_amount',
  'deposit_count',
  'withdraw_amount',
  'withdraw_count',
  'exchange_amount',
  'play_count',
];

type Range = { start: string; end: string };
const EMPTY: Range = { start: '', end: '' };

export function ReportPanel() {
  useI18n();
  // 草稿与应用分开：改日期不该每敲一下就重取三次（汇总 + 明细 + 导出）
  const [draft, setDraft] = useState<Range>(EMPTY);
  const [applied, setApplied] = useState<Range>(EMPTY);
  const [notice, setNotice] = useState<{ text: string; tone: 'error' | 'ok' } | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (key: keyof Range) => (event: { target: { value: string } }) =>
    setDraft((prev) => ({ ...prev, [key]: event.target.value }));

  const exportReport = async () => {
    setNotice(null);
    setBusy(true);
    try {
      const name = await downloadFile('/admin/v1/report/export', { query: { ...applied, format: 'xlsx' } }, 'report.xlsx');
      setNotice({ text: t('export.done', { name }), tone: 'ok' });
    } catch (cause) {
      // 跨度超限 / 日期格式非法都是服务端 400 的原话，原样显示
      setNotice({ text: cause instanceof ApiError ? cause.message : t('app.network_error'), tone: 'error' });
    } finally {
      setBusy(false);
    }
  };

  /**
   * 全平台流水（POST /admin/v1/export/transactions → xlsx）。
   * 与上面那个导出**不共用日期范围**：该端点只收一个可选的 `type`，`start`/`end` 发了也没人读
   * （ExportController::exportTransactions 里只有 `orderBy(created_at desc)->limit(10000)`）。
   * 故它不在上面那个日期表单里，自带一张卡写明这件事。
   * 不提供 `type` 下拉：值域是 `game_user_transaction.type` 的取值，全仓没有能列出它的端点，
   * 硬编一张表就是「看着能筛、筛出来是空」的假控件。
   */
  const exportLedger = async () => {
    setNotice(null);
    setBusy(true);
    try {
      const name = await downloadFile('/admin/v1/export/transactions', { method: 'POST' }, 'transactions.xlsx');
      setNotice({ text: t('export.done', { name }), tone: 'ok' });
    } catch (cause) {
      setNotice({ text: cause instanceof ApiError ? cause.message : t('app.network_error'), tone: 'error' });
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <Card title={t('report.range')} sub={t('report.range_hint')}>
        <form
          className="toolbar"
          onSubmit={(event) => {
            event.preventDefault();
            setApplied(draft);
          }}
        >
          <label className="label">
            {t('common.start_date')}
            <input className="input" type="date" value={draft.start} onChange={set('start')} />
          </label>
          <label className="label">
            {t('common.end_date')}
            <input className="input" type="date" value={draft.end} onChange={set('end')} />
          </label>
          <button className="btn" type="submit">
            {t('common.search')}
          </button>
          <button
            type="button"
            className="btn"
            onClick={() => {
              setDraft(EMPTY);
              setApplied(EMPTY);
            }}
          >
            {t('common.reset')}
          </button>
          <button type="button" className="btn" disabled={busy} onClick={() => void exportReport()}>
            {busy ? t('common.submitting') : t('export.excel')}
          </button>
        </form>
      </Card>
      {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
      {/* 单独一张卡、不在日期表单里：日期范围对这条导出**不生效**，摆在一起就是假控件 */}
      <Card title={t('report.ledger')} sub={t('report.ledger_hint')}>
        <button type="button" className="btn" disabled={busy} onClick={() => void exportLedger()}>
          {busy ? t('common.submitting') : t('export.excel')}
        </button>
      </Card>
      {/* key 钉在应用后的范围上：换范围就重挂，旧结果不会与新条件并排显示 */}
      <Section key={`s${JSON.stringify(applied)}`} title={t('tab.report_summary')} path="/admin/v1/report/summary" query={applied} />
      {/* 逐日明细：整段返回（`{start,end,rows}`，没有 total），故不分页 */}
      <Card title={t('tab.analytics.report_daily')}>
        <RowBrowser key={JSON.stringify(applied)} path="/admin/v1/report/daily" query={applied} preferred={DAILY_COLUMNS} paged={false} />
      </Card>
    </>
  );
}
