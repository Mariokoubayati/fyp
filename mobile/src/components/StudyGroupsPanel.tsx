import { useFocusEffect, router } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '../api/errors';
import { fetchStudyGroups, GroupFilter } from '../api/studyGroups';
import { StudyGroup } from '../api/types';
import { Button } from './Button';
import { EmptyState } from './EmptyState';
import { Loading } from './Loading';
import { SegmentedControl } from './SegmentedControl';
import { StudyGroupCard } from './StudyGroupCard';
import { colors, spacing, typography } from '../theme';

const FILTERS: { value: GroupFilter; label: string }[] = [
  { value: 'recommended', label: 'For you' },
  { value: 'all', label: 'All' },
  { value: 'mine', label: 'Mine' },
];

const EMPTY_MESSAGES: Record<GroupFilter, string> = {
  recommended: 'Enroll in your courses and groups you can join will show up here.',
  all: 'Be the first to start a group.',
  mine: 'Groups you join or create will appear here.',
};

/** The study groups part of the Campus tab. */
export function StudyGroupsPanel() {
  const [filter, setFilter] = useState<GroupFilter>('recommended');
  const [groups, setGroups] = useState<StudyGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Reload whenever the tab gains focus or the filter changes, so joins made
  // on the detail screen are reflected when the student comes back.
  useFocusEffect(
    useCallback(() => {
      let active = true;
      setError(null);

      fetchStudyGroups(filter)
        .then((result) => active && setGroups(result))
        .catch((e) => active && setError(getErrorMessage(e)))
        .finally(() => active && setLoading(false));

      return () => {
        active = false;
      };
    }, [filter]),
  );

  return (
    <View style={styles.root}>
      <View style={styles.top}>
        <SegmentedControl options={FILTERS} value={filter} onChange={setFilter} />
        <Button title="Start a study group" onPress={() => router.push('/study-group/new')} />
        {error ? <Text style={styles.error}>{error}</Text> : null}
      </View>

      {loading ? (
        <Loading />
      ) : (
        <FlatList
          data={groups}
          keyExtractor={(group) => String(group.id)}
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <EmptyState icon="people-outline" title="No study groups yet" message={EMPTY_MESSAGES[filter]} />
          }
          renderItem={({ item }) => (
            <StudyGroupCard group={item} onPress={() => router.push(`/study-group/${item.id}`)} />
          )}
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
