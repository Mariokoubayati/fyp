import { api } from './client';
import { ApiEnvelope, Course, Paginated } from './types';

export async function fetchCourses(search: string): Promise<Course[]> {
  const { data } = await api.get<Paginated<Course>>('/courses', { params: search ? { search } : undefined });

  return data.data;
}

export async function fetchMyCourses(): Promise<Course[]> {
  const { data } = await api.get<ApiEnvelope<Course[]>>('/me/courses');

  return data.data;
}

export async function enroll(courseId: number): Promise<void> {
  await api.put(`/me/courses/${courseId}`);
}

export async function leave(courseId: number): Promise<void> {
  await api.delete(`/me/courses/${courseId}`);
}
