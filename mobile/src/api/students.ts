import { api } from './client';
import { ApiEnvelope, Student } from './types';

export async function searchStudents(search: string): Promise<Student[]> {
  const { data } = await api.get<ApiEnvelope<Student[]>>('/users', { params: { search } });

  return data.data;
}
