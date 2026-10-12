import { api } from './client';
import { ApiEnvelope, NewStudyGroup, Paginated, StudyGroup } from './types';

export type GroupFilter = 'recommended' | 'all' | 'mine';

export async function fetchStudyGroups(filter: GroupFilter): Promise<StudyGroup[]> {
  if (filter === 'recommended') {
    const { data } = await api.get<ApiEnvelope<StudyGroup[]>>('/study-groups/recommended');

    return data.data;
  }

  const { data } = await api.get<Paginated<StudyGroup>>('/study-groups', {
    params: filter === 'mine' ? { mine: 1 } : undefined,
  });

  return data.data;
}

export async function fetchStudyGroup(id: number): Promise<StudyGroup> {
  const { data } = await api.get<ApiEnvelope<StudyGroup>>(`/study-groups/${id}`);

  return data.data;
}

export async function createStudyGroup(payload: NewStudyGroup): Promise<StudyGroup> {
  const { data } = await api.post<ApiEnvelope<StudyGroup>>('/study-groups', payload);

  return data.data;
}

export async function joinStudyGroup(id: number): Promise<StudyGroup> {
  const { data } = await api.put<ApiEnvelope<StudyGroup>>(`/study-groups/${id}/membership`);

  return data.data;
}

export async function leaveStudyGroup(id: number): Promise<void> {
  await api.delete(`/study-groups/${id}/membership`);
}

export async function deleteStudyGroup(id: number): Promise<void> {
  await api.delete(`/study-groups/${id}`);
}
