/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useState } from 'react';
import { fieldLabelKey, t, type MessageKey } from '../i18n/index.ts';
import { ApiError, api, apiEnvelope, type Query } from '../lib/api';
import { rowId, type Field } from '../lib/crud';
import { downloadFile } from '../lib/download';
import { ID_KEYS, pick } from '../lib/format';
import { buildPdfTable } from '../lib/pdf-table.ts';
import { usePagedApi } from '../lib/hooks';
import { totalOf } from '../lib/paging';
import { treeRows, visibleTreeRows } from '../lib/tree';
import { asRows, columnsFrom } from './AutoView';
import { DataTable, cell, type Row } from './DataTable';
import { DetailModal } from './DetailModal';
import { FormModal } from './FormModal';
import { ImportPanel } from './ImportPanel';
// 行尾动作链（含 CrudAction/CrudView 两个类型）搬去了 RowActions.tsx —— 本文件只 re-export 类型，
// 让 `from '../components/RowBrowser'` 的既有导入点（modules.ts / adminUsers.ts）一字不改。
import { RowActions, type CrudAction, type CrudView, type Notice } from './RowActions';
import { ErrorNote, Pager } from './ui';

export type { CrudAction, CrudView } from './RowActions';

/**
 * 一个模块的写操作配置（挂在 TabPage 的 Group.crud 上）。
 *
 * 三个「缺省即不提供该能力」的字段构成动作型模块（身份审核 / 工单）：它们后端只有状态变更
 * 端点，没有增/改/删，所以不给 fields / labelKey（加了就会长出必然 404 的按钮）：
 * - fields 缺省 ⇒ 不显示「+ 新建」「编辑」
 * - labelKey 缺省 ⇒ 不显示「删除」（删除确认要靠它认出对象是谁）
 */
export type CrudConfig = {
  /** 模块端点前缀，如 /admin/v1/game：新建 POST {createPath}、更新 PUT/DELETE {base}/{hashid} */
  base: string;
  /** 模块名（文案键），用在弹框标题与删除确认里 */
  noun: MessageKey;
  /** 新建字段 */
  fields?: Field[];
  /** 编辑字段（缺省同 fields）：与新建不同的只有「创建后不可改」的字段，标 readOnly 即可 */
  editFields?: Field[];
  /**
   * 编辑也发全量（不做「改动比较」，只跳过空值）：update 与 create 共用同一套必填校验、
   * 字段一律从请求体读的模块必须开（风控规则只发改动字段必然 422）。
   */
  fullEdit?: boolean;
  /**
   * 行里承载 hashid 的字段名（缺省 id/hashid）。列表把 hashid 放在别的列上时必给 ——
   * 风控用户列表叫 user_id，不指这一下，行内动作会因为取不到 id 而整排不渲染。
   */
  rowKey?: string;
  /** 删除确认里的对象标识字段（name / title） */
  labelKey?: string;
  /**
   * 状态切换方式：'update' = PUT {base}/{hashid} {status}；
   * 字符串 = POST 该端点 {id,status}（服务端按 body 里的 id 定位）；
   * 函数 = 行 id 在**路径**里、且无请求体（风控规则的 POST {base}/{hashid}/toggle 由服务端自己翻转）。
   * 缺省不显示启停。
   */
  toggle?: 'update' | string | ((id: string) => { path: string; method?: 'POST' | 'PUT'; body?: unknown });
  /**
   * 状态切换的守卫：返回提示语 = **拒绝这次切换**（原样提示，且一个请求都不发）；null = 放行。
   * 只放前端才知道的规则（当前唯一的用处：不许停用当前登录的管理员自己 —— 后端只认「操作者是谁」，
   * 不禁止自伤，真发出去就是把自己账号停了，得再找个人来救）。服务端侧的业务拒绝仍走原样的 message。
   */
  toggleBlock?: (row: Row) => string | null;
  /**
   * 新建地址，缺省 `${base}/create`；ConfigController 是 POST 到 {base} 本身，没有 /create 段。
   * `null` = 该资源**没有新建端点**（如阶梯限额的档位是预置的），此时不摆「+ 新建」——
   * 摆上去就是点了必然 404 的按钮。
   */
  createPath?: string | null;
  /** 删除要带请求体时才给（系统配置删除要当前登录密码）；返回 null = 用户取消，不发请求 */
  deleteBody?: (row: Row) => Record<string, unknown> | null;
  /** 模块独有的行内动作 */
  actions?: CrudAction[];
  /** 模块独有的行内只读视图（按钮 + 详情弹框） */
  views?: CrudView[];
  /** 勾选 + 批量状态变更（后台账号的批量启停） */
  batch?: CrudBatch;
  /**
   * 「导出本页 PDF」工具条按钮（POST /admin/v1/export/pdf，载荷见 lib/pdf-table.ts）。
   * 该端点**不取数**：`data` 由调用方给，所以导出的就是**当前这一页已加载的行** ——
   * 不认筛选、不是全量，按钮上写着行数。`title` 是 PDF 抬头（文案键）。
   */
  exportPdf?: { title: MessageKey };
  /**
   * Excel 批量导入（multipart POST，见 components/ImportPanel.tsx）：`path` 收单个文件段 `file`，
   * 回 `{total, success, failed, errors[]}`。导入会**批量写库**，故成功即回读列表（onDone = reload）。
   */
  importExcel?: { path: string; title: MessageKey };
};

