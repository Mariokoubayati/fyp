export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  major: string | null;
  study_year: number | null;
  bio: string | null;
  trust_score: number;
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
  is_buyer: boolean;
  rating?: { stars: number; comment: string | null } | null;
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

export type LoanStatus = 'requested' | 'accepted' | 'active' | 'repaid' | 'overdue' | 'defaulted' | 'cancelled';

export interface Loan {
  id: number;
  amount: number;
  purpose: string | null;
  due_date: string;
  status: LoanStatus;
  my_role: 'borrower' | 'lender' | null;
  borrower?: { id: number; name: string; trust_score: number; trust_tier: TrustTier };
  lender?: { id: number; name: string } | null;
  handover: { lender_confirmed: boolean; borrower_confirmed: boolean };
  repayment: { lender_confirmed: boolean; borrower_confirmed: boolean };
  activated_at: string | null;
  repaid_at: string | null;
  created_at: string;
}

export interface NewLoan {
  amount: number;
  purpose: string | null;
  due_date: string;
}

export type TrustTier = 'restricted' | 'basic' | 'trusted';

export interface TrustSummary {
  score: number;
  tier: TrustTier;
  max_loan: number;
  breakdown: Record<'repayment' | 'marketplace' | 'vouches' | 'account' | 'engagement', number>;
  history: { score: number; tier: TrustTier; trigger: string | null; created_at: string }[];
}

export interface StudentProfile extends Student {
  bio: string | null;
  trust: { score: number; tier: TrustTier; max_loan: number };
  vouch_count: number;
  vouched_by_me: boolean;
}
