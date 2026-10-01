/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useState } from 'react';
import { fieldLabelKey, t, type MessageKey } from '../i18n/index.ts';
import { ApiError, api, apiEnvelope, type Envelope, type Query } from '../lib/api';
import { labelOf, rowId, statusOf, type Field } from '../lib/crud';
import { ID_KEYS, pick } from '../lib/format';
import { usePagedApi } from '../lib/hooks';
import { totalOf } from '../lib/paging';
import { treeRows, visibleTreeRows } from '../lib/tree';
import { asRows, columnsFrom } from './AutoView';
import { DataTable, cell, type Row } from './DataTable';
import { DetailModal } from './DetailModal';
import { FormModal } from './FormModal';
import { ErrorNote, Pager } from './ui';

/**
 * 模块独有的行内动作（刷新缓存 / 批量分配…）。给了 fields 就弹表单（复用 FormModal），
 * 否则直接执行，confirm 有则先二次确认。
 */
export type CrudAction = {
  /** 按钮名是**文案键**不是译文：模块级常量，只能在渲染期取（同 lib/crud.ts 的 Field.label） */
  label: MessageKey;
  /** 目标地址，按行 id 拼；方法与请求体缺省 POST / 无体 */
  path: (id: string) => string;
  method?: 'POST' | 'PUT';
  /** 表单字段：动作本身也要填参数时给（如分配游戏要一串游戏 hashid） */
  fields?: Field[];
  title?: MessageKey;
  /**
   * 二次确认文案。危险动作（删除/驳回/关闭）必须给，且文案要能认出对象是谁 ——
   * 对象标识是每行不同的，故要给函数时拿得到整行（`row`）。
   */
  confirm?: string | ((row: Row) => string);
  /** 表单之外的固定请求字段（如 assign 的 category_id），与表单值合并后提交 */
  body?: (id: string) => Record<string, unknown>;
  /**
   * 成功后把**服务端 message** 就地显示（绿色提示），而不是只回读列表。
   * 资金动作必给：打款只回「打款成功/已提交」、审核回「初审通过，等待另一管理员确认」——
   * 这些话决定运营下一步做什么，前端自己编一句「操作成功」等于把信息抹了。
   * 给函数时自己拼文案：服务端 message 是占位符（sync-payout 只回 "success"）而有用信息在 data 里。
   */
  report?: boolean | ((envelope: Envelope<unknown>) => string);
  /**
   * 只在满足条件的行上显示该按钮（如只有 payout_batch_id 非空的订单才谈得上「同步打款」）。
   * 缺省全部显示：不筛的话界面上会摆满点了必然 422 的按钮。
   */
  when?: (row: Row) => boolean;
};

/**
 * 只读视图（优惠券 stats）：行尾按钮 + DetailModal 拉该路径，不写任何东西。
 * 与动作分开是因为它没有请求体与方法 —— 塞进 CrudAction 会把 path 逼成可选，污染整个动作链路。
 */
export type CrudView = { label: MessageKey; title?: MessageKey; path: (id: string) => string };

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
};

/**
 * 动作结果提示。tone 区分「服务端拒绝的原话」（红）与「服务端确认成功的原话」（绿）——
 * 两者都是 message，但一个是「为什么没成」，一个是「成了之后下一步是什么」。
 */
