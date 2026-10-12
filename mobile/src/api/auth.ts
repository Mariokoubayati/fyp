import { api } from './client';
import { ApiEnvelope, AuthSession, User } from './types';

export async function register(name: string, email: string, password: string): Promise<AuthSession> {
  const { data } = await api.post<ApiEnvelope<AuthSession>>('/auth/register', { name, email, password });

  return data.data;
}

export async function login(email: string, password: string): Promise<AuthSession> {
  const { data } = await api.post<ApiEnvelope<AuthSession>>('/auth/login', { email, password });

  return data.data;
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout');
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await api.get<ApiEnvelope<User>>('/me');

  return data.data;
}
