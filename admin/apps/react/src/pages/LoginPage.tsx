/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useCallback, useEffect, useState, type FormEvent, type MouseEvent } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../lib/api';
import { useAuth, type Click } from '../lib/auth';
import { describeCaptcha, type CaptchaData } from '../lib/captcha';
import { Loading } from '../components/ui';

/** 无图时的兜底尺寸，与 .cap-ph 的高度一致（验证码画布 300x200），保证坐标换算不跳变。 */
const FALLBACK_W = 300;
const FALLBACK_H = 200;

export function LoginPage() {
  const { user, login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = (location.state as { from?: string } | null)?.from ?? '/';

  const [captcha, setCaptcha] = useState<CaptchaData | null>(null);
  const [loadingCap, setLoadingCap] = useState(true);
  const [capError, setCapError] = useState<string | null>(null);
  const [size, setSize] = useState({ w: FALLBACK_W, h: FALLBACK_H });
  const [dots, setDots] = useState<Click[]>([]);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { imgSrc, required, hint } = describeCaptcha(captcha);

  const loadCaptcha = useCallback(async () => {
    setLoadingCap(true);
    setCapError(null);
    setDots([]);
    setSize({ w: FALLBACK_W, h: FALLBACK_H });
    try {
      const data = await api<CaptchaData>('/api/v1/captcha/generate', {
        method: 'POST',
        body: { difficulty: 'easy' },
        auth: false,
      });
      setCaptcha(data ?? null);
    } catch (cause) {
      setCaptcha(null);
      setCapError(cause instanceof ApiError ? cause.message : '验证码加载失败，可直接标点重试');
    } finally {
      setLoadingCap(false);
    }
  }, []);

  useEffect(() => {
    void loadCaptcha();
  }, [loadCaptcha]);

  const onPick = (event: MouseEvent<HTMLDivElement>) => {
    const box = event.currentTarget.getBoundingClientRect();
    if (!box.width || !box.height) return;
    // 点击坐标由显示尺寸换算回图片原始像素，服务端按原图坐标校验；
    // 目标数固定且逐点顺序校验，多点无效，标满即止
    setDots((prev) =>
      prev.length >= required
        ? prev
        : [
            ...prev,
            {
              x: Math.round(((event.clientX - box.left) / box.width) * size.w),
              y: Math.round(((event.clientY - box.top) / box.height) * size.h),
            },
          ],
    );
  };

  // 放在所有 hook 之后，避免条件渲染跳过 hook
  if (user) return <Navigate to={from} replace />;

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (busy || dots.length !== required) return;
    setBusy(true);
    setError(null);
    try {
      await login(username, password, captcha?.key ?? '', dots);
      navigate(from, { replace: true });
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : '登录失败，请稍后重试');
      setPassword('');
      await loadCaptcha();
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="login">
      <div className="login-card">
        <div className="login-h">
          <h1 className="h1">游戏运营台</h1>
          <p className="sub">{hint}</p>
        </div>

        <form className="form" onSubmit={submit}>
          <label className="label">
            账号
            <input
              className="input"
              value={username}
              autoComplete="username"
              onChange={(event) => setUsername(event.target.value)}
            />
          </label>

          <label className="label">
            密码
            <input
              className="input"
              type="password"
              value={password}
              autoComplete="current-password"
              onChange={(event) => setPassword(event.target.value)}
            />
          </label>

          <div className="label">
            验证码
            <div className="cap" onClick={onPick} role="presentation">
              {imgSrc ? (
                <img
                  className="cap-img"
                  src={imgSrc}
                  alt="点击验证码"
                  draggable={false}
                  onLoad={(event) => {
                    const el = event.currentTarget;
                    if (el.naturalWidth > 0) setSize({ w: el.naturalWidth, h: el.naturalHeight });
                  }}
                />
              ) : (
                <div className="cap-ph">验证码图片不可用，直接点击此区域标记坐标后提交</div>
              )}
              {dots.map((dot, index) => (
                <span
                  key={`${dot.x}-${dot.y}-${index}`}
                  className="cap-dot"
                  style={{ left: `${(dot.x / size.w) * 100}%`, top: `${(dot.y / size.h) * 100}%` }}
                >
                  {index + 1}
                </span>
              ))}
            </div>
            <div className="cap-bar">
              <span>已标 {dots.length} 点（需 {required} 点）</span>
              <span className="pagehead-a">
                <button
                  type="button"
                  className="btn btn-sm"
                  disabled={!dots.length}
                  onClick={() => setDots((prev) => prev.slice(0, -1))}
                >
                  撤销
                </button>
                <button type="button" className="btn btn-sm" onClick={() => void loadCaptcha()}>
                  换一张
                </button>
              </span>
            </div>
          </div>

          {loadingCap ? <Loading rows={1} label="验证码加载中" /> : null}
          {capError ? <p className="sub" style={{ color: 'var(--amber)' }}>{capError}</p> : null}
          {error ? <p className="errnote">{error}</p> : null}

          <button className="btn" type="submit" disabled={busy || dots.length !== required || !username || !password}>
            {busy ? '登录中…' : '登录'}
          </button>
        </form>
      </div>
    </div>
  );
}
