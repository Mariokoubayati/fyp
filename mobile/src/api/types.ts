export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  major: string | null;
  study_year: number | null;
  bio: string | null;
  created_at: string;
}

export interface Course {
  id: number;
  code: string;
  name: string;
}

export interface ProfileUpdate {
  name?: string;
  major?: string | null;
  study_year?: number | null;
  bio?: string | null;
}

/** Laravel paginator wrapped by an Eloquent resource collection. */
export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; last_page: number; total: number };
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