/**
 * 表格级的批量动作：勾选若干行后**一次性**提交，端点收 `{ids: [...], status}`。
 *
 * 为什么不是把行内 toggle 循环调用 N 次：批量端点是一次事务、一条服务端 message、
 * 一个「改了 N 个」的读数；N 次单发则是 N 个请求、N 条提示、中途失败只改了一半
 * （而界面只显示最后一次的结果）。
 */
export type CrudBatch = {
  /** 目标端点（如 /admin/v1/user/batch/status），收 {ids, status} */
  path: string;
  /**
   * 这一行能不能被勾选。缺省全部可勾。
   * 管理员模块拿它挡「批量停用自己」—— 那是单行 toggle 的自我守卫（`toggleBlock`）挡不住的
   * 第二条路：勾上自己一起提交，守卫整条绕过。
   */
  pickable?: (row: Row) => boolean;
  /** 二次确认；按当前勾选数给（0 也要能拿到，提示里要能说出「几个」）。返回 null = 不确认 */
  confirm: (count: number, enable: boolean) => string | null;
};

/**
 * 列表页通用件：列表 + 行点击弹详情；给了 crud 再挂上「新建 / 编辑 / 删除 / 启用·停用」。
 * preferred 只影响列的排序，字段在响应里不存在时不会凭空造列。
 */
