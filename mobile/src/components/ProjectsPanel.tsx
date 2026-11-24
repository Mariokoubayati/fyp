import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '../api/errors';
import { fetchProjects, ProjectFilter } from '../api/projects';
import { CampusTag, Project } from '../api/types';
import { colors, spacing, typography } from '../theme';
import { Button } from './Button';
import { EmptyState } from './EmptyState';
import { Loading } from './Loading';
import { ProjectCard } from './ProjectCard';
import { SegmentedControl } from './SegmentedControl';
import { TagFilter } from './TagFilter';

const FILTERS: { value: ProjectFilter; label: string }[] = [
  { value: 'recommended', label: 'For you' },
  { value: 'all', label: 'All' },
  { value: 'joined', label: 'Joined' },
  { value: 'mine', label: 'Mine' },
];

const EMPTY_MESSAGES: Record<ProjectFilter, string> = {
  recommended: 'Add your skills and interests on your profile and projects that need you will show up here.',
  all: 'No projects are looking for members right now. Post yours.',
  joined: 'Teams you are accepted into will appear here.',
  mine: 'Projects you post will appear here.',
};

/** The projects part of the Campus tab. */
export function ProjectsPanel() {
  const [filter, setFilter] = useState<ProjectFilter>('recommended');
  const [tag, setTag] = useState<CampusTag | null>(null);
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      setError(null);

      fetchProjects(filter, tag)
        .then((result) => active && setProjects(result))
        .catch((e) => active && setError(getErrorMessage(e)))
        .finally(() => active && setLoading(false));

      return () => {
        active = false;
      };
    }, [filter, tag]),
  );

  return (
    <View style={styles.root}>
      <View style={styles.top}>
        <SegmentedControl options={FILTERS} value={filter} onChange={setFilter} />
        <TagFilter value={tag} onChange={setTag} />
        <Button title="Post a project" onPress={() => router.push('/project/new')} />
        {error ? <Text style={styles.error}>{error}</Text> : null}
      </View>

      {loading ? (
        <Loading />
      ) : (
        <FlatList
          data={projects}
          keyExtractor={(project) => String(project.id)}
          contentContainerStyle={styles.list}
          ListEmptyComponent={<EmptyState icon="construct-outline" title="No projects here" message={EMPTY_MESSAGES[filter]} />}
          renderItem={({ item }) => <ProjectCard project={item} onPress={() => router.push(`/project/${item.id}`)} />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  top: { paddingHorizontal: spacing.md, paddingBottom: spacing.md, paddingTop: spacing.sm, gap: spacing.sm },
  list: { paddingHorizontal: spacing.md, paddingBottom: spacing.lg, flexGrow: 1 },
  error: { ...typography.caption, color: colors.danger },
});
