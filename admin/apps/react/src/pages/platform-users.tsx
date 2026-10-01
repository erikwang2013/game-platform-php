/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 平台用户（C 端玩家）标签页：行内「编辑」（nickname / status，局部 PUT）与「注销」，
 * 外加**导出**（POST /admin/v1/export/users → export_users_<YmdHis>.xlsx）。
 *
 * 不走 RowBrowser 的 CrudConfig —— 这个模块没有新建端点，也不该长出「+ 新建」；
 * 注销还要回读列表确认人真的不在了（见 PlatformUserDestroy），通用 delete 撑不住；
 * 导出是**页面级**动作（端点不收行 id，只认一个可选的 status），行内按钮挂不上。
 */
import { useState } from 'react';
import { asRows, columnsFrom } from '../components/AutoView';
import { labelsFor } from '../lib/columns.ts';
import { DataTable, type Row } from '../components/DataTable';
import { FormModal } from '../components/FormModal';
import { ErrorNote, Pager } from '../components/ui';
import { t, useI18n } from '../i18n/index.ts';
import { ApiError, api } from '../lib/api';
import { labelOf } from '../lib/crud';
import { downloadFile } from '../lib/download';
import { ID_KEYS, pick } from '../lib/format';
import { usePagedApi } from '../lib/hooks';
import { totalOf } from '../lib/paging';
import { statusEnumsFor, withStatusLabels } from '../lib/status.ts';
import { PLATFORM_USER_FIELDS } from './modules';
// 详情（只读）：用户字段 + 钱包卡 + 流水表。id 走 hashid，与行内编辑/注销同一个取法
import { PlatformUserDetail } from './wallet';

export function PlatformUsers({ path, preferred }: { path: string; preferred?: string[] }) {
  useI18n();
  const { data, loading, error, reload, page, setPage, pageSize } = usePagedApi<unknown>(path);
  const [notice, setNotice] = useState<string | null>(null);
  const [editing, setEditing] = useState<Row | null>(null);
  // 详情弹框（用户字段 + 钱包 + 流水）。存**行**：弹框标题要用户名，而列表里那列可能被 preferred 挤掉
  const [detail, setDetail] = useState<Row | null>(null);
  const [exporting, setExporting] = useState(false);

  const rows = asRows(data) ?? [];
  const total = totalOf(data, rows.length);
  // labelsFor：这个模块没有 CrudConfig 声明字段，不给这张表就等于整排裸字段名（user_id / vip_level…）
  // 状态列同样要摊平：这一屏的 `status` 是 1=正常 / 0=封禁，比「启用/停用」那套更容易被看反
  const columns = withStatusLabels(
    columnsFrom(rows, preferred, undefined, undefined, labelsFor(rows)),
    statusEnumsFor(path),
  );
  // 操作列排在末尾，不占 preferred 的列预算
  columns.push({
    key: '__actions',
    label: t('common.actions'),
    render: (row) => (
      <span className="rowact">
        {/* 只读入口，排在最前：查看是这一屏最常见、也最安全的动作 */}
        <button
          type="button"
          className="btn btn-sm"
          onClick={() => {
            setNotice(null);
            setDetail(row);
          }}
        >
          {t('common.detail')}
        </button>
        <button
          type="button"
          className="btn btn-sm"
          onClick={() => {
            setNotice(null);
            setEditing(row);
          }}
        >
          {t('common.edit')}
        </button>
        <PlatformUserDestroy row={row} path={path} onNotice={setNotice} onDone={reload} />
      </span>
    ),
  });

  /**
   * 导出全量用户。**不带筛选**：该端点只认一个可选的 `status`，而这一屏没有状态筛选控件
   * —— 照着过滤条件编一个出来就是「界面说的」与「导出里的」对不上。分页在这里也同样不生效
   * （端点自己 limit 10000），故按钮不加页码语义。
   */
  const exportUsers = async () => {
    setNotice(null);
    setExporting(true);
    try {
      const name = await downloadFile('/admin/v1/export/users', { method: 'POST' }, 'users.xlsx');
      setNotice(t('export.done', { name }));
    } catch (cause) {
      setNotice(cause instanceof ApiError ? cause.message : t('app.network_error'));
    } finally {
      setExporting(false);
    }
  };

  const key = editing ? pick(editing, ID_KEYS) : undefined;
  const id = key === null || key === undefined || key === '' ? '' : String(key);
  const detailKey = detail ? pick(detail, ID_KEYS) : undefined;
  const detailId = detailKey === null || detailKey === undefined || detailKey === '' ? '' : String(detailKey);

  return (
    <>
      <div className="toolbar">
        <button type="button" className="btn" disabled={exporting} onClick={() => void exportUsers()}>
          {exporting ? t('common.submitting') : t('export.excel')}
        </button>
      </div>
      {notice ? <ErrorNote message={notice} /> : null}
      <DataTable columns={columns} rows={rows} loading={loading} error={error} onRetry={reload} />
      {total > pageSize || page > 1 ? (
        <Pager page={page} pages={Math.max(1, Math.ceil(total / pageSize))} total={total} onJump={setPage} />
      ) : null}
      {editing ? (
        <FormModal
          key={id}
          title={t('tab.user_edit', { name: labelOf(editing, 'username') })}
          fields={PLATFORM_USER_FIELDS}
          row={editing}
          submitLabel={t('common.save')}
          onSubmit={async (body) => {
            // 一个字段都没改就不空发一次 PUT（后端 update 是局部更新，空体等于无操作）
            if (id !== '' && Object.keys(body).length > 0) {
              await api(`/admin/v1/platform/user/${id}`, { method: 'PUT', body });
            }
            setEditing(null);
            reload();
          }}
          onClose={() => setEditing(null)}
        />
      ) : null}
      {detail && detailId !== '' ? <PlatformUserDetail id={detailId} onClose={() => setDetail(null)} /> : null}
    </>
  );
}

