/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 资金页里**不是行列表**的三块：全局提现开关、阶梯限额的全档位重置、批量审核。
 * 字段描述仍归 modules.ts（本文件只放控件与接线），底座（RowBrowser/FormModal）表达不了的是
 * 「一个全局对象」「不带行上下文的批量端点」—— 这两类没有行可挂动作，硬塞进行尾按钮只会错。
 */
import { useState } from 'react';
import { FormModal } from '../components/FormModal';
import { RowBrowser } from '../components/RowBrowser';
import { ErrorNote, Loading } from '../components/ui';
import { ApiError, apiEnvelope } from '../lib/api';
import type { Field } from '../lib/crud';
import { useApi } from '../lib/hooks';
import { WITHDRAW_LIMIT_CRUD, WITHDRAW_ORDER_CRUD } from './modules';

/** 动作结果提示（与服务端 message 同形），tone 区分「服务端确认」与「服务端拒绝」。 */
type Notice = { text: string; tone: 'error' | 'ok' };

/**
 * 全局提现开关 —— GET / PUT 共用 `/admin/v1/withdraw/switch`（后端按 method 分流）。
 * GET 回来是 `{global_switch, enabled, status}`：同一个布尔值的三种键名（历史读法不同），
 * 写的是 `{enabled: 0|1}`。读回来的是**平台配置的当前值**，不是乐观值。
 *
 * 这是全平台提现的总闸：关掉之后所有用户立刻提交不了新申请（已在处理的订单不受影响），
 * 故两个方向都要二次确认，确认后显示服务端 message 再回读。
 */
