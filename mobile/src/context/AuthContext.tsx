import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import * as authApi from '../api/auth';
import { setUnauthorizedHandler } from '../api/client';
import { getErrorMessage } from '../api/errors';
import { AuthSession, User } from '../api/types';
import { tokenStorage } from '../storage/tokenStorage';

interface AuthContextValue {
  user: User | null;
  /** True while the stored token is being restored on app start. */
  isLoading: boolean;
  /**
   * Set when a stored session exists but the server could not be reached (or failed) on app start.
   * The student is still signed in; they only need to retry.
   */
  restoreError: string | null;
  /** Try restoring the stored session again after a restoreError. */
  retryRestore: () => void;
  /** True while a retry is in progress. */
  retrying: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (name: string, email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
  /** Replace the cached user, e.g. after the profile was edited. */
  updateUser: (user: User) => void;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const [restoreError, setRestoreError] = useState<string | null>(null);
  const [attempt, setAttempt] = useState(0);
  const [retrying, setRetrying] = useState(false);

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
            setRestoreError(null);
          }
        }
      } catch (error) {
        // An invalid token is cleared by the API client (sign-in screen). Any other failure
        // (offline, server down) keeps the token and offers a retry instead of looking signed out.
        const stillSignedIn = !!(await tokenStorage.get());

        if (active) {
          setRestoreError(stillSignedIn ? getErrorMessage(error) : null);
        }
      } finally {
        if (active) {
          setIsLoading(false);
          setRetrying(false);
        }
      }
    }

    restoreSession();

    return () => {
      active = false;
      setUnauthorizedHandler(null);
    };
  }, [attempt]);

  const retryRestore = useCallback(() => {
    setRetrying(true);
    setAttempt((n) => n + 1);
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
    setRestoreError(null);
    setUser(null);
  }, []);

  const value = useMemo(
    () => ({ user, isLoading, restoreError, retryRestore, retrying, signIn, signUp, signOut, updateUser: setUser }),
    [user, isLoading, restoreError, retryRestore, retrying, signIn, signUp, signOut],
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
