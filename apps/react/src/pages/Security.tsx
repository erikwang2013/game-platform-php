/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';
import { SecretBox } from '../components/SecretBox.tsx';

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
  const status = useAsync(() => api.twoFactorStatus(), []);

  // 阶段：idle=只看状态；setup=已出密钥待验证；codes=刚启用，正在展示一次性备用码
  const [stage, setStage] = useState<'idle' | 'setup' | 'codes'>('idle');
  const [setup, setSetup] = useState<{ secret: string; qr_url: string } | null>(null);
  const [codes, setCodes] = useState<string[] | null>(null);
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);

  // 关闭 2FA 要密码 + TOTP 双因子
  const [offOpen, setOffOpen] = useState(false);
  const [pw, setPw] = useState('');
  const [offCode, setOffCode] = useState('');

  const err = (e: unknown, fallback: string) =>
    setMsg({ ok: false, text: e instanceof ApiError ? e.message : fallback });

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
      err(e, '生成密钥失败，请稍后重试');
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
      err(e, '启用失败，请稍后重试');
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
      setMsg({ ok: true, text: '两步验证已关闭' });
      status.reload();
    } catch (e) {
      err(e, '关闭失败，请稍后重试');
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
      <p className="label">账号安全</p>
      <h1 className="h1">
        两步验证
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
                备用码
              </p>
              <span className="pill pill--orange">仅显示这一次</span>
            </div>
            <p className="small muted" style={{ margin: '10px 0 0', maxWidth: '62ch' }}>
              验证器丢失时用其中任意一条登录；每条用掉即作废。请立刻抄到离线的地方保存——
              离开本页后服务端不再展示明文，遗失只能重新生成。
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
                复制全部
              </button>
              <button
                type="button"
                className="btn btn--sm btn--primary"
                onClick={() => {
                  setCodes(null);
                  setStage('idle');
                  setMsg({ ok: true, text: '两步验证已开启' });
                }}
              >
                我已保存，完成
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
                    {status.data.enabled ? '已开启' : '未开启'}
                  </p>
                  <span className={`pill ${status.data.enabled ? 'pill--orange' : 'pill--plain'}`}>
                    {status.data.enabled ? '受保护' : '仅密码'}
                  </span>
                </div>
                <p className="small muted" style={{ margin: '10px 0 0', maxWidth: '62ch' }}>
                  开启后，登录除密码外还需输入验证器上的 6 位动态码；验证器丢失时可用备用码登录。
                </p>

                {msg && (
                  <p
                    className={msg.ok ? 'small' : 'err'}
                    role={msg.ok ? 'status' : 'alert'}
                    style={{ margin: '12px 0 0' }}
                  >
                    {msg.text}
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
                  {busy ? '生成中…' : '开启两步验证'}
                </button>
              )}

              {stage === 'setup' && setup && (
                <div className="stack">
                  <p className="label" style={{ margin: 0 }}>
                    第 1 步 · 把密钥加进验证器
                  </p>
                  <p className="small muted" style={{ margin: 0, maxWidth: '62ch' }}>
                    在验证器 App（Google Authenticator / Microsoft Authenticator / 1Password 等）里
                    选「手动输入密钥」，粘贴下面这串，账号名填你的用户名。
                    本页不生成二维码图片：二维码要走第三方服务渲染，等于把你的密钥发给外部主机。
                  </p>

                  <SecretBox label="密钥（Base32）" value={setup.secret} />
                  <SecretBox label="otpauth 链接（部分验证器支持粘贴）" value={setup.qr_url} />

                  <p className="label" style={{ margin: '8px 0 0' }}>
                    第 2 步 · 输入验证器当前显示的 6 位动态码
                  </p>
                  <label className="field">
                    <span>动态验证码</span>
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
                      {busy ? '启用中…' : '确认启用'}
                    </button>
                    <button
                      type="button"
                      className="btn btn--sm"
                      disabled={busy}
                      onClick={cancelSetup}
                    >
                      取消
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
                  关闭两步验证
                </button>
              ) : (
                <div className="stack" style={{ maxWidth: 380 }}>
                  <p className="label" style={{ margin: 0 }}>
                    关闭需同时验证密码与动态码
                  </p>
                  <label className="field">
                    <span>当前密码</span>
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
                    <span>动态验证码</span>
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
                      {busy ? '关闭中…' : '确认关闭'}
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
                      取消
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* 邮箱/手机验证已撤下：后端不建立归属、结果也读不回（见本页顶部注释） */}
          <p className="small muted">
            回到 <Link to="/me">我的</Link>
          </p>
        </section>
      )}
    </>
  );
}
