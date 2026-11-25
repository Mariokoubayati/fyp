import { api } from './client';
import { Activity, ApiEnvelope } from './types';

export async function fetchMyActivity(): Promise<Activity> {
  const { data } = await api.get<ApiEnvelope<Activity>>('/me/activity');

  return data.data;
}