export function WithdrawSwitch() {
  const { data, loading, error, reload } = useApi<Record<string, unknown>>('/admin/v1/withdraw/switch');
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const on = Number(data?.status ?? 0) === 1;

  const flip = async (next: 0 | 1) => {
    const question =
      next === 1
        ? '确认开启全平台提现？开启后所有用户可立即提交提现申请。'
        : '确认关闭全平台提现？关闭后所有用户立刻无法提交新的提现申请（已在处理中的订单不受影响）。';
    if (!window.confirm(question)) return;
    setNotice(null);
    setBusy(true);
    try {
      const envelope = await apiEnvelope<unknown>('/admin/v1/withdraw/switch', { method: 'PUT', body: { enabled: next } });
      setNotice({ text: envelope.message, tone: 'ok' });
    } catch (cause) {
      setNotice({ text: cause instanceof ApiError ? cause.message : '网络异常，请稍后重试', tone: 'error' });
      return;
    } finally {
      setBusy(false);
    }
    reload();
  };

  if (loading) return <Loading />;
  if (error) return <ErrorNote message={error} onRetry={reload} />;

  return (
    <>
      {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
      <div className="stack">
        <p className="muted">
          当前状态：{on ? '已开启' : '已关闭'}。关闭后全平台用户无法提交新的提现申请。
        </p>
        <button type="button" className={on ? 'btn btn-danger' : 'btn'} disabled={busy} onClick={() => void flip(on ? 0 : 1)}>
          {busy ? '提交中…' : on ? '关闭提现' : '开启提现'}
        </button>
      </div>
    </>
  );
}

/**
 * 阶梯限额：单档精调走行内「编辑」（PUT limits/{hashid}），
 * 「全档位重置」走 POST limits/set —— 它一次写**所有**档位（含 platform_config 回落配置），
 * 没有行上下文，故做成页签级按钮而不是行内动作。
 */
export function WithdrawLimits({ path }: { path: string }) {
  const [open, setOpen] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  // 全档位重置后要重取列表（RowBrowser 自己持 useApi）：换 key 重挂 = 唯一不含糊的重取方式
  const [nonce, setNonce] = useState(0);

  const submit = async (body: Record<string, unknown>) => {
    if (Object.keys(body).length === 0) throw new ApiError(422, '三项都留空：没有要写入的值');
    const values = Object.entries(body)
      .map(([key, value]) => `${SET_LABELS[key] ?? key} = ${String(value)}`)
      .join('，');
    if (!window.confirm(`确认把 ${values} 写入**全部**限额档位？各档现有的同名值会被覆盖（单笔最高/月限额不动）。`)) {
      return;
    }
    const envelope = await apiEnvelope<unknown>('/admin/v1/withdraw/limits/set', { method: 'POST', body });
    setOpen(false);
    setNotice({ text: envelope.message, tone: 'ok' });
    setNonce((n) => n + 1);
  };

  return (
    <>
      <div className="toolbar">
        <button type="button" className="btn" onClick={() => {
          setNotice(null);
          setOpen(true);
        }}>
          全档位重置
        </button>
      </div>
      {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
      <RowBrowser
        key={nonce}
        path={path}
        preferred={['id', 'user_level', 'single_min', 'single_max', 'daily_limit', 'fee_pct', 'fee_max']}
        crud={WITHDRAW_LIMIT_CRUD}
      />
      {open ? (
        <FormModal
          title="全档位重置（写入所有档位）"
          fields={SET_FIELDS}
          submitLabel="写入全部档位"
          onSubmit={submit}
          onClose={() => setOpen(false)}
        />
      ) : null}
    </>
  );
}

const SET_LABELS: Record<string, string> = {
  daily_limit: '每日限额',
  min_amount: '单笔最低',
  auto_approve_threshold: '自动审核阈值',
};

/** 端点只收这三项（列名映射：min_amount → 各档 single_min）；留空即不写该列。 */
const SET_FIELDS: Field[] = [
  { name: 'min_amount', label: '单笔最低', type: 'text', hint: '写入所有档位的 single_min；留空 = 不动这一项' },
  { name: 'daily_limit', label: '每日限额', type: 'text', hint: '写入所有档位；留空 = 不动' },
  { name: 'auto_approve_threshold', label: '自动审核阈值', type: 'text', hint: '写入所有档位；留空 = 不动' },
];

/**
 * 提现订单页签：批量审核 + 订单列表。
 * 批量端点收的是一串订单 hashid，没有「当前行」可挂 —— 故按钮开表单，把订单号一行一个贴进去
 * （复用 lines 字段 + FormModal）。二次确认里把**贴进去的每一条订单标识**与「驳回会逐笔退款」都写出来：
 * 批量动作是不可逆的，文案里必须能认出影响面。
 */
export function WithdrawOrders({ path }: { path: string }) {
  const [open, setOpen] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [nonce, setNonce] = useState(0);

  const submit = async (body: Record<string, unknown>) => {
    const ids = (Array.isArray(body.ids) ? body.ids : []) as string[];
    const action = String(body.action ?? '');
    const verb = action === 'reject' ? '驳回' : '通过';
    const tail = action === 'reject' ? '：驳回会逐笔把金额退回用户余额并记退款流水，不可撤销。' : '。';
    if (!window.confirm(`确认对以下 ${ids.length} 笔订单执行「${verb}」？\n${ids.join('\n')}\n${tail}`)) return;

    const envelope = await apiEnvelope<{ failed?: unknown[] }>('/admin/v1/withdraw/batch-review', {
      method: 'POST',
      body,
    });
    setOpen(false);
    // 成功信封里也可能带失败清单（逐笔事务，部分失败不回滚别的）：有失败就别摆成绿色的「一切正常」
    const failed = Array.isArray(envelope.data?.failed) ? envelope.data.failed : [];
    setNotice({ text: envelope.message, tone: failed.length > 0 ? 'error' : 'ok' });
    setNonce((n) => n + 1);
  };

  return (
    <>
      <div className="toolbar">
        <button type="button" className="btn" onClick={() => {
          setNotice(null);
          setOpen(true);
        }}>
          批量审核
        </button>
      </div>
      {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
      <RowBrowser key={nonce} path={path} preferred={ORDER_COLUMNS} crud={WITHDRAW_ORDER_CRUD} />
      {open ? (
        <FormModal
          title="批量审核提现订单"
          fields={BATCH_FIELDS}
          submitLabel="提交"
          onSubmit={submit}
          onClose={() => setOpen(false)}
        />
      ) : null}
    </>
  );
}

/** 订单列表默认展示的列（后端回的是全字段，这里只排顺序）。 */
const ORDER_COLUMNS = [
  'id',
  'order_no',
  'user_id',
  'platform_amount',
  'fiat_amount',
  'currency',
  'method',
  'status',
  'payout_status',
  'created_at',
];

const BATCH_FIELDS: Field[] = [
  {
    name: 'ids',
    label: '订单 ID',
    type: 'lines',
    required: true,
    placeholder: '每行一个',
    hint: '取自提现订单列表的 id 列（hashid），每行一个；已处理的订单会被后端跳过，不做任何事',
  },
  {
    name: 'action',
    label: '动作',
    type: 'select',
    required: true,
    options: [
      { value: 'approve', label: 'approve 通过' },
      { value: 'reject', label: 'reject 驳回（逐笔退款）' },
    ],
    hint: '只有这两个值（批量端点不收二次确认 confirm）',
  },
  { name: 'note', label: '审核备注', type: 'textarea', hint: '写进每笔订单的 review_note' },
];
