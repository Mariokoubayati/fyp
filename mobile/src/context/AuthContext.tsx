import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import * as authApi from '../api/auth';
import { setUnauthorizedHandler } from '../api/client';
import { AuthSession, User } from '../api/types';
import { tokenStorage } from '../storage/tokenStorage';

interface AuthContextValue {
  user: User | null;
  /** True while the stored token is being restored on app start. */
  isLoading: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (name: string, email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let active = true;

    setUnauthorizedHandler(() => setUser(null));

    async function restoreSession() {
      try {
        const token = await tokenStorage.get();

        if (token) {
          const currentUser = await authApi.fetchCurrentUser();

          if (active) {
            setUser(currentUser);
          }
        }
      } catch {
        // An invalid token is cleared by the API client; any other failure
        // (e.g. offline) just leaves the user on the sign-in screen.
      } finally {
        if (active) {
          setIsLoading(false);
        }
      }
    }

    restoreSession();

    return () => {
      active = false;
      setUnauthorizedHandler(null);
    };
  }, []);

  const startSession = useCallback(async (session: AuthSession) => {
    await tokenStorage.set(session.access_token);
    setUser(session.user);
  }, []);

  const signIn = useCallback(
    async (email: string, password: string) => startSession(await authApi.login(email, password)),
    [startSession],
  );

  const signUp = useCallback(
    async (name: string, email: string, password: string) =>
      startSession(await authApi.register(name, email, password)),
    [startSession],
  );

  const signOut = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      // The token may already be expired; signing out locally is what matters.
    }

    await tokenStorage.clear();
    setUser(null);
  }, []);

  const value = useMemo(
    () => ({ user, isLoading, signIn, signUp, signOut }),
    [user, isLoading, signIn, signUp, signOut],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside <AuthProvider>.');
  }

  return context;
}
