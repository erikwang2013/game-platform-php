/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 风控页里两块**没有行上下文**的界面：IP 名单、团伙检测与确认。
 *
 * 为什么不挂行内按钮 —— 以控制器（唯一真值）为准：
 * - `/risk/ip/list` 的行**既没有 id 也没有原文 IP**（只有 ip_masked），而 block/whitelist/recheck
 *   收的是原文 IP（服务端自己算 sha256）⇒ 行上凑不出请求体，只能由运营粘贴标识发起。
 *   （设备页原本同病，后端补回完整 fp_hash 后已改成行内动作，见 modules.ts 的 RISK_DEVICE_CRUD。）
 * - `/risk/clusters/detect` 是全局扫描（无参数、无行），`/clusters/confirm` 的 member_ids 收的是
 *   hashid（服务端 decodeId，非法值 400），detect 结果里也不含成员 —— 两件事都没有「当前行」可挂，
 *   成员候选要去已建团伙的成员视图里取。
 *
 * 分工与 pages/funds.tsx 一致：行内动作/表单的字段描述归 modules.ts，这里只放底座表达不了的
 * 控件（粘贴标识的名单表单、检测结果表、全局确认）。金额动作一个都没有，故不涉钱。
 */
import { useState } from 'react';
import { DataTable, type Column, type Row } from '../components/DataTable';
import { FormModal } from '../components/FormModal';
import { RowBrowser } from '../components/RowBrowser';
import { ErrorNote } from '../components/ui';
import { ApiError, api, apiEnvelope } from '../lib/api';
import {
  RISK_CLUSTER_CRUD,
  RISK_CLUSTER_PANEL,
  RISK_DEVICE_CRUD,
  RISK_DEVICE_HIDE,
  RISK_IP_FLAGS,
  type FlagAction,
} from './modules';

type Notice = { text: string; tone: 'error' | 'ok' };

const reason = (cause: unknown, fallback: string): string =>
  cause instanceof ApiError ? cause.message : fallback;

/* --------------------------------- 名单动作 -------------------------------- */

