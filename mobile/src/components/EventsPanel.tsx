import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '../api/errors';
import { EventFilter, fetchEvents } from '../api/events';
import { CampusEvent, CampusTag } from '../api/types';
import { colors, spacing, typography } from '../theme';
import { Button } from './Button';
import { EmptyState } from './EmptyState';
import { EventCard } from './EventCard';
import { Loading } from './Loading';
import { SegmentedControl } from './SegmentedControl';
import { TagFilter } from './TagFilter';

const FILTERS: { value: EventFilter; label: string }[] = [
  { value: 'recommended', label: 'For you' },
  { value: 'all', label: 'All' },
  { value: 'going', label: 'Going' },
  { value: 'mine', label: 'Mine' },
];

const EMPTY_MESSAGES: Record<EventFilter, string> = {
  recommended: 'Add your interests on your profile and upcoming events you would like will show up here.',
  all: 'No upcoming events yet. Post the first one.',
  going: 'Events you say yes to will appear here.',
  mine: 'Events you post will appear here.',
};

/** The events part of the Campus tab. */
export function EventsPanel() {
  const [filter, setFilter] = useState<EventFilter>('recommended');
  const [tag, setTag] = useState<CampusTag | null>(null);
  const [events, setEvents] = useState<CampusEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      setError(null);

      fetchEvents(filter, tag)
        .then((result) => active && setEvents(result))
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
        <Button title="Post an event" onPress={() => router.push('/event/new')} />
        {error ? <Text style={styles.error}>{error}</Text> : null}
      </View>

      {loading ? (
        <Loading />
      ) : (
        <FlatList
          data={events}
          keyExtractor={(event) => String(event.id)}
          contentContainerStyle={styles.list}
          ListEmptyComponent={<EmptyState icon="calendar-outline" title="No events here" message={EMPTY_MESSAGES[filter]} />}
          renderItem={({ item }) => <EventCard event={item} onPress={() => router.push(`/event/${item.id}`)} />}
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
