import axios from 'axios';

import { API_URL } from '../config';
import { tokenStorage } from '../storage/tokenStorage';

let onUnauthorized: (() => void) | null = null;

/** Called when the API rejects the stored token, so the app can sign the user out. */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler;
}

export const api = axios.create({
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
    const isLoginRequest = axios.isAxiosError(error) && error.config?.url === '/auth/login';

    if (axios.isAxiosError(error) && error.response?.status === 401 && !isLoginRequest) {
      await tokenStorage.clear();
      onUnauthorized?.();
    }

    return Promise.reject(error);
  },
);
