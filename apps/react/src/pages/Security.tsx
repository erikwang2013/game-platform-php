/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';
import { SecretBox } from '../components/SecretBox.tsx';
import { t, type MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

/**
 * 反馈文案的**暂存形**：存「键」或「原始错误 + 兜底键」，**不存翻好的串**。
 * ⚠ 存翻好的串会把语言冻在失败那一刻（理由同 Wallet.tsx 的 Row 注释）。
 */
type Msg = { ok: boolean; key: MessageKey } | { ok: false; err: unknown; fallback: MessageKey };

const msgText = (m: Msg): string =>
  'err' in m ? (m.err instanceof ApiError ? m.err.message : t(m.fallback)) : t(m.key);

/**
 * ⚠ 本页**只有 2FA 自助**（status/setup/enable/disable 四个端点真能用）。
 * 原先还挂了「邮箱验证 / 手机验证」两块，2026-10-01 撤下，后端缺口（已核盘）：
 *  - `VerificationService::sendEmail($email,$userId)` 的 **$email 在函数体里一次都没用到**，
 *    码走 NotificationService 发到**本人站内通知** ⇒ 填任意邮箱都能过，不建立归属；
 *  - `sendSms($phone,$userId)` 的 $phone 同样未用，且**没有任何投递调用**（函数体只有 Redis 读写）
 *    却 return success ⇒ 码存了但用户永远收不到，是真死路；
 *  - `email_verified_at`/`phone_verified_at` 全仓非 vendor **恰 2 处命中、全是写入**
 *    （VerificationController.php:54/93），`/user/profile` 也不回这两个字段 ⇒ 结果读不回来。
 * 摆一个「验证邮箱/手机」的界面 = 给用户一个假的信任信号，比不做更糟。恢复了后端再恢复本页。
 */

export function Security() {
  const { t } = useI18n();
  const status = useAsync(() => api.twoFactorStatus(), []);

  // 阶段：idle=只看状态；setup=已出密钥待验证；codes=刚启用，正在展示一次性备用码
  const [stage, setStage] = useState<'idle' | 'setup' | 'codes'>('idle');
  const [setup, setSetup] = useState<{ secret: string; qr_url: string } | null>(null);
  const [codes, setCodes] = useState<string[] | null>(null);
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<Msg | null>(null);

  // 关闭 2FA 要密码 + TOTP 双因子
  const [offOpen, setOffOpen] = useState(false);
  const [pw, setPw] = useState('');
  const [offCode, setOffCode] = useState('');

  const err = (e: unknown, fallback: MessageKey) => setMsg({ ok: false, err: e, fallback });

  const startSetup = async () => {
    if (busy) return;
    setBusy(true);
    setMsg(null);
    try {
      // 服务端每次调用都会删掉旧的未启用密钥，所以这里只能保留最新一份
      const d = await api.twoFactorSetup();
      setSetup(d);
      setCode('');
      setStage('setup');
    } catch (e) {
      err(e, 'security.setup_failed');
    } finally {
      setBusy(false);
    }
  };

  const confirmEnable = async () => {
    if (busy) return;
    setBusy(true);
    setMsg(null);
    try {
      const d = await api.twoFactorEnable(code.trim());
      setCodes(d.backup_codes);
      setCode('');
      setStage('codes');
      status.reload();
    } catch (e) {
      err(e, 'security.enable_failed');
    } finally {
      setBusy(false);
    }
  };

  const confirmDisable = async () => {
    if (busy) return;
    setBusy(true);
    setMsg(null);
    try {
      await api.twoFactorDisable(pw, offCode.trim());
      setPw('');
      setOffCode('');
      setOffOpen(false);
      setMsg({ ok: true, key: 'security.2fa_disabled' });
      status.reload();
    } catch (e) {
      err(e, 'security.disable_failed');
    } finally {
      setBusy(false);
    }
  };

  const cancelSetup = () => {
    setStage('idle');
    setSetup(null);
    setCode('');
    setMsg(null);
  };

  return (
    <>
      <p className="label">{t('security.title')}</p>
      <h1 className="h1">
        {t('me.two_factor')}
        <span style={{ color: 'var(--orange)' }}>.</span>
      </h1>

      {/*
        刚启用：备用码只在这里显示一次（服务端落库后不再回传明文），
        所以这一屏优先于状态屏，用户不点「我已保存」不离开。
      */}
      {stage === 'codes' && codes ? (
        <section className="stack" style={{ marginTop: 24 }}>
          <div className="card card--flat">
            <div className="between">
              <p className="h3" style={{ margin: 0 }}>
                {t('security.backup_codes')}
              </p>
              <span className="pill pill--orange">{t('security.shown_once')}</span>
            </div>
            <p className="small muted" style={{ margin: '10px 0 0', maxWidth: '62ch' }}>
              {t('security.codes_hint_1')} {t('security.codes_hint_2')}
            </p>

            <div className="list" style={{ marginTop: 16 }}>
              {codes.map((c) => (
                <div className="li" key={c}>
                  <span className="mono">{c}</span>
                </div>
              ))}
            </div>

            <div className="row" style={{ gap: 10, marginTop: 16 }}>
              <button
                type="button"
                className="btn btn--sm"
                onClick={() => {
                  void navigator.clipboard
                    ?.writeText(codes.join('\n'))
                    .catch(() => undefined);
                }}
              >
                {t('security.copy_all')}
              </button>
              <button
                type="button"
                className="btn btn--sm btn--primary"
                onClick={() => {
                  setCodes(null);
                  setStage('idle');
                  setMsg({ ok: true, key: 'security.2fa_enabled' });
                }}
              >
                {t('security.saved_done')}
              </button>
            </div>
          </div>
        </section>
      ) : (
        <section className="stack" style={{ marginTop: 24 }}>
          <div className="card card--flat">
            {status.loading && <Loading />}
            {!status.loading && status.error && (
              <ErrorBox message={status.error} onRetry={status.reload} />
            )}

            {!status.loading && !status.error && status.data && (
              <>
                <div className="between">
                  <p className="h3" style={{ margin: 0 }}>
                    {status.data.enabled ? t('security.on') : t('security.off')}
                  </p>
                  <span className={`pill ${status.data.enabled ? 'pill--orange' : 'pill--plain'}`}>
                    {status.data.enabled ? t('security.protected') : t('security.password_only')}
                  </span>
                </div>
                <p className="small muted" style={{ margin: '10px 0 0', maxWidth: '62ch' }}>
                  {t('security.enabled_hint')}
                </p>

                {msg && (
                  <p
                    className={msg.ok ? 'small' : 'err'}
                    role={msg.ok ? 'status' : 'alert'}
                    style={{ margin: '12px 0 0' }}
                  >
                    {msgText(msg)}
                  </p>
                )}
              </>
            )}
          </div>

          {/* ---- 未开启：走 setup → enable ---- */}
          {!status.loading && !status.error && status.data && !status.data.enabled && (
            <div className="card card--flat">
              {stage === 'idle' && (
                <button
                  type="button"
                  className="btn btn--primary"
                  disabled={busy}
                  onClick={startSetup}
                >
                  {busy ? t('security.generating') : t('security.enable')}
                </button>
              )}

              {stage === 'setup' && setup && (
                <div className="stack">
                  <p className="label" style={{ margin: 0 }}>
                    {t('security.step1')}
                  </p>
                  <p className="small muted" style={{ margin: 0, maxWidth: '62ch' }}>
                    {t('security.step1_hint_1')} {t('security.step1_hint_2')} {t('security.step1_hint_3')}
                  </p>

                  <SecretBox label={t('security.secret_label')} value={setup.secret} />
                  <SecretBox label={t('security.otpauth_label')} value={setup.qr_url} />

                  <p className="label" style={{ margin: '8px 0 0' }}>
                    {t('security.step2')}
                  </p>
                  <label className="field">
                    <span>{t('security.code_label')}</span>
                    <input
                      className="input mono"
                      name="code"
                      type="text"
                      inputMode="numeric"
                      autoComplete="one-time-code"
                      maxLength={6}
                      value={code}
                      onChange={(e) => {
                        setCode(e.target.value.replace(/\D/g, ''));
                        setMsg(null);
                      }}
                    />
                  </label>

                  <div className="row" style={{ gap: 10 }}>
                    <button
                      type="button"
                      className="btn btn--sm btn--primary"
                      disabled={busy || code.length !== 6}
                      onClick={confirmEnable}
                    >
                      {busy ? t('security.enabling') : t('security.confirm_enable')}
                    </button>
                    <button
                      type="button"
                      className="btn btn--sm"
                      disabled={busy}
                      onClick={cancelSetup}
                    >
                      {t('app.cancel')}
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ---- 已开启：关闭要密码 + 动态码 ---- */}
          {!status.loading && !status.error && status.data && status.data.enabled && (
            <div className="card card--flat">
              {!offOpen ? (
                <button
                  type="button"
                  className="btn btn--sm"
                  onClick={() => {
                    setMsg(null);
                    setOffOpen(true);
                  }}
                >
                  {t('security.disable')}
                </button>
              ) : (
                <div className="stack" style={{ maxWidth: 380 }}>
                  <p className="label" style={{ margin: 0 }}>
                    {t('security.disable_hint')}
                  </p>
                  <label className="field">
                    <span>{t('me.current_password')}</span>
                    <input
                      className="input"
                      name="password"
                      type="password"
                      autoComplete="current-password"
                      value={pw}
                      onChange={(e) => {
                        setPw(e.target.value);
                        setMsg(null);
                      }}
                    />
                  </label>
                  <label className="field">
                    <span>{t('security.code_label')}</span>
                    <input
                      className="input mono"
                      name="code"
                      type="text"
                      inputMode="numeric"
                      autoComplete="one-time-code"
                      maxLength={6}
                      value={offCode}
                      onChange={(e) => {
                        setOffCode(e.target.value.replace(/\D/g, ''));
                        setMsg(null);
                      }}
                    />
                  </label>
                  <div className="row" style={{ gap: 10 }}>
                    <button
                      type="button"
                      className="btn btn--sm"
                      disabled={busy || !pw || offCode.length !== 6}
                      onClick={confirmDisable}
                    >
                      {busy ? t('security.disabling') : t('security.confirm_disable')}
                    </button>
                    <button
                      type="button"
                      className="btn btn--sm"
                      disabled={busy}
                      onClick={() => {
                        setOffOpen(false);
                        setPw('');
                        setOffCode('');
                        setMsg(null);
                      }}
                    >
                      {t('app.cancel')}
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* 邮箱/手机验证已撤下：后端不建立归属、结果也读不回（见本页顶部注释） */}
          <p className="small muted">
            {t('security.back_prefix')} <Link to="/me">{t('nav.me')}</Link>
          </p>
        </section>
      )}
    </>
  );
}
