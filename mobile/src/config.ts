/**
 * Base URL of the Laravel API.
 *
 * Set EXPO_PUBLIC_API_URL in mobile/.env. Use your computer's LAN IP when
 * testing on a real phone (e.g. http://192.168.1.20:8000/api/v1) and
 * http://10.0.2.2:8000/api/v1 on the Android emulator.
 */
export const API_URL: string =
  process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api/v1';
