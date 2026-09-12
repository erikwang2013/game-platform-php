/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
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

  const value = useMemo<AuthValue>(() => ({ user, login, logout }), [user, login, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth 必须在 AuthProvider 内使用');
  return ctx;
}