export function RowBrowser({
  path,
  query,
  preferred,
  hide,
  detailBase,
  detailTitle = 'common.detail',
  tree,
  paged = true,
  crud,
}: {
  path: string;
  query?: Query;
  preferred?: string[];
  /** 不进列的字段（设备列表的 fp_hash：行内动作的入参，不是给人看的） */
  hide?: string[];
  detailBase?: string;
  detailTitle?: MessageKey;
  /**
   * 树形列表的 children 键（权限树）：children 摊平成**表行**（缩进 + 展开箭头）—— 不摊平子节点
   * 在界面上够不到也就改不到；行还是那些行，行内动作照旧可用。
   */
  tree?: string;
  /** 是否分页，缺省 true。**整表端点**（一次回全量、没有 total，且同时是别处的下拉选项源）
   * 与**裸数组/树**（区服、权限）必须传 false：发 page/page_size 要么没人读、要么真把全量截成 20 条。 */
  paged?: boolean;
  crud?: CrudConfig;
}) {
  // const 别名：闭包里也保住非空收窄
  const config = crud;
  const { data, loading, error, reload, page, setPage, pageSize } = usePagedApi<unknown>(path, query, paged);
  const [selected, setSelected] = useState<string | null>(null);
  // null = 关框；{} = 新建；带 row = 编辑
  const [editing, setEditing] = useState<{ row?: Row } | null>(null);
  const [notice, setNotice] = useState<Notice | null>(null);
  // 树的折叠态存**被折叠**的 id（缺省空集 = 全展开）；存「展开的 id」则重取列表后整棵树会塌成顶层。
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(() => new Set());
  // 批量勾选态存 **id**（不是行对象）：回读列表后行对象会整批换新，存对象会让「已选」凭空丢
  const [picked, setPicked] = useState<ReadonlySet<string>>(() => new Set());
  const [batching, setBatching] = useState(false);
  const [pdfBusy, setPdfBusy] = useState(false);

  const fetched = asRows(data) ?? [];
  const flat = tree ? treeRows(fetched, tree) : null;
  const rows: Row[] = flat ? visibleTreeRows(flat, collapsed) : fetched;
  const total = paged ? totalOf(data, fetched.length) : 0;
  // 列标题复用模块**已经声明过**的字段标签键（`CrudConfig.fields` 里每条都带 `f.*` label）。
  // 之前表头是直接把字段名当标题渲染的 ⇒ 任何语言下都显示 `real_name` 这种裸字段名。
  const columnLabels: Record<string, string> = {};
  // ① 模块自己声明的字段标签是权威（`f.*`）
  for (const field of [...(config?.fields ?? []), ...(config?.editFields ?? [])]) {
    columnLabels[field.name] = field.label;
  }
  // ② 模块没声明的**只读列**（id / created_at / 关联表带出来的 user_name…）退回同名 f.<字段名>；
  //    表里也没有就保持字段名本身（`columnsFrom` 的 ?? key 兜底），不会冒出裸露的 f.xxx
  for (const key of [...(preferred ?? []), ...Object.keys(rows[0] ?? {})]) {
    if (columnLabels[key] !== undefined) continue;
    const fallback = fieldLabelKey(key);
    if (fallback !== null) columnLabels[key] = fallback;
  }
  const columns = columnsFrom(rows, preferred, undefined, hide, columnLabels);
  // 「没有新建/编辑字段」= 该模块没有增改端点（动作型），不摆按钮
  const fields = config?.fields ?? [];
  const canEdit = fields.length > 0;
  // 有编辑字段但没有新建端点（阶梯限额）：编辑照给，「+ 新建」不给。
  // `config !== undefined` 只是给 tsc 的收窄（下面要用 t(config.noun)）：运行时不改变行为
  const canCreate = canEdit && config !== undefined && config.createPath !== null;

  const open = (row: Row) => {
    const id = pick(row, ID_KEYS);
    if (id !== null && id !== undefined && id !== '') setSelected(String(id));
  };

  const toggleRow = (id: string) => {
    setCollapsed((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  // 树的缩进与展开箭头挂在**首列**（权限那组的 preferred 把 name 排在第一，箭头正好挨着名字）
  if (flat && columns.length > 0) {
    const head = columns[0];
    columns[0] = {
      ...head,
      render: (row) => (
        <>
          <TreeMark row={row} collapsed={collapsed} onToggle={toggleRow} />
          {head.render ? head.render(row) : cell(row[head.key])}
        </>
      ),
    };
  }

  // 勾选列插在**最前**（放在树那段之后：树的箭头认的是 columns[0]，抢在它前面会把箭头挂到复选框上）
  const batch = config?.batch;
  const pdf = config?.exportPdf;
  const imports = config?.importExcel;
  const pickable = (row: Row): boolean => {
    if (!batch) return false;
    return rowId(row, config?.rowKey) !== '' && (batch.pickable?.(row) ?? true);
  };
  if (batch) {
    columns.unshift({
      key: '__pick',
      // 与 __actions 同理：存**键**，DataTable 渲染期才过 t()
      label: 'browser.pick',
      render: (row) => {
        const id = rowId(row, config?.rowKey);
        const ok = pickable(row);
        return (
          <input
            type="checkbox"
            checked={ok && picked.has(id)}
            disabled={!ok}
            aria-label={t('browser.pick')}
            // 行点击是「弹详情」：勾一下顺手弹个框很烦人（同 TreeMark 的箭头）
            onClick={(event) => event.stopPropagation()}
            onChange={() =>
              setPicked((prev) => {
                const next = new Set(prev);
                if (next.has(id)) next.delete(id);
                else next.add(id);
                return next;
              })
            }
          />
        );
      },
    });
  }

  /**
   * 导出本页 PDF。列与单元格**照屏幕上那几列**取（含模块自定义的列顺序与 hide），
   * 于是「界面上看不见的字段」（设备页的 fp_hash 之类）不会从 PDF 这条路上漏出去。
   */
  const exportPdf = async (spec: NonNullable<CrudConfig['exportPdf']>) => {
    setNotice(null);
    setPdfBusy(true);
    try {
      const body = buildPdfTable(t(spec.title), columns, rows);
      const name = await downloadFile('/admin/v1/export/pdf', { method: 'POST', body }, 'export.pdf');
      setNotice({ text: t('export.done', { name }), tone: 'ok' });
    } catch (cause) {
      setNotice({ text: cause instanceof ApiError ? cause.message : t('app.network_error'), tone: 'error' });
    } finally {
      setPdfBusy(false);
    }
  };

  /**
   * 批量状态变更。**只显示服务端 message**，不把 `data.count` 拼成「改了 N 个」：
   * 那个 count 是 MySQL 报的 changed rows（本来就处于目标状态的行也算改动，见 UserController::batchStatus），
   * 拿它当数量读数是错的。成功即清空勾选并回读列表。
   */
  const applyBatch = async (enable: boolean) => {
    if (!batch) return;
    const text = batch.confirm(picked.size, enable);
    if (text !== null && !window.confirm(text)) return;
    setNotice(null);
    setBatching(true);
    try {
      const envelope = await apiEnvelope<unknown>(batch.path, {
        method: 'POST',
        body: { ids: [...picked], status: enable ? 1 : 0 },
      });
      setNotice({ text: envelope.message, tone: 'ok' });
      setPicked(new Set());
      reload();
    } catch (cause) {
      setNotice({ text: cause instanceof ApiError ? cause.message : t('app.network_error'), tone: 'error' });
    } finally {
      setBatching(false);
    }
  };

  if (config) {
    columns.push({
      key: '__actions',
      // 存**键**不存译文：DataTable 统一在渲染期过 t()，这里先算一次会把文案冻在当前语言上
      label: 'common.actions',
      render: (row) => (
        <RowActions
          row={row}
          config={config}
          onEdit={(target) => {
            setNotice(null);
            setEditing({ row: target });
          }}
          onNotice={setNotice}
          onDone={reload}
        />
      ),
    });
  }

  const submit = async (body: Record<string, unknown>) => {
    if (!config) return;
    const target = editing?.row;
    if (target) {
      const id = rowId(target, config.rowKey);
      if (!id) return;
      // 一个字段都没改就不空发一次 PUT（后端是局部更新，空体等价于无操作）
      if (Object.keys(body).length > 0) await api(`${config.base}/${id}`, { method: 'PUT', body });
    } else {
      await api(config.createPath ?? `${config.base}/create`, { method: 'POST', body });
    }
    setEditing(null);
    reload();
  };

  return (
    <>
      {canCreate || batch || pdf || imports ? (
        <div className="toolbar">
          {canCreate ? (
            <button
              type="button"
              className="btn"
              onClick={() => {
                setNotice(null);
                setEditing({});
              }}
            >
              {t('browser.new_row', { noun: t(config.noun) })}
            </button>
          ) : null}
          {batch ? (
            <>
              <span className="muted">{t('browser.picked', { count: picked.size })}</span>
              {/* 按钮名用现成的 common.enable/disable：计数就在旁边，不必再造「批量启用」两个键 */}
              {[true, false].map((enable) => (
                <button
                  type="button"
                  className="btn"
                  key={String(enable)}
                  disabled={picked.size === 0 || batching}
                  onClick={() => void applyBatch(enable)}
                >
                  {t(enable ? 'common.enable' : 'common.disable')}
                </button>
              ))}
            </>
          ) : null}
          {pdf ? (
            /* 行数写进按钮名：这个端点不认筛选、也不是全量，导的就是眼前这一页 */
            <button
              type="button"
              className="btn"
              disabled={rows.length === 0 || pdfBusy}
              onClick={() => void exportPdf(pdf)}
            >
              {t('export.pdf_page', { count: rows.length })}
            </button>
          ) : null}
          {/* 导入会写库：成功即回读（onDone = reload），当前页/筛选不变 */}
          {imports ? <ImportPanel spec={imports} onDone={reload} /> : null}
        </div>
      ) : null}
      {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
      <DataTable
        columns={columns}
        rows={rows}
        loading={loading}
        error={error}
        onRetry={reload}
        onRowClick={detailBase ? open : undefined}
      />
      {/* 只有一页（或没有数据）就不画分页条；`page > 1` 也画，是为了「在第 2 页删掉最后一条后
          还能翻回去」——那时 total 已经只剩一页，不画就等于把人钉在空页上 */}
      {paged && (total > pageSize || page > 1) ? (
        <Pager page={page} pages={Math.max(1, Math.ceil(total / pageSize))} total={total} onJump={setPage} />
      ) : null}
      {selected && detailBase ? (
        <DetailModal path={`${detailBase}/${selected}`} title={`${t(detailTitle)} ${selected}`} onClose={() => setSelected(null)} />
      ) : null}
      {/* key 钉住身份：换一条记录编辑就重挂，草稿不会把上一条的值带到这一条上 */}
      {config && editing ? (
        <FormModal
          key={editing.row ? rowId(editing.row, config.rowKey) : '__create'}
          title={editing.row ? t('browser.edit_row', { noun: t(config.noun) }) : t('browser.new_row', { noun: t(config.noun) })}
          fields={editing.row ? (config.editFields ?? fields) : fields}
          row={editing.row}
          fullEdit={config.fullEdit}
          submitLabel={t(editing.row ? 'common.save' : 'common.create')}
          onSubmit={submit}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </>
  );
}

/**
 * 树行的缩进与展开箭头（挂在首列里，名字跟着缩进）。叶子没有箭头，但留同宽占位，同级才对得齐。
 * 箭头点击不能让事件冒到行上：行点击是「弹详情」，点个箭头顺手弹框很烦人。
 */
function TreeMark({
  row,
  collapsed,
  onToggle,
}: {
  row: Row;
  collapsed: ReadonlySet<string>;
  onToggle: (id: string) => void;
}) {
  const id = rowId(row);
  const fold = collapsed.has(id);
  return (
    <span className="treemark" style={{ paddingLeft: `${Number(row.__depth ?? 0) * 1.2}em` }}>
      {Number(row.__kids ?? 0) > 0 ? (
        <button
          type="button"
          className="tgl"
          aria-expanded={!fold}
          aria-label={t(fold ? 'common.expand' : 'common.collapse')}
          onClick={(event) => {
            event.stopPropagation();
            onToggle(id);
          }}
        >
          {fold ? '▸' : '▾'}
        </button>
      ) : (
        <span className="tgl" aria-hidden="true" />
      )}
    </span>
  );
}
