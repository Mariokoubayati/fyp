import { api } from './client';
import { ApiEnvelope, Listing, ListingCategory, ListingStatus, NewListing, Paginated } from './types';

export interface ListingQuery {
  search?: string;
  category?: ListingCategory | null;
  mine?: boolean;
}

export async function fetchListings({ search, category, mine }: ListingQuery): Promise<Listing[]> {
  const { data } = await api.get<Paginated<Listing>>('/listings', {
    params: {
      search: search || undefined,
      category: category || undefined,
      mine: mine ? 1 : undefined,
    },
  });

  return data.data;
}

export async function fetchListing(id: number): Promise<Listing> {
  const { data } = await api.get<ApiEnvelope<Listing>>(`/listings/${id}`);

  return data.data;
}

export async function createListing(payload: NewListing): Promise<Listing> {
  const { data } = await api.post<ApiEnvelope<Listing>>('/listings', payload);

  return data.data;
}

export async function setListingStatus(id: number, status: Exclude<ListingStatus, 'sold'>): Promise<Listing> {
  const { data } = await api.patch<ApiEnvelope<Listing>>(`/listings/${id}`, { status });

  return data.data;
}

export async function markListingSold(id: number, buyerId: number): Promise<Listing> {
  const { data } = await api.post<ApiEnvelope<Listing>>(`/listings/${id}/sold`, { buyer_id: buyerId });

  return data.data;
}

export async function deleteListing(id: number): Promise<void> {
  await api.delete(`/listings/${id}`);
}

export async function rateSeller(id: number, stars: number): Promise<void> {
  await api.post(`/listings/${id}/rating`, { stars });
}
