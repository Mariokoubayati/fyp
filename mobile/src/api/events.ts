import { api } from './client';
import { ApiEnvelope, CampusEvent, CampusTag, NewEvent, Paginated } from './types';

export type EventFilter = 'recommended' | 'all' | 'going' | 'mine';

export async function fetchEvents(filter: EventFilter, tag?: CampusTag | null): Promise<CampusEvent[]> {
  if (filter === 'recommended') {
    const { data } = await api.get<ApiEnvelope<CampusEvent[]>>('/events/recommended');

    return tag ? data.data.filter((event) => event.tags.includes(tag)) : data.data;
  }

  const { data } = await api.get<Paginated<CampusEvent>>('/events', {
    params: { filter, ...(tag ? { tag } : {}) },
  });

  return data.data;
}

export async function fetchEvent(id: number): Promise<CampusEvent> {
  const { data } = await api.get<ApiEnvelope<CampusEvent>>(`/events/${id}`);

  return data.data;
}

export async function createEvent(payload: NewEvent): Promise<CampusEvent> {
  const { data } = await api.post<ApiEnvelope<CampusEvent>>('/events', payload);

  return data.data;
}

export async function rsvpEvent(id: number): Promise<CampusEvent> {
  const { data } = await api.put<ApiEnvelope<CampusEvent>>(`/events/${id}/rsvp`);

  return data.data;
}

export async function cancelRsvp(id: number): Promise<void> {
  await api.delete(`/events/${id}/rsvp`);
}

export async function deleteEvent(id: number): Promise<void> {
  await api.delete(`/events/${id}`);
}
