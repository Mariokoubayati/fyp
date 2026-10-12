import { ListingCategory, ListingCondition } from '../api/types';

export const CATEGORY_LABELS: Record<ListingCategory, string> = {
  books: 'Books',
  notes: 'Notes',
  electronics: 'Electronics',
  supplies: 'Supplies',
  other: 'Other',
};

export const CONDITION_LABELS: Record<ListingCondition, string> = {
  new: 'New',
  like_new: 'Like new',
  good: 'Good',
  fair: 'Fair',
};

export function formatPrice(price: number): string {
  return price === 0 ? 'Free' : `$${price.toFixed(2)}`;
}

export const CATEGORY_OPTIONS = (Object.keys(CATEGORY_LABELS) as ListingCategory[]).map((value) => ({
  value,
  label: CATEGORY_LABELS[value],
}));

export const CONDITION_OPTIONS = (Object.keys(CONDITION_LABELS) as ListingCondition[]).map((value) => ({
  value,
  label: CONDITION_LABELS[value],
}));
