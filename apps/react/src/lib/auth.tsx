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

interface AuthValue {
  user: Profile | null;
  ready: boolean;
  login: (username: string, password: string) => Promise<void>;
  register: (username: string, password: string, email: string) => Promise<void>;
  logout: () => void;
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

  const login = useCallback(async (username: string, password: string) => {
    const data = await api.login(username, password);
    if (!('access_token' in data)) {
      // 该账号开启了 2FA，需走 /2fa/verify 换发正式 token
      throw new Error('该账号已开启两步验证，请先在客户端完成 2FA 校验');
    }
    tokens.set(data.access_token, data.refresh_token);
    setUser(await api.profile());
  }, []);

  const register = useCallback(
    async (username: string, password: string, email: string) => {
      const data = await api.register(username, password, email);
      tokens.set(data.access_token, data.refresh_token);
      setUser(await api.profile());
    },
    [],
  );

  const value = useMemo<AuthValue>(
    () => ({ user, ready, login, register, logout }),
    [user, ready, login, register, logout],
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth() {
  const v = useContext(Ctx);
  if (!v) throw new Error('useAuth must be used inside <AuthProvider>');
  return v;
}
