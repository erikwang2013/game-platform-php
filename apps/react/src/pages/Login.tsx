/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState, type FormEvent } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/auth.tsx';

export function Login() {
  const { user, login, register } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [email, setEmail] = useState('');
  const [err, setErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (user) return <Navigate to="/" replace />;

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setErr(null);
    setBusy(true);
    try {
      if (mode === 'login') await login(username.trim(), password);
      else await register(username.trim(), password, email.trim());
      const from = (location.state as { from?: string } | null)?.from;
      navigate(from ?? '/', { replace: true });
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : '操作失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  const switchTo = (m: 'login' | 'register') => {
    setMode(m);
    setErr(null);
  };

  return (
    <main className="shell">
      <div className="shell--split" style={{ paddingTop: 32, paddingBottom: 48 }}>
        <section className="stack">
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

            <label className="field">
              <span>密码</span>
              <input
                className="input"
                name="password"
                type="password"
                autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
                required
                minLength={6}
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
        </section>
      </div>
    </main>
  );
}
