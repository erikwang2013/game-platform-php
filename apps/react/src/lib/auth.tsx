/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { api, setUnauthorizedHandler, tokens, type Profile } from './api.ts';
import type { CaptchaProof } from './captcha.ts';

/**
 * 登录结果分两支：直接登录成功，或账号开了 2FA —— 后者服务端**不签发正式 token**，
 * 只回一张 10 分钟短期票据，必须拿它 + TOTP 码走 /2fa/verify 换发。
 */
export type LoginResult = { status: 'ok' } | { status: '2fa'; pendingToken: string };

interface AuthValue {
  user: Profile | null;
  ready: boolean;
  /** 验证码由调用页面先弹框取好，这里只负责随请求带上 */
  login: (username: string, password: string, proof: CaptchaProof) => Promise<LoginResult>;
  /** 2FA 第二步：用 login 回的短期票据 + 6 位 TOTP 码换正式 token */
  complete2fa: (pendingToken: string, code: string) => Promise<void>;
  /** shareCode 是邀请短码，可选；无效码服务端静默忽略、不报错 */
  register: (
    username: string,
    password: string,
    email: string,
    proof: CaptchaProof,
    shareCode?: string,
  ) => Promise<void>;
  logout: () => void;
  /** 改资料后就地刷新用户态（改昵称/语言后不必重新拉整页） */
  refreshProfile: () => Promise<void>;
}

const Ctx = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<Profile | null>(null);
  const [ready, setReady] = useState(false);

  const logout = useCallback(() => {
    tokens.clear();
    setUser(null);
  }, []);

  // 刷新失败时 api 已清空 token，这里只需清用户态；受保护路由随即自动跳回 /login
  useEffect(() => {
    setUnauthorizedHandler(() => setUser(null));
    return () => setUnauthorizedHandler(() => {});
  }, []);

  useEffect(() => {
    let alive = true;
    if (!tokens.access()) {
      setReady(true);
      return;
    }
    api
      .profile()
      .then((p) => alive && setUser(p))
      .catch(() => alive && tokens.clear())
      .finally(() => alive && setReady(true));
    return () => {
      alive = false;
    };
  }, []);

  const login = useCallback(
    async (username: string, password: string, proof: CaptchaProof): Promise<LoginResult> => {
      const data = await api.login(username, password, proof);
      if (!('access_token' in data)) {
        // 该账号开启了 2FA：把短期票据交回页面继续第二步，此处不落任何 token
        return { status: '2fa', pendingToken: data.pending_2fa_token };
      }
      tokens.set(data.access_token, data.refresh_token);
      setUser(await api.profile());
      return { status: 'ok' };
    },
    [],
  );

  const complete2fa = useCallback(async (pendingToken: string, code: string) => {
    const data = await api.verify2fa(pendingToken, code);
    tokens.set(data.access_token, data.refresh_token);
    setUser(await api.profile());
  }, []);

  const refreshProfile = useCallback(async () => {
    setUser(await api.profile());
  }, []);

  const register = useCallback(
    async (
      username: string,
      password: string,
      email: string,
      proof: CaptchaProof,
      shareCode?: string,
    ) => {
      const data = await api.register(username, password, email, proof, shareCode);
      tokens.set(data.access_token, data.refresh_token);
      setUser(await api.profile());
    },
    [],
  );

  const value = useMemo<AuthValue>(
    () => ({ user, ready, login, complete2fa, register, logout, refreshProfile }),
    [user, ready, login, complete2fa, register, logout, refreshProfile],
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth() {
  const v = useContext(Ctx);
  if (!v) throw new Error('useAuth must be used inside <AuthProvider>');
  return v;
}