/** 名单动作条：按钮 → 单字段表单 → 二次确认 → 提交 → 服务端原话 + 回读列表。 */
function FlagBar({ flags, onDone }: { flags: FlagAction[]; onDone: () => void }) {
  const [active, setActive] = useState<FlagAction | null>(null);
  const [notice, setNotice] = useState<Notice | null>(null);

  const submit = async (flag: FlagAction, body: Record<string, unknown>) => {
    const value = String(body[flag.field.name] ?? '');
    // 取消：抛错让 FormModal 原地显示（框不关，可改后重试）
    if (!window.confirm(flag.confirm(value))) throw new ApiError(0, '已取消（未提交任何变更）');
    const envelope = await apiEnvelope<unknown>(flag.path, { method: 'POST', body });
    setActive(null);
    setNotice({ text: flag.report ? flag.report(envelope, value) : envelope.message, tone: 'ok' });
    onDone();
  };

  return (
    <>
      <div className="toolbar">
        {flags.map((flag) => (
          <button
            type="button"
            className="btn"
            key={flag.label}
            onClick={() => {
              setNotice(null);
              setActive(flag);
            }}
          >
            {flag.label}
          </button>
        ))}
      </div>
      {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
      {active ? (
        <FormModal
          title={active.title}
          fields={[active.field]}
          submitLabel="提交"
          onSubmit={(body) => submit(active, body)}
          onClose={() => setActive(null)}
        />
      ) : null}
    </>
  );
}

/**
 * 设备指纹页：拉黑/解封是**行内动作**（列表回完整 fp_hash，见 RISK_DEVICE_CRUD）。
 * 拉黑是管理端 Redis 标记（TTL 30 天），`RiskService::check()` 在规则循环前对它短路 ⇒
 * 该设备的充值/提现会被**真的阻断**（不看规则是否启用）。列表的 blocked 列实时查 Redis，
 * 动作成功后 RowBrowser 自己回读列表，故不必再像粘贴表单那样重挂整表。
 */
export function RiskDevices({ path }: { path: string }) {
  return (
    <RowBrowser
      path={path}
      preferred={['fp_masked', 'ip_c_segment', 'account_count', 'blocked', 'first_seen_at', 'last_seen_at']}
      hide={RISK_DEVICE_HIDE}
      crud={RISK_DEVICE_CRUD}
    />
  );
}

/** IP 信誉页：名单写入是同一行的覆盖，且没有解封端点（见 modules.ts 里 RISK_IP_FLAGS 的说明）。 */
export function RiskIps({ path }: { path: string }) {
  const [nonce, setNonce] = useState(0);

  return (
    <>
      <FlagBar flags={RISK_IP_FLAGS} onDone={() => setNonce((n) => n + 1)} />
      <RowBrowser
        key={nonce}
        path={path}
        preferred={['ip_masked', 'reputation_score', 'source', 'hit_count', 'first_seen_at', 'last_seen_at']}
      />
    </>
  );
}

/* --------------------------------- 关联团伙 -------------------------------- */

/** 检测结果的列（列名与 detect 返回的键一致）。 */
const candidateColumns = (onConfirm: (row: Row) => void): Column[] => [
  { key: 'type', label: '类型' },
  { key: 'fingerprint_masked', label: '指纹（前 8 位）' },
  { key: 'user_count', label: '账户数', align: 'right' },
  {
    key: '__actions',
    label: '操作',
    render: (row) => (
      <span className="rowact">
        <button type="button" className="btn btn-sm" onClick={() => onConfirm(row)}>
          确认团伙
        </button>
      </span>
    ),
  },
];

/** 候选 → 预填表单的值（含一个可直接用的默认名，省得每条都手打）。 */
const candidateName = (row: Row): string => {
  const who = row.type === 'same_device' ? '同设备' : row.type === 'same_ip' ? '同 IP' : String(row.type ?? '');
  const masked = String(row.fingerprint_masked ?? '').replace(/\*+$/, '');
  return `${who}团伙 ${masked}`;
};

/**
 * 团伙页：检测（候选表，不落库）+ 确认（写入 game_risk_cluster）+ 已确认列表。
 * detect 是全局扫描，返回的是**快照**且带完整 fingerprint —— 确认表单直接预填该候选，
 * 人只需补个名字。确认成功后候选表清空（避免对同一条反复写入造成重复团伙）。
 */
export function RiskClusters({ path }: { path: string }) {
  const [candidates, setCandidates] = useState<Row[] | null>(null);
  const [days, setDays] = useState(0);
  const [form, setForm] = useState<{ row?: Row; title: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [nonce, setNonce] = useState(0);

  const detect = async () => {
    setNotice(null);
    setBusy(true);
    try {
      const data = await api<{ window_days?: number; candidates?: Row[] }>(RISK_CLUSTER_PANEL.detect, {
        method: 'POST',
      });
      setDays(Number(data.window_days ?? 0));
      setCandidates(Array.isArray(data.candidates) ? data.candidates : []);
    } catch (cause) {
      setNotice({ text: reason(cause, '检测失败，请稍后重试'), tone: 'error' });
      return;
    } finally {
      setBusy(false);
    }
    setNotice({ text: '检测完成：候选仅供确认，未写入任何数据。', tone: 'ok' });
  };

  const submit = async (body: Record<string, unknown>) => {
    const name = String(body.name ?? '');
    const fingerprint = String(body.fingerprint ?? '');
    const members = Array.isArray(body.member_ids) ? body.member_ids.length : 0;
    const who = fingerprint === '' ? `成员 ${members} 人（按成员 hashid 列表）` : `指纹 ${fingerprint}`;
    if (!window.confirm(`确认把「${name}」写成已确认团伙？${who}。写入后可在下方列表改状态或看成员。`)) {
      throw new ApiError(0, '已取消（未写入）');
    }
    const envelope = await apiEnvelope<{ cluster?: Row }>(RISK_CLUSTER_PANEL.confirm, {
      method: 'POST',
      body,
    });
    setForm(null);
    const cluster = envelope.data?.cluster;
    setNotice({
      text: cluster
        ? `已写入团伙「${String(cluster.name)}」（${String(cluster.type)}，成员 ${String(cluster.user_count)} 人）`
        : envelope.message,
      tone: 'ok',
    });
    // 候选是检测当时的快照：确认过的这条再点一次会写出第二个团伙行，故清掉，要接着确认就重新检测
    setCandidates(null);
    setNonce((n) => n + 1);
  };

  return (
    <>
      <div className="toolbar">
        <button
          type="button"
          className="btn"
          disabled={busy}
          onClick={() => {
            setNotice(null);
            setForm({ title: '手动确认团伙' });
          }}
        >
          手动确认团伙
        </button>
        <button type="button" className="btn" disabled={busy} onClick={() => void detect()}>
          {busy ? '检测中…' : '聚类检测'}
        </button>
      </div>
      {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
      {candidates ? (
        <>
          <p className="muted">
            近 {days} 天候选（同 IP ≥ 5 账户 / 同设备 ≥ 3 账户）：确认才会落库，检测本身不写数据。
          </p>
          <DataTable
            columns={candidateColumns((row) =>
              setForm({
                title: '确认团伙（预填自检测结果）',
                row: {
                  type: row.type,
                  fingerprint: row.fingerprint,
                  user_count: row.user_count,
                  name: candidateName(row),
                },
              }),
            )}
            rows={candidates}
          />
        </>
      ) : null}
      <RowBrowser
        key={nonce}
        path={path}
        preferred={['id', 'name', 'type', 'fingerprint_masked', 'user_count', 'status', 'updated_at']}
        crud={RISK_CLUSTER_CRUD}
      />
      {form ? (
        <FormModal
          // 预填值来自检测快照，key 钉住它：换一条候选就重挂，草稿不会串
          key={String(form.row?.fingerprint ?? 'manual')}
          title={form.title}
          fields={RISK_CLUSTER_PANEL.fields}
          row={form.row}
          // 预填自候选 ⇒ 必须全量提交，否则「一个字段都没改」会发出空请求体（见 RISK_CLUSTER_PANEL 注释）
          fullEdit={RISK_CLUSTER_PANEL.fullEdit}
          submitLabel="写入"
          onSubmit={submit}
          onClose={() => setForm(null)}
        />
      ) : null}
    </>
  );
}
