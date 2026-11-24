import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const TOKEN_KEY = 'unicircle.access_token';

/**
 * Phones keep the token in the OS keychain (SecureStore). The browser build, used only as a
 * second test account during development, has no keychain, so it falls back to localStorage.
 */
const browser = {
  get: async (): Promise<string | null> => globalThis.localStorage?.getItem(TOKEN_KEY) ?? null,
  set: async (token: string): Promise<void> => globalThis.localStorage?.setItem(TOKEN_KEY, token),
  clear: async (): Promise<void> => globalThis.localStorage?.removeItem(TOKEN_KEY),
};

const device = {
  get: (): Promise<string | null> => SecureStore.getItemAsync(TOKEN_KEY),
  set: (token: string): Promise<void> => SecureStore.setItemAsync(TOKEN_KEY, token),
  clear: (): Promise<void> => SecureStore.deleteItemAsync(TOKEN_KEY),
};

export const tokenStorage = Platform.OS === 'web' ? browser : device;
