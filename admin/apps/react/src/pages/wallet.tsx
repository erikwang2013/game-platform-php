/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 平台用户钱包（**只读**）：钱包卡 + 流水表，外加把两者挂进详情弹框的 `PlatformUserDetail`。
 *
 * 两块数据都不新增端点：
 * - 钱包卡吃 `GET /admin/v1/platform/user/{hashid}` 回包里的 `data.wallet`
 *   （PlatformUserController::detail 用 `User::with('wallet')` 带出来的 `game_user_wallet` 行）
 * - 流水表走 `GET /admin/v1/platform/user/{hashid}/transactions`（同控制器 ::transactions）
 *
 * **这一屏不许长出任何改余额的控件**：本轮只要「只读展示 + 看流水」。
 * 冻结/解冻在风控页（RiskUserController），不在这棵树这一屏 —— 摆上来就是第二条改钱的路。
 *
 * 金额一律是**服务端算好的 bcmath 字符串**，前端只做呈现：不求和、不取差、不 parseFloat
 * （所以卡上没有「净收入 = 累计收入 − 累计支出」这种字段：那是前端算钱）。
 */
import { AutoView, asRows } from '../components/AutoView';
import { DataTable, type Column, type Row } from '../components/DataTable';
import { Card, ErrorNote, Loading, Modal, Pager, Stat, type Tone } from '../components/ui';
import { t, useI18n, type MessageKey } from '../i18n/index.ts';
import { amountClass, dash } from '../lib/format';
import { useApi, usePagedApi } from '../lib/hooks';
import { txLabel } from '../lib/labels.ts';
import { totalOf } from '../lib/paging';

/**
 * 钱包卡的四个金额。`balance` 可用 / `frozen_balance` 冻结（风控锁定中，用户动不了）
 * / `total_earned` 累计收入 / `total_spent` 累计支出。
 * 标签用 `f.*`（字段描述那一族）：与表头、`AutoView` 的统计块走**同一条兜底链**
 * （`fieldLabelKey`），以后往 en.fields.ts 补一条就自动生效，不会有第二处清单要同步。
 */
const WALLET_STATS: { key: string; label: MessageKey; tone?: Tone }[] = [
  { key: 'balance', label: 'f.balance', tone: 'primary' },
  { key: 'frozen_balance', label: 'f.frozen_balance', tone: 'amber' },
  { key: 'total_earned', label: 'f.total_earned', tone: 'success' },
  { key: 'total_spent', label: 'f.total_spent' },
];

/**
 * 流水表的列。**只列后端确实回的字段**（PlatformUserController::transactions 的 items 形状），
 * 顺序即列序；`ref_type` / `ref_id` 是关联单据，不进列（单据类型没有值域可展示，摆出来是一列裸 hashid）。
 */
const TX_COLUMNS: { key: string; label: MessageKey; align?: 'right' }[] = [
  { key: 'type', label: 'f.type' },
  { key: 'amount', label: 'f.amount', align: 'right' },
  { key: 'balance_after', label: 'f.balance_after', align: 'right' },
  { key: 'remark', label: 'f.remark' },
  { key: 'created_at', label: 'f.created_at' },
];

const COLUMNS: Column[] = TX_COLUMNS.map((spec) => {
  // 类型列：`txLabel` 把后端的 type 映射成当前语言的文案（未知类型原样透出，见 lib/labels.ts）
  if (spec.key === 'type') return { ...spec, render: (row: Row) => txLabel(String(row.type ?? '')) };
  // 金额列：正负分色。**只看字符串**（amountClass），不经 float
  if (spec.key === 'amount') {
    return { ...spec, render: (row: Row) => <span className={amountClass(row.amount)}>{dash(row.amount)}</span> };
  }
  return spec;
});

/** 详情回包里的钱包块。后端在用户**没有钱包行**时不返回这个键（`if ($user->wallet)`），
 *  故这里回 null 而不是空对象 —— 调用方要能区分「没有钱包」与「钱包里都是 0」。 */
