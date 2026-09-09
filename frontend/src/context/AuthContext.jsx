import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { authService } from '../services/authService';
import { setToken } from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [initializing, setInitializing] = useState(true);

  const loadUser = useCallback(async () => {
    if (!authService.hasToken()) {
      setUser(null);
      setInitializing(false);
      return;
    }

    try {
      const result = await authService.me();
      setUser(result.user);
    } catch {
      setToken(null);
      setUser(null);
    } finally {
      setInitializing(false);
    }
  }, []);

  useEffect(() => {
    loadUser();
  }, [loadUser]);

  const login = useCallback(async (payload) => {
    const result = await authService.login(payload);
    setUser(result.user);
    return result;
  }, []);

  const loginWithGoogle = useCallback(async (accessToken) => {
    const result = await authService.loginWithGoogle(accessToken);
    setUser(result.user);
    return result;
  }, []);

  const register = useCallback(async (payload) => {
    const result = await authService.register(payload);
    setUser(result.user);
    return result;
  }, []);

  const logout = useCallback(async () => {
    await authService.logout();
    setUser(null);
  }, []);

  const updateUser = useCallback((next) => {
    setUser((prev) => (prev ? { ...prev, ...next } : prev));
  }, []);

  const value = useMemo(
    () => ({ user, initializing, login, loginWithGoogle, register, logout, updateUser }),
    [user, initializing, login, loginWithGoogle, register, logout, updateUser]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
