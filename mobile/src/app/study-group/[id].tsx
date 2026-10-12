import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { deleteStudyGroup, fetchStudyGroup, joinStudyGroup, leaveStudyGroup } from '@/api/studyGroups';
import { StudyGroup } from '@/api/types';
import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { formatDateTime } from '@/lib/format';
import { colors, spacing, typography } from '@/theme';

export default function StudyGroupDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const groupId = Number(id);
  const [group, setGroup] = useState<StudyGroup | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchStudyGroup(groupId)
      .then(setGroup)
      .catch((e) => setError(getErrorMessage(e)));
  }, [groupId]);

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

  if (!group) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  const handleJoin = () => run(async () => setGroup(await joinStudyGroup(group.id)));
  const handleLeave = () =>
    run(async () => {
      await leaveStudyGroup(group.id);
      setGroup(await fetchStudyGroup(group.id));
    });
  const handleDelete = () =>
    run(async () => {
      await deleteStudyGroup(group.id);
      router.back();
    });

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.title}>{group.title}</Text>
        {group.course ? (
          <Text style={typography.caption}>
            {group.course.code} · {group.course.name}
          </Text>
        ) : null}
        <View style={styles.meta}>
          <Ionicons name="time-outline" size={18} color={colors.textMuted} />
          <Text style={typography.body}>{formatDateTime(group.starts_at)}</Text>
        </View>
        {group.location ? (
          <View style={styles.meta}>
            <Ionicons name="location-outline" size={18} color={colors.textMuted} />
            <Text style={typography.body}>{group.location}</Text>
          </View>
        ) : null}
        <View style={styles.meta}>
          <Ionicons name="people-outline" size={18} color={colors.textMuted} />
          <Text style={typography.body}>
            {group.members_count}/{group.capacity} students{group.is_full ? ' · Full' : ''}
          </Text>
        </View>
        {group.creator ? <Text style={typography.caption}>Started by {group.creator.name}</Text> : null}
        {group.description ? <Text style={[typography.body, styles.description]}>{group.description}</Text> : null}
      </Card>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.actions}>
        {group.is_creator ? (
          <Button title="Cancel this group" variant="danger" onPress={handleDelete} loading={busy} />
        ) : group.is_member ? (
          <Button title="Leave group" variant="secondary" onPress={handleLeave} loading={busy} />
        ) : (
          <Button title={group.is_full ? 'Group is full' : 'Join group'} onPress={handleJoin} loading={busy} disabled={group.is_full} />
        )}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.sm, marginBottom: spacing.md },
  meta: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  description: { marginTop: spacing.xs },
  actions: { gap: spacing.sm },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
