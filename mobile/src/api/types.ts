export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  created_at: string;
}

export interface AuthSession {
  access_token: string;
  token_type: 'bearer';
  expires_in: number;
  user: User;
}

/** Every successful response from the API is wrapped in `data`. */
export interface ApiEnvelope<T> {
  data: T;
}

/** Laravel validation error shape (HTTP 422). */
export interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
}
