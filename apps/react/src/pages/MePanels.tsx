/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * `Me.tsx` 底部两块**自足**卡片（导出我的数据 / 注销账号）—— 只为把 `Me.tsx` 压回 500 行以内而拆。
 *
 * ⚠ 它们**不是**可复用组件：各被 `Me` 用一次、props 为空，别往 `components/` 里搬。
 * 两块都在自己的模块里自取 `useAuth()` / `useNavigate()`，所以不需要 `Me` 透传任何东西；
 * 语言订阅仍由 `Me` 的 `useI18n()` 负责 —— 父组件重渲染时子组件跟着重渲染，
 * 这里**不必**再订阅一次（`msgText` 在渲染期求值，跟得上语言）。
 */
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../lib/api.ts';
import { deleteErrorMessage, deleteUnknownMessage, deleteVerdict } from '../lib/accountDeletion.ts';
import { useAuth } from '../lib/auth.tsx';
import { dt } from '../lib/datetime.ts';
import { exportBlob, exportCounts, exportName, saveBlob } from '../lib/exportData.ts';
import { msgText, type Msg } from '../lib/message.ts';
import { t } from '../i18n/index.ts';

/**
 * 导出我的数据（GDPR）。`/user/export-data` 回的**是普通信封**（不是文件），
 * 所以这里取到 JSON 后自己捏 Blob 落盘 —— 别照搬 admin 树那条「按 content-type 分流」的附件链路。
 * 屏幕上的时刻与计数全部来自**服务端回包**（`exported_at` 与四类明细长度），本机时钟不进这句话。
 */
export function ExportPanel() {
  const [expBusy, setExpBusy] = useState(false);
  const [expMsg, setExpMsg] = useState<Msg | null>(null);

  const doExport = async () => {
    if (expBusy) return;
    setExpBusy(true);
    setExpMsg(null);
    try {
      const data = await api.exportData();
      const name = exportName(data.exported_at);
      saveBlob(exportBlob(data), name);
      setExpMsg({
        ok: true,
        key: 'me.exported',
        params: { name, time: dt(data.exported_at), counts: exportCounts(data) },
      });
    } catch (e) {
      setExpMsg({ ok: false, err: e });
    } finally {
      setExpBusy(false);
    }
  };

  return (
    <section className="stack" style={{ marginTop: 32 }} aria-label={t('me.export_title')}>
      <p className="label">{t('me.export_title')}</p>
      <div className="card card--flat">
        <p className="h3" style={{ margin: 0 }}>
          {t('me.export_card_title')}
        </p>
        <p className="small muted" style={{ margin: '10px 0 0', maxWidth: '62ch' }}>
          {/*
            这一段被切成 6 段只为**保住 HEAD 的两处 <b> 加粗边界**：`t()` 只能回字符串，
            回不了 React 元素，所以加粗只能由调用点包。⚠ 相邻表达式之间的纯空白行 JSX 会整段丢掉，
            所以这 6 段渲染出来是**连着**的，与 HEAD 逐字相同。
          */}
          {/*
            段间空格**显式写出来**（`{' '}`）而不是靠 JSX 折行规则：
            HEAD 里**两行都有内容**时换行+缩进折成**一个空格**（`…提现，\n以及已绑定…` ⇒ 有一个空格）；
            而**行尾贴着标签**时那截空白落在文本节点尾部，JSX 直接 trim 掉、**不留空格**
            （`…；\n<b>游戏币…` ⇒ 没有空格）。两处都不是"相邻表达式自动拼接"的直觉能担保的，
            `verify-render.mjs` 把 HEAD 与现树各渲染一遍逐字比过（本行结论就是它给的读数）。
          */}
          {t('me.export_desc_1')}
          {' '}
          {t('me.export_desc_2')}
          <b>{t('me.export_desc_3')}</b>
          {t('me.export_desc_4')}
          <b>{t('me.export_desc_5')}</b>
          {t('me.export_desc_6')}
        </p>

        {expMsg && (
          <p
            className={expMsg.ok ? 'small' : 'err'}
            role={expMsg.ok ? 'status' : 'alert'}
            style={{ marginTop: 12 }}
          >
            {msgText(expMsg, 'me.export_failed')}
          </p>
        )}

        <button
          type="button"
          className="btn btn--sm"
          style={{ marginTop: 16 }}
          disabled={expBusy}
          onClick={doExport}
        >
          {expBusy ? t('me.exporting') : t('me.download_json')}
        </button>
      </div>
    </section>
  );
}

