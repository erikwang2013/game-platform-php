/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useState } from 'react';
import { apiEnvelope } from '../lib/api';
import { t, type MessageKey } from '../i18n/index.ts';
import type { Field } from '../lib/crud';
import { DataTable, type Column, type Row } from './DataTable';
import { FormModal } from './FormModal';
import { Modal } from './ui';

/** 端点回来的读数：`ImportController::users` 的 data（errors 是逐行的「第几行、为什么没进」）。 */
type ImportReport = {
  total: number;
  success: number;
  failed: number;
  errors: Row[];
};

/** 逐行失败表照**服务端给的行号**显示：那是 Excel 里的真实行号（含表头行的偏移），
 *  前端拿不到也不该重算 —— 重算一次就多一个和后端对不上的口径。 */
const ERROR_COLUMNS: Column[] = [
  { key: 'row', label: 'f.row', align: 'right' },
  { key: 'reason', label: 'f.reason' },
];

/**
 * Excel 批量导入：工具条按钮 → 选文件弹框 → 结果报表。
 *
 * 为什么不做成 RowBrowser 里的一个内联分支：导入的**产出是一份读数**（成功几行、哪几行没进），
 * 不是一次 CRUD。读数得留在屏幕上给人抄，而 FormModal 提交成功即关框（表单的常规语义，不宜为它破例），
 * 所以结果另开一个框。整块行为收在这一个文件里，RowBrowser 只留一个挂载点（`importExcel`）。
 * 失败路径不用另写：FormModal 自己把服务端 message 显示在框内且**不关框**（改文件重试）。
 */
export function ImportPanel({ spec, onDone }: { spec: { path: string; title: MessageKey }; onDone?: () => void }) {
  const [open, setOpen] = useState(false);
  const [report, setReport] = useState<ImportReport | null>(null);

  // 必填（后端 `$request->file('file')` 拿不到就回「请选择文件」，这里先拦一次省一个来回）；
  // accept 只是选文件对话框的过滤提示，真值域由后端判（见 lib/crud.ts 的 Field.accept）
  const fields: Field[] = [
    { name: 'file', label: 'f.file', type: 'file', required: true, accept: '.xlsx,.xls', hint: 'import.hint' },
  ];

  const submit = async (body: Record<string, unknown>) => {
    // 有文件就一律走 FormData：文件只能靠 multipart 上去，文本字段顺带同路
    const form = new FormData();
    for (const [key, value] of Object.entries(body)) form.append(key, value as string | Blob);
    const envelope = await apiEnvelope<Partial<ImportReport>>(spec.path, { method: 'POST', form });
    const data = envelope.data ?? {};
    setOpen(false);
    // 端点可能不回全 errors/total（后端改口径时不至于把报表框炸成白屏）：缺项当 0/空表
    setReport({
      total: Number(data.total) || 0,
      success: Number(data.success) || 0,
      failed: Number(data.failed) || 0,
      errors: Array.isArray(data.errors) ? data.errors : [],
    });
    onDone?.();
  };

  return (
    <>
      <button type="button" className="btn" onClick={() => setOpen(true)}>
        {t(spec.title)}
      </button>
      {open ? (
        <FormModal
          title={t(spec.title)}
          fields={fields}
          submitLabel={t(spec.title)}
          onSubmit={submit}
          onClose={() => setOpen(false)}
        />
      ) : null}
      {report ? (
        <Modal title={t('import.result')} onClose={() => setReport(null)}>
          <p>{t('import.summary', { total: report.total, success: report.success, failed: report.failed })}</p>
          {/* 这个端点不建角色关联，而鉴权是遍历 roles 聚合的 ⇒ 新账号能登录、每个页面都 403。
              不说就等于让运营导完 200 个账号发下去、再挨个收到「登进去什么都没有」。
              一条都没导进去时不提这茬（没账号，警告是噪声）。 */}
          {/* 恢复路径必须点名**编辑表单里的角色字段**：本树管理员列表的行内动作只有「重置密码」，
              没有分配角色那一项 ⇒ 原先写「从行内动作分配」是把运营指向一个不存在的入口。
              {roles} 取 f.roles，与表单上那个标签同源，改标签这里跟着变。 */}
          {report.success > 0 ? <p className="muted">{t('import.no_roles', { roles: t('f.roles') })}</p> : null}
          {/* 全成功就不摆一张空表：没有失败行时那张表只剩一句「暂无数据」，是噪声 */}
          {report.errors.length > 0 ? <DataTable columns={ERROR_COLUMNS} rows={report.errors} /> : null}
        </Modal>
      ) : null}
    </>
  );
}
