import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { StyleSheet, Text } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { fetchEvent, updateEvent } from '@/api/events';
import { CampusEvent } from '@/api/types';
import { EventForm } from '@/components/EventForm';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { colors, typography } from '@/theme';

export default function EditEvent() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const eventId = Number(id);
  const [event, setEvent] = useState<CampusEvent | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchEvent(eventId)
      .then(setEvent)
      .catch((e) => setError(getErrorMessage(e)));
  }, [eventId]);

  if (!event) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  return (
    <EventForm
      initial={event}
      submitLabel="Save changes"
      onSubmit={async (payload) => {
        await updateEvent(event.id, payload);
        router.back();
      }}
    />
  );
}

const styles = StyleSheet.create({
  error: { ...typography.caption, color: colors.danger },
});