/**
 * 平台用户注销 —— DELETE /admin/v1/platform/user/{hashid}。
 *
 * 后端拒绝有非零余额的用户（安全要求），拒绝原因在信封 message 里 —— 原样显示，
 * 不吞成「操作失败」，否则运营只看到「失败」而不知道该先清余额。
 * 成功以回读为准：重取列表，该用户不再出现才算成功，不以「请求发出去了」为准。
 */
function PlatformUserDestroy({
  row,
  path,
  onNotice,
  onDone,
}: {
  row: Row;
  path: string;
  onNotice: (message: string | null) => void;
  onDone: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const picked = pick(row, ID_KEYS);
  const key = picked === null || picked === undefined || picked === '' ? '' : String(picked);

  const destroy = async () => {
    // 注销前必须能看清是谁：用户名 + 昵称都摆进确认文案（列表里这两列可能被 preferred 挤掉）
    const nickname = String(row.nickname ?? '').trim();
    const who = `${labelOf(row, 'username')}${nickname === '' ? '' : `（${nickname}）`}`;
    if (!key || !window.confirm(t('tab.deactivate_confirm', { who }))) return;
    onNotice(null);
    setBusy(true);
    try {
      await api(`/admin/v1/platform/user/${key}`, { method: 'DELETE' });
    } catch (cause) {
      onNotice(cause instanceof ApiError ? cause.message : t('app.network_error'));
      return;
    } finally {
      setBusy(false);
    }
    try {
      const after = await api<unknown>(path);
      if ((asRows(after) ?? []).some((r) => String(pick(r, ID_KEYS) ?? '') === key)) {
        onNotice(t('tab.deactivate_pending'));
      }
    } catch {
      onNotice(t('tab.deactivate_unreadable'));
    }
    onDone();
  };

  if (!key) return null;
  return (
    <button type="button" className="btn btn-sm" disabled={busy} onClick={() => void destroy()}>
      {t('tab.deactivate')}
    </button>
  );
}
