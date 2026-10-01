/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 行尾动作（编辑 / 模块动作 / 只读视图 / 启停 / 删除）与它的类型词汇。
 *
 * 从 `RowBrowser.tsx` 搬出来：那个文件已经 525 行（越过仓库的 500 行线），而本批还要给它加
 * 「勾选 + 批量」——先把这 180 余行动作链整块挪出来，功能一行没改。
 * 类型（`CrudAction` / `CrudView`）跟着走，`RowBrowser.tsx` 原样 re-export，
 * 各模块（modules.ts / adminUsers.ts）的 `from '../components/RowBrowser'` 不用改。
 *
 * ⚠ `CrudConfig` **留**在 RowBrowser.tsx 里（它是整张列表的配置，不只是动作的），
 * 这里按**类型**引它：类型导入在编译期就被抹掉，运行期不会形成 RowBrowser ⇄ RowActions 的循环。
 */
import { useState } from 'react';
import { t, type MessageKey } from '../i18n/index.ts';
import { ApiError, api, apiEnvelope, type Envelope } from '../lib/api';
import { labelOf, rowId, statusOf, type Field } from '../lib/crud.ts';
import { downloadFile } from '../lib/download';
import { type Row } from './DataTable';
import { DetailModal } from './DetailModal';
import { FormModal } from './FormModal';
import type { CrudConfig } from './RowBrowser';

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
  /**
   * 表单字段：动作本身也要填参数时给（如分配游戏要一串游戏 hashid）。
   * 也可以给**异步来源**（与 `FieldOptions` 同一个形态）：现值只有详情端点才拿得到时，
   * 字段表本身要等取回来才知道（游戏币种：手打整张表必然丢掉 id，而丢了 id 就是新建一条 ⇒ 重复行）。
   * 拉取失败**不开框**，原样显示服务端 message。
   */
  fields?: Field[] | ((row: Row) => Promise<Field[]>);
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
  /**
   * 该动作的响应是**文件**而不是信封（订单凭证 PDF）：给落盘文件名兜底。
   * 走 downloadFile（成功是二进制、失败仍是信封，按 content-type 分流），
   * 成功后提示「已导出 xxx」；失败照旧原样显示服务端 message。
   */
  download?: string;
};

/**
 * 只读视图（优惠券 stats）：行尾按钮 + DetailModal 拉该路径，不写任何东西。
 * 与动作分开是因为它没有请求体与方法 —— 塞进 CrudAction 会把 path 逼成可选，污染整个动作链路。
 */
export type CrudView = { label: MessageKey; title?: MessageKey; path: (id: string) => string };

/**
 * 动作结果提示。tone 区分「服务端拒绝的原话」（红）与「服务端确认成功的原话」（绿）——
 * 两者都是 message，但一个是「为什么没成」，一个是「成了之后下一步是什么」。
 */
export type Notice = { text: string; tone: 'error' | 'ok' };

/**
 * 行尾动作：编辑 / 模块动作（actions）/ 启用·停用（配置了 toggle 时）/ 删除（二次确认）。
 * 成功后统一 onDone() 回读列表 —— 以列表为准，不以「请求发出去了」为准；
 * 失败（含后端 403/422 的业务拒绝）走 onNotice 原样显示 message，不吞成「操作失败」。
 */
export function RowActions({
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
  // 打开中的动作表单：fields 已解析成数组（异步来源在开框前就 await 完，框里不会先空后跳）
  const [form, setForm] = useState<{ spec: CrudAction; fields: Field[] } | null>(null);
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
    // 文件类动作（凭证 PDF）：落盘名以响应头为准，spec.download 只是兜底
    if (spec.download !== undefined) {
      return t('export.done', { name: await downloadFile(spec.path(id), options, spec.download) });
    }
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
    if (!spec.fields) {
      const text = confirmText(spec);
      if (text !== null && !window.confirm(text)) return;
      void run(() => ask(spec, spec.body?.(id)), t('common.action_failed', { action: t(spec.label) }));
      return;
    }
    onNotice(null);
    void (async () => {
      try {
        // 先取字段表再开框：拉不到就**别开**（空框提交出去等于把现值清空）
        const fields = typeof spec.fields === 'function' ? await spec.fields(row) : spec.fields;
        setForm({ spec, fields: fields ?? [] });
      } catch (cause) {
        onNotice({ text: cause instanceof ApiError ? cause.message : t('app.network_error'), tone: 'error' });
      }
    })();
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
          key={form.spec.label}
          title={t(form.spec.title ?? form.spec.label)}
          fields={form.fields}
          submitLabel={t('common.submit')}
          onSubmit={(body) => submitAction(form.spec, body)}
          onClose={() => setForm(null)}
        />
      ) : null}
      {/* viewing.title 是 view() 里现取的成品（键 → 译文 + 行 id），这里原样用，别再取一次 */}
      {viewing ? <DetailModal path={viewing.path} title={viewing.title} onClose={() => setViewing(null)} /> : null}
    </>
  );
}
