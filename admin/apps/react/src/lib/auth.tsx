/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { t } from '../i18n/index.ts';
import { api, session, type AdminUser } from './api';

type LoginResult = {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  user: AdminUser;
};

export type Click = { x: number; y: number };

type AuthValue = {
  user: AdminUser | null;
  login: (username: string, password: string, captchaKey: string, clicks: Click[]) => Promise<void>;
  logout: () => void;
  /**
   * 改完资料后把会话里的用户信息刷新一遍（给「我的账号」用）。给了 patch 就先落盘再重读 ——
   * 侧栏与顶栏读的是同一处上下文，故不用重登录就能看到新名字。
   */
  refreshUser: (patch?: Partial<AdminUser>) => void;
};

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AdminUser | null>(() => session.user);

  const login = useCallback(
    async (username: string, password: string, captchaKey: string, clicks: Click[]) => {
      const data = await api<LoginResult>('/api/v1/auth/login', {
        method: 'POST',
        auth: false,
        body: { username, password, captcha_key: captchaKey, clicks },
      });
      session.save(data.access_token, data.refresh_token, data.user);
      setUser(data.user);
    },
    [],
  );

  const logout = useCallback(() => {
    session.clear();
    setUser(null);
  }, []);

  const refreshUser = useCallback((patch?: Partial<AdminUser>) => {
    if (patch) session.updateUser(patch);
    setUser(session.user);
  }, []);

  const value = useMemo<AuthValue>(() => ({ user, login, logout, refreshUser }), [user, login, logout, refreshUser]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error(t('auth.outside_provider'));
  return ctx;
}