function walletOf(detail: unknown): Row | null {
  const value = detail && typeof detail === 'object' && !Array.isArray(detail) ? (detail as Row).wallet : null;
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Row) : null;
}

/**
 * 钱包卡：四个金额。没有钱包行时说清楚，不摆四个「—」让人以为是加载失败。
 *
 * 用 `grid-2` 而不是 `grid-4`：金额是**不可断**的串（bcmath 8 位小数，`1234.56780000` 就有 13 字符），
 * 而 `.stat-v` 带 `overflow-wrap: anywhere` —— 四列时每个格子只有约 140px，
 * 会把 `1234.56780000` 渲染成「1234.567800」换行「0」，读起来像两个数（真机截图实测）。
 * 两列时格子宽度翻倍，20 位的精度串也放得下。**别改回 grid-4**，除非金额改成截断显示。
 */
function WalletCard({ wallet }: { wallet: Row | null }) {
  return (
    <Card title={t('f.wallet')}>
      {wallet ? (
        <div className="grid grid-2">
          {WALLET_STATS.map((spec) => (
            <Stat key={spec.key} label={t(spec.label)} value={dash(wallet[spec.key])} tone={spec.tone} />
          ))}
        </div>
      ) : (
        <p className="muted">{t('wallet.missing')}</p>
      )}
    </Card>
  );
}

/**
 * 流水：表格 + 分页条。端点是按 `created_at desc, id desc` 排的（同秒多笔是常态，见控制器注释），
 * 分页参数走 `usePagedApi` 的 `pageQuery` —— 三个别名一起发，这个端点读的是其中的 `per_page`。
 * 每页 20 与后端默认值同值（PAGE_SIZE），所以页数按 PAGE_SIZE 算是对的。
 */
function TransactionList({ id }: { id: string }) {
  const { data, loading, error, reload, page, setPage, pageSize } = usePagedApi<unknown>(
    `/admin/v1/platform/user/${id}/transactions`,
  );
  const rows = asRows(data) ?? [];
  const total = totalOf(data, rows.length);

  return (
    <>
      <DataTable columns={COLUMNS} rows={rows} loading={loading} error={error} onRetry={reload} />
      {/* 只有一页不画分页条；`page > 1` 也画，免得翻到后一页时把入口弄丢（同 RowBrowser） */}
      {total > pageSize || page > 1 ? (
        <Pager page={page} pages={Math.max(1, Math.ceil(total / pageSize))} total={total} onJump={setPage} />
      ) : null}
    </>
  );
}

/**
 * 平台用户详情弹框：钱包卡 + 用户字段（兜底渲染）+ 流水。
 *
 * 用 `Modal` 而不是 `DetailModal`：`DetailModal` 固定渲染 `AutoView`，而这里要多挂两块
 * 依赖同一份详情回包的内容（钱包卡要吃 `data.wallet`），再包一层就得多开一次同样的请求。
 */
export function PlatformUserDetail({ id, onClose }: { id: string; onClose: () => void }) {
  // 本子树自己订阅语言：Shell 重绘不会带动弹框里的 t()（见 i18n/index.ts 的 useI18n 说明）
  useI18n();
  const { data, loading, error, reload } = useApi<unknown>(`/admin/v1/platform/user/${id}`);

  // 钱包由 WalletCard 专门渲染 ⇒ 从兜底视图里摘掉这个键，
  // 否则同一组数字会在同一个弹框里出现两次（一张卡一个版本）。
  const detail = data && typeof data === 'object' && !Array.isArray(data) ? (data as Row) : null;
  const rest: Row = { ...detail };
  delete rest.wallet;
  const username = String(detail?.username ?? '').trim();

  return (
    <Modal title={`${t('common.detail')} ${username === '' ? id : username}`} onClose={onClose}>
      {loading ? (
        <Loading rows={5} />
      ) : error ? (
        <ErrorNote message={error} onRetry={reload} />
      ) : (
        <div className="stack">
          <WalletCard wallet={walletOf(data)} />
          <AutoView data={rest} />
          <Card title={t('wallet.transactions')}>
            <TransactionList id={id} />
          </Card>
        </div>
      )}
    </Modal>
  );
}
