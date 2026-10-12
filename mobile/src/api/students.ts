import { api } from './client';
import { ApiEnvelope, Student, StudentProfile } from './types';

export async function searchStudents(search: string): Promise<Student[]> {
  const { data } = await api.get<ApiEnvelope<Student[]>>('/users', { params: { search } });

  return data.data;
}

export async function fetchStudent(id: number): Promise<StudentProfile> {
  const { data } = await api.get<ApiEnvelope<StudentProfile>>(`/users/${id}`);

  return data.data;
}

export async function vouchFor(id: number): Promise<void> {
  await api.put(`/users/${id}/vouch`);
}

export async function withdrawVouch(id: number): Promise<void> {
  await api.delete(`/users/${id}/vouch`);
}
