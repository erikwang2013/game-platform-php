/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useState } from 'react';
import { ApiError, api, apiEnvelope, type Envelope, type Query } from '../lib/api';
import { labelOf, rowId, statusOf, type Field } from '../lib/crud';
import { ID_KEYS, flattenTree, pick } from '../lib/format';
import { useApi } from '../lib/hooks';
import { asRows, columnsFrom } from './AutoView';
import { DataTable, type Row } from './DataTable';
import { DetailModal } from './DetailModal';
import { FormModal } from './FormModal';
import { ErrorNote } from './ui';

/**
 * 模块独有的行内动作（刷新缓存 / 批量分配…）。给了 fields 就弹表单（复用 FormModal），
 * 否则直接执行，confirm 有则先二次确认。
 */
export type CrudAction = {
  label: string;
  /** 目标地址，按行 id 拼；方法与请求体缺省 POST / 无体 */
  path: (id: string) => string;
  method?: 'POST' | 'PUT';
  /** 表单字段：动作本身也要填参数时给（如分配游戏要一串游戏 hashid） */
  fields?: Field[];
  title?: string;
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
export type CrudView = { label: string; title?: string; path: (id: string) => string };

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
  /** 模块中文名，用在弹框标题与删除确认里 */
  noun: string;
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
  detailTitle = '详情',
  tree,
  crud,
}: {
  path: string;
  query?: Query;
  preferred?: string[];
  /** 不进列的字段（设备列表的 fp_hash：行内动作的入参，不是给人看的） */
  hide?: string[];
  detailBase?: string;
  detailTitle?: string;
  /** 树形列表的 children 键（权限树）：展开成行，否则子节点在界面上够不到 */
  tree?: string;
  crud?: CrudConfig;
}) {
  // const 别名：闭包里也保住非空收窄
  const config = crud;
  const { data, loading, error, reload } = useApi<unknown>(path, query);
  const [selected, setSelected] = useState<string | null>(null);
  // null = 关框；{} = 新建；带 row = 编辑
  const [editing, setEditing] = useState<{ row?: Row } | null>(null);
  const [notice, setNotice] = useState<Notice | null>(null);

  const fetched = asRows(data) ?? [];
  const rows = tree ? flattenTree(fetched, tree) : fetched;
  const columns = columnsFrom(rows, preferred, undefined, hide);
  // 「没有新建/编辑字段」= 该模块没有增改端点（动作型），不摆按钮
  const fields = config?.fields ?? [];
  const canEdit = fields.length > 0;
  // 有编辑字段但没有新建端点（阶梯限额）：编辑照给，「+ 新建」不给
  const canCreate = canEdit && config?.createPath !== null;

  const open = (row: Row) => {
    const id = pick(row, ID_KEYS);
    if (id !== null && id !== undefined && id !== '') setSelected(String(id));
  };

  if (config) {
    columns.push({
      key: '__actions',
      label: '操作',
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
            + 新建
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
      {selected && detailBase ? (
        <DetailModal path={`${detailBase}/${selected}`} title={`${detailTitle} ${selected}`} onClose={() => setSelected(null)} />
      ) : null}
      {/* key 钉住身份：换一条记录编辑就重挂，草稿不会把上一条的值带到这一条上 */}
      {config && editing ? (
        <FormModal
          key={editing.row ? rowId(editing.row, config.rowKey) : '__create'}
          title={`${editing.row ? '编辑' : '新建'}${config.noun}`}
          fields={editing.row ? (config.editFields ?? fields) : fields}
          row={editing.row}
          fullEdit={config.fullEdit}
          submitLabel={editing.row ? '保存' : '创建'}
          onSubmit={submit}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </>
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
    } else if (!window.confirm(`确认删除${config.noun}「${labelOf(row, labelKey ?? '')}」？该操作不可撤销。`)) {
      return;
    }
    void run(async () => {
      await api(`${config.base}/${id}`, { method: 'DELETE', body: body ?? undefined });
      return null;
    }, '删除失败，请稍后重试');
  };

  const toggle = () => {
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
    }, '状态切换失败，请稍后重试');
  };

  // 只读视图（优惠券 stats）：拉端点交给 DetailModal，与动作共用行尾按钮位
  const view = (spec: CrudView) => {
    onNotice(null);
    setViewing({ path: spec.path(id), title: `${spec.title ?? spec.label} ${id}` });
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
    void run(() => ask(spec, spec.body?.(id)), `${spec.label}失败，请稍后重试`);
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
            编辑
          </button>
        ) : null}
        {actions.map((spec) => (
          <button type="button" className="btn btn-sm" key={spec.label} disabled={busy} onClick={() => fire(spec)}>
            {spec.label}
          </button>
        ))}
        {(config.views ?? []).map((spec) => (
          <button type="button" className="btn btn-sm" key={spec.label} disabled={busy} onClick={() => view(spec)}>
            {spec.label}
          </button>
        ))}
        {config.toggle ? (
          <button type="button" className="btn btn-sm" disabled={busy} onClick={toggle}>
            {on ? '停用' : '启用'}
          </button>
        ) : null}
        {labelKey !== undefined ? (
          <button type="button" className="btn btn-sm btn-danger" disabled={busy} onClick={remove}>
            删除
          </button>
        ) : null}
      </span>
      {/* 弹框挂到行动作之外：.rowact 是 inline-flex + nowrap，套在里面会把弹框当弹性项挤着排 */}
      {form ? (
        <FormModal
          key={form.label}
          title={form.title ?? form.label}
          fields={form.fields ?? []}
          submitLabel="提交"
          onSubmit={(body) => submitAction(form, body)}
          onClose={() => setForm(null)}
        />
      ) : null}
      {viewing ? <DetailModal path={viewing.path} title={viewing.title} onClose={() => setViewing(null)} /> : null}
    </>
  );
}
