import { api } from './client';
import { ApiEnvelope, CampusTag, NewProject, Paginated, Project } from './types';

export type ProjectFilter = 'recommended' | 'all' | 'joined' | 'mine';

export async function fetchProjects(filter: ProjectFilter, tag?: CampusTag | null): Promise<Project[]> {
  if (filter === 'recommended') {
    const { data } = await api.get<ApiEnvelope<Project[]>>('/projects/recommended');

    return tag ? data.data.filter((project) => project.tags.includes(tag)) : data.data;
  }

  const { data } = await api.get<Paginated<Project>>('/projects', {
    params: { filter, ...(tag ? { tag } : {}) },
  });

  return data.data;
}

export async function fetchProject(id: number): Promise<Project> {
  const { data } = await api.get<ApiEnvelope<Project>>(`/projects/${id}`);

  return data.data;
}

export async function createProject(payload: NewProject): Promise<Project> {
  const { data } = await api.post<ApiEnvelope<Project>>('/projects', payload);

  return data.data;
}

export async function updateProject(id: number, payload: Partial<NewProject> & { is_open?: boolean }): Promise<Project> {
  const { data } = await api.patch<ApiEnvelope<Project>>(`/projects/${id}`, payload);

  return data.data;
}

export async function requestToJoin(id: number, message: string | null): Promise<Project> {
  const { data } = await api.put<ApiEnvelope<Project>>(`/projects/${id}/request`, { message });

  return data.data;
}

export async function leaveProject(id: number): Promise<void> {
  await api.delete(`/projects/${id}/request`);
}

export async function answerRequest(id: number, userId: number, status: 'accepted' | 'declined'): Promise<Project> {
  const { data } = await api.put<ApiEnvelope<Project>>(`/projects/${id}/members/${userId}`, { status });

  return data.data;
}

export async function deleteProject(id: number): Promise<void> {
  await api.delete(`/projects/${id}`);
}
