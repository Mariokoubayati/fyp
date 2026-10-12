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

export interface StudyGroup {
  id: number;
  title: string;
  description: string | null;
  location: string | null;
  starts_at: string;
  capacity: number;
  members_count: number;
  is_full: boolean;
  is_member: boolean;
  is_creator: boolean;
  course?: Course;
  creator?: { id: number; name: string };
}

export interface NewStudyGroup {
  course_id: number;
  title: string;
  description: string | null;
  location: string | null;
  starts_at: string;
  capacity: number;
}

export type ListingCategory = 'books' | 'notes' | 'electronics' | 'supplies' | 'other';
export type ListingCondition = 'new' | 'like_new' | 'good' | 'fair';
export type ListingStatus = 'available' | 'reserved' | 'sold';

export interface Listing {
  id: number;
  title: string;
  description: string | null;
  price: number;
  category: ListingCategory;
  condition: ListingCondition;
  status: ListingStatus;
  sold_at: string | null;
  created_at: string;
  is_seller: boolean;
  course?: Course | null;
  seller?: { id: number; name: string };
}

export interface NewListing {
  title: string;
  description: string | null;
  price: number;
  category: ListingCategory;
  condition: ListingCondition;
  course_id: number | null;
}

/** Another student as shown in pickers: no email. */
export interface Student {
  id: number;
  name: string;
  major: string | null;
  study_year: number | null;
}
