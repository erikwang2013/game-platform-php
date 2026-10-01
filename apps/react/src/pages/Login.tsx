/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../lib/api.ts';
import { useAuth } from '../lib/auth.tsx';
import { useCaptcha } from '../lib/useCaptcha.tsx';

/** 与后端 AuthController::PASSWORD_RULE 对齐；HTML pattern 隐含整串匹配，故省去 ^$ */
const REGISTER_PASSWORD_PATTERN = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).+';
const REGISTER_PASSWORD_TITLE = '8-32 位，需含大小写字母和数字';

/**
 * 登录第二步只接受两种长度：6 位 TOTP、10 位备用码（服务端 between:6,10）。
 * 7–9 位两种都不是、服务端必回 422，所以这里直接不让提交——本地先拦，
 * 免得用户对着一个注定失败的码反复点。逻辑同 TwoFactorController::verify 的分支。
 */
const isTwoFactorCode = (v: string) => v.length === 6 || v.length === 10;

/** 邀请链接 `?code=` 的取值：服务端 `share_code` 收 `nullable|string|max:12`，此处同口径 */
const readInviteCode = (search: string) => {
  const v = new URLSearchParams(search).get('code')?.trim() ?? '';
  return v.length > 0 && v.length <= 12 ? v : '';
};

export function Login() {
  const { user, login, complete2fa, register } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  // 带邀请码进来 = 朋友点的落地页：直接落在注册页签，邀请码预填好
  const [mode, setMode] = useState<'login' | 'register'>(() =>
    readInviteCode(location.search) ? 'register' : 'login',
  );
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [email, setEmail] = useState('');
  const [invite, setInvite] = useState(() => readInviteCode(location.search));
  const [err, setErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  // 非 null 表示密码已通过、账号开了 2FA：此步只差一个 TOTP 码
  const [pending2fa, setPending2fa] = useState<string | null>(null);
  const [code, setCode] = useState('');
  const cap = useCaptcha();

  /**
   * 落地页点击上报。这一步才是「邀请链接」在本树内成立的另一半：没有它，
   * 分享短码只被生成、从不被点击（clicks 恒 0），页面就只剩一个假仪式。
   *
   * 匿名公开路由，失败**必须静默**（网络抖动/码已失效都不该挡住注册）；
   * ref 去重是因为 StrictMode 会双跑 effect，否则开发态一次打开算两次点击。
   */
  const reported = useRef(false);
  useEffect(() => {
    const c = readInviteCode(location.search);
    if (!c || reported.current) return;
    reported.current = true;
    api.visitShare(c).catch(() => {});
  }, [location.search]);

  if (user) return <Navigate to="/" replace />;

  const done = () => {
    const from = (location.state as { from?: string } | null)?.from;
    navigate(from ?? '/', { replace: true });
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    setErr(null);
    // 先过本地必填校验，再弹验证码框，免得空表单先弹一层
    if (!username.trim() || !password || (mode === 'register' && !email.trim())) return;
    const proof = await cap.ask();
    if (!proof) return; // 用户取消
    setBusy(true);
    try {
      if (mode === 'register') {
        // 邀请码原样透传：服务端自己校验（无效码静默忽略，不报错），客户端不替它决定
        await register(username.trim(), password, email.trim(), proof, invite.trim() || undefined);
        done();
        return;
      }
      const r = await login(username.trim(), password, proof);
      if (r.status === '2fa') {
        // 不开 2FA 的账号这一步就结束了；开了的要接着输码，票据留到第二步用
        setPending2fa(r.pendingToken);
        setCode('');
        return;
      }
      done();
    } catch (e2) {
      // 失败（含 422 验证码错误）：框已关，服务端 message 落在表单错误位，下次提交重取
      setErr(e2 instanceof Error ? e2.message : '操作失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  const submitCode = async (e: FormEvent) => {
    e.preventDefault();
    if (busy || !pending2fa) return;
    setErr(null);
    if (code.trim().length === 0) return;
    setBusy(true);
    try {
      await complete2fa(pending2fa, code.trim());
      done();
    } catch (e2) {
      // 票据 10 分钟过期 / 码错误：都留在本步，由用户决定重输还是回上一步
      setErr(e2 instanceof Error ? e2.message : '验证失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  const switchTo = (m: 'login' | 'register') => {
    setMode(m);
    setErr(null);
    setPending2fa(null);
    setCode('');
  };

  const backToPassword = () => {
    setErr(null);
    setPending2fa(null);
    setCode('');
    setPassword('');
  };

  // 只在「链接带来的码还在、没被用户改掉」时提示，改用别人的码后提示自然消失
  const fromInviteLink =
    mode === 'register' && !!invite && invite === readInviteCode(location.search);

  return (
    <main className="shell">
      <div className="shell--split" style={{ paddingTop: 32, paddingBottom: 48 }}>
        <section className="stack">
          {/* 吉祥物小骰（Dicey）：纯装饰，语义由下面的文案承载；随 BASE_URL 走子路径部署 */}
          <img
            className="login-mascot"
            src={`${import.meta.env.BASE_URL}mascot.svg`}
            alt=""
            aria-hidden="true"
          />
          <p className="label">C 端平台</p>
          <h1 className="h1">
            玩你喜欢的
            <br />
            <em style={{ fontStyle: 'normal', background: 'var(--yellow)' }}>每一款游戏</em>
          </h1>
          <p className="muted" style={{ maxWidth: '42ch' }}>
            一个账号，畅玩全平台游戏。统一钱包、实时结算、多币种支持。
          </p>
          <p className="small muted">
            还没有账号？在右侧切换到「注册」，30 秒即可开始。
          </p>
        </section>

        <section className="card" aria-label="账号">
          {pending2fa ? (
            <form onSubmit={submitCode} className="stack" aria-label="两步验证">
              <p className="label" style={{ margin: 0 }}>
                两步验证
              </p>
              <p className="small muted" style={{ margin: 0 }}>
                账号 {username.trim()} 已开启两步验证。请输入验证器上的 6 位动态码；
                验证器丢失时可改用一条 10 位备用码。
              </p>

              {err && <p className="err" role="alert">{err}</p>}

              <label className="field">
                <span>动态码 / 备用码</span>
                <input
                  className="input mono"
                  name="code"
                  type="text"
                  autoComplete="one-time-code"
                  maxLength={10}
                  required
                  autoFocus
                  value={code}
                  onChange={(e) => {
                    // 备用码是字母数字混合（如 aB3xY9kLm2），不能只留数字、也不能卡在 6 位，
                    // 否则用户按提示输备用码时会被客户端先截断成无效串。服务端收 between:6,10。
                    setCode(e.target.value.replace(/[^A-Za-z0-9]/g, ''));
                    setErr(null);
                  }}
                />
              </label>

              <button
                type="submit"
                className="btn btn--primary btn--block"
                disabled={busy || !isTwoFactorCode(code)}
              >
                {busy && <span className="spin" aria-hidden="true" />}
                验证并登录
              </button>

              <button type="button" className="btn btn--sm" disabled={busy} onClick={backToPassword}>
                返回上一步
              </button>
            </form>
          ) : (
            <>
              <div className="tabs" role="tablist">
                <button
                  type="button"
                  role="tab"
                  aria-selected={mode === 'login'}
                  className={`tabs__b${mode === 'login' ? ' is-on' : ''}`}
                  onClick={() => switchTo('login')}
                >
                  登录
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-selected={mode === 'register'}
                  className={`tabs__b${mode === 'register' ? ' is-on' : ''}`}
                  onClick={() => switchTo('register')}
                >
                  注册
                </button>
              </div>

              <form onSubmit={submit} className="stack" style={{ marginTop: 18 }}>
                {fromInviteLink && (
                  <p className="small muted" role="status" style={{ margin: 0 }}>
                    你正在通过邀请链接注册，邀请码已填好。
                  </p>
                )}

                {err && <p className="err" role="alert">{err}</p>}

                <label className="field">
                  <span>用户名</span>
                  <input
                    className="input"
                    name="username"
                    autoComplete="username"
                    required
                    minLength={3}
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                  />
                </label>

                {mode === 'register' && (
                  <label className="field">
                    <span>邮箱</span>
                    <input
                      className="input"
                      name="email"
                      type="email"
                      autoComplete="email"
                      required
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                    />
                  </label>
                )}

                {mode === 'register' && (
                  <label className="field">
                    <span>邀请码（选填）</span>
                    <input
                      className="input mono"
                      name="invite"
                      autoComplete="off"
                      maxLength={12}
                      placeholder={invite ? undefined : '朋友给的 8 位邀请码'}
                      value={invite}
                      onChange={(e) => setInvite(e.target.value.trim())}
                    />
                  </label>
                )}

                <label className="field">
                  <span>密码</span>
                  <input
                    className="input"
                    name="password"
                    type="password"
                    autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
                    required
                    minLength={mode === 'login' ? 6 : 8}
                    maxLength={mode === 'login' ? undefined : 32}
                    pattern={mode === 'login' ? undefined : REGISTER_PASSWORD_PATTERN}
                    title={mode === 'login' ? undefined : REGISTER_PASSWORD_TITLE}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                </label>

                <button type="submit" className="btn btn--primary btn--block" disabled={busy}>
                  {busy && <span className="spin" aria-hidden="true" />}
                  {mode === 'login' ? '登录' : '注册并登录'}
                </button>
              </form>

              <p className="small muted" style={{ marginBottom: 0 }}>
                <Link to="/" style={{ textDecoration: 'underline' }}>
                  先随便逛逛
                </Link>
              </p>
            </>
          )}
        </section>
      </div>

      {cap.modal}
    </main>
  );
}
