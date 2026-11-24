import { api } from './client';
import { ApiEnvelope, CampusEvent, Listing, Project, StudyGroup } from './types';

export interface HomeFeed {
  groups: StudyGroup[];
  listings: Listing[];
  events: CampusEvent[];
  projects: Project[];
  /** 'model' when the recommender ranked the feed, 'rules' when the SQL ordering did. */
  source: 'model' | 'rules';
}

export async function fetchHomeFeed(): Promise<HomeFeed> {
  const { data } = await api.get<ApiEnvelope<HomeFeed>>('/recommendations');

  return data.data;
}
