import { create, isAxiosError } from 'axios';

import { API_URL } from '../config';
import { tokenStorage } from '../storage/tokenStorage';

let onUnauthorized: (() => void) | null = null;

/** Called when the API rejects the stored token, so the app can sign the user out. */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler;
}

export const api = create({
  baseURL: API_URL,
  timeout: 15000,
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use(async (config) => {
  const token = await tokenStorage.get();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error: unknown) => {
    if (!isAxiosError(error) || error.response?.status !== 401 || !error.config) {
      return Promise.reject(error);
    }

    const original = error.config as typeof error.config & { _retried?: boolean };
    const isAuthRequest = original.url === '/auth/login' || original.url === '/auth/refresh';

    // Tokens last an hour. Swap an expired one for a fresh one once, then retry the request.
    if (!isAuthRequest && !original._retried) {
      original._retried = true;
      const fresh = await refreshToken();
      if (fresh) {
        original.headers.Authorization = `Bearer ${fresh}`;
        return api.request(original);
      }
    }

    if (!isAuthRequest) {
      await tokenStorage.clear();
      onUnauthorized?.();
    }

    return Promise.reject(error);
  },
);

let refreshing: Promise<string | null> | null = null;

/**
 * Asks the API for a new token. Parallel 401s share one refresh call, so the token
 * is only rotated once. Returns null when the session cannot be renewed.
 */
function refreshToken(): Promise<string | null> {
  refreshing ??= (async () => {
    try {
      const current = await tokenStorage.get();
      if (!current) return null;
      const { data } = await api.post<{ data: { access_token: string } }>('/auth/refresh', null, {
        headers: { Authorization: `Bearer ${current}` },
      });
      await tokenStorage.set(data.data.access_token);
      return data.data.access_token;
    } catch {
      return null;
    } finally {
      refreshing = null;
    }
  })();

  return refreshing;
}
