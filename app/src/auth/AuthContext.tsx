import AsyncStorage from '@react-native-async-storage/async-storage';
import { useQueryClient } from '@tanstack/react-query';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { api, setToken, setUnauthorizedHandler } from '@/api/client';

import { tokenStore } from './tokenStore';

const INSTALLED_KEY = 'wa.installed';

type Status = 'loading' | 'signedOut' | 'signedIn';

type AuthValue = {
  status: Status;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
};

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<Status>('loading');

  const clearSession = useCallback(async () => {
    setToken(null);
    await tokenStore.clear();
    // Children's details must not outlive the session on the phone.
    queryClient.clear();
    setStatus('signedOut');
  }, [queryClient]);

  useEffect(() => {
    (async () => {
      // Keychain entries survive an uninstall; a fresh install starts signed out.
      if (!(await AsyncStorage.getItem(INSTALLED_KEY))) {
        await tokenStore.clear();
        await AsyncStorage.setItem(INSTALLED_KEY, '1');
      }
      const saved = await tokenStore.get();
      setToken(saved);
      setStatus(saved ? 'signedIn' : 'signedOut');
    })();
  }, []);

  useEffect(() => {
    setUnauthorizedHandler(() => void clearSession());
    return () => setUnauthorizedHandler(null);
  }, [clearSession]);

  const signIn = useCallback(async (email: string, password: string) => {
    const { token } = await api.login(email.trim(), password);
    await tokenStore.set(token);
    setToken(token);
    setStatus('signedIn');
  }, []);

  const signOut = useCallback(async () => {
    try {
      await api.logout();
    } catch {
      // Signing out locally matters more than telling the server.
    }
    await clearSession();
  }, [clearSession]);

  const value = useMemo(() => ({ status, signIn, signOut }), [status, signIn, signOut]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
