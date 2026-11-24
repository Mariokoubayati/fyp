import { CampusTag } from '../api/types';

export const TAG_LABELS: Record<CampusTag, string> = {
  academic: 'Academic',
  tech: 'Tech',
  sports: 'Sports',
  social: 'Social',
  culture: 'Culture',
  arts: 'Arts',
  career: 'Career',
  volunteering: 'Volunteering',
};

export const TAG_OPTIONS = (Object.keys(TAG_LABELS) as CampusTag[]).map((value) => ({ value, label: TAG_LABELS[value] }));

/** "Laravel, UI design" typed by a student becomes ["Laravel", "UI design"]. */
export function parseSkills(text: string): string[] {
  return text
    .split(',')
    .map((skill) => skill.trim())
    .filter((skill) => skill.length > 0);
}
