import { api } from './client';
import { ApiEnvelope, Student } from './types';

export interface Conversation {
  id: number;
  other: Student;
  last_message: { preview: string; is_mine: boolean; created_at: string } | null;
  unread_count: number;
  blocked: boolean;
  last_message_at: string | null;
}

export interface ChatMessage {
  id: number;
  conversation_id: number;
  sender_id: number;
  is_mine: boolean;
  body: string;
  read_at: string | null;
  created_at: string;
}

export type NotificationType =
  | 'message'
  | 'loan_accepted'
  | 'loan_due_soon'
  | 'loan_overdue'
  | 'event_starting_soon'
  | 'event_cancelled'
  | 'project_join_requested'
  | 'project_request_accepted'
  | 'project_request_declined';

export interface AppNotification {
  id: string;
  type: NotificationType;
  data: {
    conversation_id?: number;
    sender?: { id: number; name: string };
    preview?: string;
    loan_id?: number;
    amount?: number;
    due_date?: string | null;
    other?: { id: number; name: string } | null;
    event_id?: number;
    project_id?: number;
    title?: string;
    starts_at?: string;
    location?: string;
  };
  read_at: string | null;
  created_at: string;
}

export type ReportReason = 'harassment' | 'spam' | 'scam' | 'inappropriate' | 'other';

export async function fetchConversations(): Promise<Conversation[]> {
  const { data } = await api.get<ApiEnvelope<Conversation[]>>('/conversations');
  return data.data;
}

/** Opens the conversation with a student (returns the existing one if there is one). */
export async function startConversation(userId: number): Promise<Conversation> {
  const { data } = await api.post<ApiEnvelope<Conversation>>('/conversations', { user_id: userId });
  return data.data;
}

/** The latest messages, or only those newer than `after` (used for polling). */
export interface MessagePage {
  messages: ChatMessage[];
  /** Id of the newest of my messages the other student has read, or null. */
  seenUpTo: number | null;
}

export async function fetchMessages(conversationId: number, after?: number): Promise<MessagePage> {
  const { data } = await api.get<ApiEnvelope<ChatMessage[]> & { meta?: { seen_up_to?: number | null } }>(
    `/conversations/${conversationId}/messages`,
    { params: after ? { after } : undefined },
  );
  return { messages: data.data, seenUpTo: data.meta?.seen_up_to ?? null };
}

export async function sendMessage(conversationId: number, body: string): Promise<ChatMessage> {
  const { data } = await api.post<ApiEnvelope<ChatMessage>>(`/conversations/${conversationId}/messages`, { body });
  return data.data;
}

export async function blockStudent(userId: number): Promise<void> {
  await api.put(`/users/${userId}/block`);
}

export async function unblockStudent(userId: number): Promise<void> {
  await api.delete(`/users/${userId}/block`);
}

export async function reportStudent(userId: number, reason: ReportReason, conversationId?: number): Promise<void> {
  await api.post(`/users/${userId}/report`, { reason, conversation_id: conversationId });
}

export async function fetchNotifications(): Promise<{ items: AppNotification[]; unread: number }> {
  const { data } = await api.get<{ data: AppNotification[]; meta: { unread_count: number } }>('/notifications');
  return { items: data.data, unread: data.meta.unread_count };
}

export async function markNotificationsRead(ids?: string[]): Promise<void> {
  await api.post('/notifications/read', ids ? { ids } : {});
}

export async function fetchBadges(): Promise<{ messages: number; notifications: number }> {
  const { data } = await api.get<ApiEnvelope<{ messages: number; notifications: number }>>('/me/badges');
  return data.data;
}
