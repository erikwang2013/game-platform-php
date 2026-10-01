/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 操作日志（GET /admin/v1/log）—— 后端一直有，本树一直没有页面。
 *
 * 这不是「RowBrowser 加一行配置」能了事的：端点带五个筛选参数（动作/路径/起止日期/用户），
 * 而 TabPage 的 Group 只描述一个 path。故整页自己组装：筛选条 + RowBrowser + 导出。
 *
 * 三个后端口径决定了界面的样子（读 LogController）：
 * ① `action` 存的是**HTTP 方法**（OperationLog 中间件 `$log->action = $method`），不是「登录/改价」这类业务动作 ⇒ 选项就是四个方法；
 * ② `path` 是 LIKE 模糊匹配，`action` 是精确匹配；
 * ③ 行里 `user_id` 被 unset 了（只剩 `user_name`）⇒ 按用户 id 筛是个**界面上拿不到值**的参数，不放出来。
 */
import { useState } from 'react';
import { RowBrowser } from '../components/RowBrowser';
import { Card, ErrorNote, PageHead } from '../components/ui';
import { t, useI18n } from '../i18n/index.ts';
import { ApiError } from '../lib/api';
import { downloadFile } from '../lib/download';

/** 提交给端点的筛选条件（键名即后端参数名，空串不发）。 */
type Filters = { action: string; path: string; start_date: string; end_date: string };
const EMPTY: Filters = { action: '', path: '', start_date: '', end_date: '' };

/** 动作就是 HTTP 方法（后端 `$log->action = $method`），值域封闭，故用下拉而不是输入框。 */
const METHODS = ['GET', 'POST', 'PUT', 'DELETE'];

/**
 * 列顺序：时间放最前（日志是按时间读的）、`input` 排最后。
 * `input` 是请求体原文（JSON 串），可能很长 —— cell() 会截断成 48 字符并挂 title，
 * 不摆进 preferred 但它在行里存在，故是第 8 列（columnsFrom 先排 preferred 再补其余）。
 */
const COLUMNS = ['created_at', 'user_name', 'action', 'method', 'path', 'ip', 'source'];

/** 导出用的表名（ExportController 的白名单：admin_user / operation_log / admin_role / system_config）。 */
const EXPORT_TABLE = 'operation_log';

export function LogsPage() {
  // 页面自己订阅语言：Shell 重绘带不动 <Outlet/> 子树（见 i18n/index.ts 的 useI18n 说明）
  useI18n();
  // 输入中的值与应用的值分开：边打字边取数会让每敲一个字母发一次请求，
  // 且 RowBrowser 的「筛选变了回第 1 页」会在半截输入上反复触发
  const [draft, setDraft] = useState<Filters>(EMPTY);
  const [applied, setApplied] = useState<Filters>(EMPTY);
  const [notice, setNotice] = useState<{ text: string; tone: 'error' | 'ok' } | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (key: keyof Filters) => (event: { target: { value: string } }) =>
    setDraft((prev) => ({ ...prev, [key]: event.target.value }));

  /**
   * 导出 Excel。`conditions` 是**精确 where**（ExportController::fetchExportData 逐键 `where`），
   * 故只有「动作」进得去：路径是 LIKE、日期是范围，两者都表达不了 —— 这一点写在按钮旁的常驻提示里，
   * 不让用户以为导出的就是屏幕上这一屏。
   */
  const exportExcel = async () => {
    const conditions: Record<string, string> = {};
    if (applied.action !== '') conditions.action = applied.action;
    const scope = applied.action === '' ? t('logs.export_scope_all') : t('logs.export_scope_action', { action: applied.action });
    if (!window.confirm(t('logs.export_confirm', { scope }))) return;
    setNotice(null);
    setBusy(true);
    try {
      const name = await downloadFile('/admin/v1/export/excel', {
        method: 'POST',
        body: { table: EXPORT_TABLE, columns: [], conditions },
      });
      setNotice({ text: t('export.done', { name }), tone: 'ok' });
    } catch (cause) {
      // 服务端拒绝的原话（表名不在白名单 / 权限不足）原样显示
      setNotice({ text: cause instanceof ApiError ? cause.message : t('app.network_error'), tone: 'error' });
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <PageHead title={t('page.logs.title')} sub={t('page.logs.sub')} />
      <Card title={t('tab.logs')}>
        <form
          className="toolbar"
          onSubmit={(event) => {
            event.preventDefault();
            setApplied(draft);
          }}
        >
          <label className="label">
            {t('f.action')}
            <select className="input" value={draft.action} onChange={set('action')}>
              <option value="">{t('logs.any_action')}</option>
              {METHODS.map((method) => (
                <option key={method} value={method}>
                  {method}
                </option>
              ))}
            </select>
          </label>
          <label className="label">
            {t('f.path')}
            <input className="input" type="text" value={draft.path} onChange={set('path')} placeholder={t('logs.path_placeholder')} />
          </label>
          <label className="label">
            {t('common.start_date')}
            {/* 原生日期控件（rung 4）：不引日期库 */}
            <input className="input" type="date" value={draft.start_date} onChange={set('start_date')} />
          </label>
          <label className="label">
            {t('common.end_date')}
            <input className="input" type="date" value={draft.end_date} onChange={set('end_date')} />
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
          <button type="button" className="btn" disabled={busy} onClick={() => void exportExcel()}>
            {busy ? t('common.submitting') : t('export.excel')}
          </button>
          <span className="muted hint">{t('logs.export_hint')}</span>
        </form>
        {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
        {/* key 钉在应用后的筛选上：换筛选就重挂列表，草稿态不会把旧结果留在屏上 */}
        <RowBrowser key={JSON.stringify(applied)} path="/admin/v1/log" query={applied} preferred={COLUMNS} />
      </Card>
    </>
  );
}
