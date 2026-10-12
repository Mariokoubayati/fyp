import { isAxiosError } from 'axios';

import { ApiErrorBody } from './types';

/** A readable message for any error thrown by an API call. */
export function getErrorMessage(error: unknown): string {
  if (isAxiosError<ApiErrorBody>(error)) {
    if (!error.response) {
      return 'Cannot reach the server. Check your connection and the API address.';
    }

    if (error.response.status === 429) {
      return 'Too many attempts. Please wait a minute and try again.';
    }

    return error.response.data?.message ?? 'Something went wrong. Please try again.';
  }

  return 'Something went wrong. Please try again.';
}

/** First validation message per field from a Laravel 422 response. */
export function getFieldErrors(error: unknown): Record<string, string> {
  if (!isAxiosError<ApiErrorBody>(error) || error.response?.status !== 422) {
    return {};
  }

  const errors = error.response.data?.errors ?? {};

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? '']),
  );
}