/** 注销账号。成功不以「请求发出去了」为准：`api.accountGone()` 回读确认真取不到了才登出。 */
export function DeletePanel() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const [delOpen, setDelOpen] = useState(false);
  const [delPw, setDelPw] = useState('');
  const [delYes, setDelYes] = useState('');
  const [delBusy, setDelBusy] = useState(false);
  // ⚠ 存**输入**不存翻好的文案：三个 helper 都留到渲染期再调（理由同 msgText）
  const [delError, setDelError] = useState<
    | { kind: 'submit'; err: unknown }
    | { kind: 'check'; err: unknown }
    | { kind: 'verdict'; gone: boolean }
    | null
  >(null);

  const cancelDelete = () => {
    setDelPw('');
    setDelYes('');
    setDelError(null);
    setDelOpen(false);
  };

  const submitDelete = async () => {
    if (delBusy) return;
    setDelBusy(true);
    setDelError(null);
    try {
      await api.deleteAccount(delPw, delYes);
    } catch (e) {
      setDelBusy(false);
      setDelError({ kind: 'submit', err: e });
      return;
    }
    // 成功不以「请求发出去了」为准：回读确认账号真的取不到
    let gone: boolean;
    try {
      gone = await api.accountGone();
    } catch (e) {
      setDelBusy(false);
      setDelError({ kind: 'check', err: e });
      return;
    }
    setDelBusy(false);
    const verdict = deleteVerdict(gone);
    if (!verdict.ok) {
      setDelError({ kind: 'verdict', gone });
      return;
    }
    setDelPw('');
    setDelYes('');
    logout();
    navigate('/');
  };

  return (
    <section className="stack" style={{ marginTop: 32 }} aria-label={t('me.delete_account')}>
      <p className="label">{t('me.delete_account')}</p>
      <div className="card card--flat">
        <div className="between">
          <p className="h3" style={{ margin: 0 }}>
            {t('me.delete_account')}
          </p>
          <span className="pill pill--orange">{t('app.this_action_cannot_be_undone')}</span>
        </div>
        <p className="small muted" style={{ margin: '10px 0 0', maxWidth: '62ch' }}>
          {t('me.delete_desc')}
        </p>

        {delError && (
          <p className="err" role="alert" style={{ marginTop: 14 }}>
            {delError.kind === 'submit'
              ? deleteErrorMessage(delError.err)
              : delError.kind === 'check'
                ? deleteUnknownMessage(delError.err)
                : deleteVerdict(delError.gone).message}
          </p>
        )}

        {delOpen ? (
          <div className="stack" style={{ marginTop: 16, maxWidth: 380 }}>
            <label className="field">
              <span>{t('me.current_password')}</span>
              <input
                className="input"
                type="password"
                autoComplete="current-password"
                placeholder={t('me.current_password_placeholder')}
                value={delPw}
                onChange={(e) => {
                  setDelPw(e.target.value);
                  setDelError(null);
                }}
              />
            </label>
            <label className="field">
              <span>{t('me.confirm_delete_label')}</span>
              <input
                className="input mono"
                autoComplete="off"
                placeholder="yes"
                value={delYes}
                onChange={(e) => {
                  setDelYes(e.target.value);
                  setDelError(null);
                }}
              />
            </label>
            <div className="row" style={{ gap: 10 }}>
              <button
                type="button"
                className="btn btn--sm"
                disabled={delBusy}
                onClick={submitDelete}
              >
                {delBusy ? t('me.deleting') : t('me.confirm_delete')}
              </button>
              <button
                type="button"
                className="btn btn--sm"
                disabled={delBusy}
                onClick={cancelDelete}
              >
                {t('app.cancel')}
              </button>
            </div>
          </div>
        ) : (
          <button
            type="button"
            className="btn btn--sm"
            style={{ marginTop: 16 }}
            onClick={() => {
              setDelError(null);
              setDelOpen(true);
            }}
          >
            {t('me.delete_account')}
          </button>
        )}
      </div>
    </section>
  );
}
