import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { cancelRsvp, deleteEvent, fetchEvent, rsvpEvent } from '@/api/events';
import { CampusEvent } from '@/api/types';
import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { TagList } from '@/components/TagList';
import { formatDateTime } from '@/lib/format';
import { colors, spacing, typography } from '@/theme';

export default function EventDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const eventId = Number(id);
  const [event, setEvent] = useState<CampusEvent | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchEvent(eventId)
      .then(setEvent)
      .catch((e) => setError(getErrorMessage(e)));
  }, [eventId]);

  const run = useCallback(async (action: () => Promise<void>) => {
    setBusy(true);
    setError(null);

    try {
      await action();
    } catch (e) {
      setError(getErrorMessage(e));
    } finally {
      setBusy(false);
    }
  }, []);

  if (!event) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  const handleGoing = () => run(async () => setEvent(await rsvpEvent(event.id)));
  const handleNotGoing = () =>
    run(async () => {
      await cancelRsvp(event.id);
      setEvent(await fetchEvent(event.id));
    });
  const handleCancel = () =>
    run(async () => {
      await deleteEvent(event.id);
      router.back();
    });

  const people = event.capacity ? `${event.attendees_count}/${event.capacity} going` : `${event.attendees_count} going`;
  const ends = event.ends_at ? ` to ${new Date(event.ends_at).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}` : '';

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.title}>{event.title}</Text>
        <TagList tags={event.tags} />
        {event.course ? (
          <Text style={typography.caption}>
            {event.course.code} · {event.course.name}
          </Text>
        ) : null}
        <View style={styles.meta}>
          <Ionicons name="time-outline" size={18} color={colors.textMuted} />
          <Text style={typography.body}>
            {formatDateTime(event.starts_at)}
            {ends}
          </Text>
        </View>
        <View style={styles.meta}>
          <Ionicons name="location-outline" size={18} color={colors.textMuted} />
          <Text style={typography.body}>{event.location}</Text>
        </View>
        <View style={styles.meta}>
          <Ionicons name="people-outline" size={18} color={colors.textMuted} />
          <Text style={typography.body}>
            {people}
            {event.is_full ? ' · Full' : ''}
          </Text>
        </View>
        {event.creator ? (
          <Text style={[typography.caption, styles.link]} onPress={() => router.push(`/student/${event.creator?.id}`)}>
            Posted by {event.creator.name}
          </Text>
        ) : null}
        {event.description ? <Text style={[typography.body, styles.description]}>{event.description}</Text> : null}
      </Card>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.actions}>
        {event.is_creator ? (
          <Button title="Cancel this event" variant="danger" onPress={handleCancel} loading={busy} />
        ) : event.has_started ? (
          <Button title="This event has started" disabled onPress={() => undefined} />
        ) : event.is_going ? (
          <Button title="I can't go anymore" variant="secondary" onPress={handleNotGoing} loading={busy} />
        ) : (
          <Button title={event.is_full ? 'Event is full' : "I'm going"} onPress={handleGoing} loading={busy} disabled={event.is_full} />
        )}
      </View>
      {event.is_going && !event.is_creator ? (
        <Text style={[typography.caption, styles.note]}>We will remind you the day before.</Text>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.sm, marginBottom: spacing.md },
  meta: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  link: { color: colors.primary },
  description: { marginTop: spacing.xs },
  actions: { gap: spacing.sm },
  note: { marginTop: spacing.sm, textAlign: 'center' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