type Notice = { text: string; tone: 'error' | 'ok' };

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
      {canCreate ? (
        <div className="toolbar">
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

/**
 * 行尾动作：编辑 / 模块动作（actions）/ 启用·停用（配置了 toggle 时）/ 删除（二次确认）。
 * 成功后统一 onDone() 回读列表 —— 以列表为准，不以「请求发出去了」为准；
 * 失败（含后端 403/422 的业务拒绝）走 onNotice 原样显示 message，不吞成「操作失败」。
 */
function RowActions({
  row,
  config,
  onEdit,
  onNotice,
  onDone,
}: {
  row: Row;
  config: CrudConfig;
  onEdit: (row: Row) => void;
  onNotice: (notice: Notice | null) => void;
  onDone: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState<CrudAction | null>(null);
  const [viewing, setViewing] = useState<{ path: string; title: string } | null>(null);
  const id = rowId(row, config.rowKey);
  if (!id) return null;
  const on = statusOf(row) === 1;
  // 缺省即没有该能力：没有字段就没有编辑，没有 labelKey 就没有删除（动作型模块两者都缺）
  const canEdit = (config.fields?.length ?? 0) > 0;
  const labelKey = config.labelKey;
  // 只显示这一行上说得通的动作（没有批次号就没有「同步打款」）
  const actions = (config.actions ?? []).filter((spec) => spec.when?.(row) ?? true);

  /** 二次确认文案；对象标识按行给时是个函数，没配就是 null（不确认）。 */
  const confirmText = (spec: CrudAction): string | null =>
    typeof spec.confirm === 'function' ? spec.confirm(row) : (spec.confirm ?? null);

  /**
   * 跑一个动作：work 返回**服务端 message** 时就地显示成绿色提示（返回 null 表示没什么可说的）。
   * 失败（含后端 403/422 的业务拒绝）原样显示 message，不吞成「操作失败」。
   * 不乐观更新：成功即 onDone() 回读列表，界面以服务端为准。
   */
  const run = async (work: () => Promise<string | null>, fallback: string) => {
    onNotice(null);
    setBusy(true);
    let message: string | null = null;
    try {
      message = await work();
    } catch (cause) {
      onNotice({ text: cause instanceof ApiError ? cause.message : fallback, tone: 'error' });
      return;
    } finally {
      setBusy(false);
    }
    if (message !== null) onNotice({ text: message, tone: 'ok' });
    onDone();
  };

  /**
   * 动作请求。report 的动作走信封版拿服务端原话（打款只回「已提交」、审核回「等待另一管理员确认」），
   * 其余动作只关心成不成，返回 null 不显示提示。
   */
  const ask = async (spec: CrudAction, body?: Record<string, unknown>): Promise<string | null> => {
    const options = { method: spec.method ?? 'POST', body } as const;
    if (!spec.report) {
      await api(spec.path(id), options);
      return null;
    }
    const envelope = await apiEnvelope<unknown>(spec.path(id), options);
    return typeof spec.report === 'function' ? spec.report(envelope) : envelope.message;
  };

  const remove = () => {
    // 要带请求体的删除（系统配置要密码二次确认）：返回 null = 用户取消，连确认框都不用弹
    const body = config.deleteBody?.(row) ?? null;
    if (config.deleteBody) {
      if (body === null) return;
    } else if (!window.confirm(t('browser.delete_confirm', { noun: t(config.noun), name: labelOf(row, labelKey ?? '') }))) {
      return;
    }
    void run(async () => {
      await api(`${config.base}/${id}`, { method: 'DELETE', body: body ?? undefined });
      return null;
    }, t('common.delete_failed'));
  };

  const toggle = () => {
    // 前端守卫先行：被挡住时连请求都不发（发了就真把账号停了/删了，撤销不回来）
    const blocked = config.toggleBlock?.(row) ?? null;
    if (blocked !== null) {
      onNotice({ text: blocked, tone: 'error' });
      return;
    }
    // 三种口径：update = 走 PUT {status}；函数 = 行 id 在路径里（无请求体）；字符串 = 固定端点收 {id,status}
    const spec = config.toggle;
    const custom = typeof spec === 'function' ? spec(id) : null;
    void run(async () => {
      if (spec === 'update') {
        await api(`${config.base}/${id}`, { method: 'PUT', body: { status: on ? 0 : 1 } });
      } else if (custom) {
        await api(custom.path, { method: custom.method ?? 'POST', body: custom.body });
      } else {
        await api(String(spec), { method: 'POST', body: { id, status: on ? 0 : 1 } });
      }
      return null;
    }, t('common.status_toggle_failed'));
  };

  // 只读视图（优惠券 stats）：拉端点交给 DetailModal，与动作共用行尾按钮位
  const view = (spec: CrudView) => {
    onNotice(null);
    setViewing({ path: spec.path(id), title: `${t(spec.title ?? spec.label)} ${id}` });
  };

  // 有字段的动作弹表单（错误在框内显示，不关框）；没字段的直接跑，confirm 有则先确认
  const fire = (spec: CrudAction) => {
    if (spec.fields) {
      onNotice(null);
      setForm(spec);
      return;
    }
    const text = confirmText(spec);
    if (text !== null && !window.confirm(text)) return;
    void run(() => ask(spec, spec.body?.(id)), t('common.action_failed', { action: t(spec.label) }));
  };

  const submitAction = async (spec: CrudAction, body: Record<string, unknown>) => {
    // 填完表单才问二次确认（驳回这类：先写理由、再确认，取消则不关框可改）
    const text = confirmText(spec);
    if (text !== null && !window.confirm(text)) return;
    // 抛错仍交给 FormModal 在框内显示（不关框，可改后重试）；成功则关框 + 显示服务端 message
    const message = await ask(spec, { ...(spec.body?.(id) ?? {}), ...body });
    setForm(null);
    if (message !== null) onNotice({ text: message, tone: 'ok' });
    onDone();
  };

  return (
    <>
      <span className="rowact">
        {canEdit ? (
          <button type="button" className="btn btn-sm" disabled={busy} onClick={() => onEdit(row)}>
            {t('common.edit')}
          </button>
        ) : null}
        {actions.map((spec) => (
          <button type="button" className="btn btn-sm" key={spec.label} disabled={busy} onClick={() => fire(spec)}>
            {t(spec.label)}
          </button>
        ))}
        {(config.views ?? []).map((spec) => (
          <button type="button" className="btn btn-sm" key={spec.label} disabled={busy} onClick={() => view(spec)}>
            {t(spec.label)}
          </button>
        ))}
        {config.toggle ? (
          <button type="button" className="btn btn-sm" disabled={busy} onClick={toggle}>
            {t(on ? 'common.disable' : 'common.enable')}
          </button>
        ) : null}
        {labelKey !== undefined ? (
          <button type="button" className="btn btn-sm btn-danger" disabled={busy} onClick={remove}>
            {t('common.delete')}
          </button>
        ) : null}
      </span>
      {/* 弹框挂到行动作之外：.rowact 是 inline-flex + nowrap，套在里面会把弹框当弹性项挤着排 */}
      {form ? (
        <FormModal
          key={form.label}
          title={t(form.title ?? form.label)}
          fields={form.fields ?? []}
          submitLabel={t('common.submit')}
          onSubmit={(body) => submitAction(form, body)}
          onClose={() => setForm(null)}
        />
      ) : null}
      {/* viewing.title 是 view() 里现取的成品（键 → 译文 + 行 id），这里原样用，别再取一次 */}
      {viewing ? <DetailModal path={viewing.path} title={viewing.title} onClose={() => setViewing(null)} /> : null}
    </>
  );
}
