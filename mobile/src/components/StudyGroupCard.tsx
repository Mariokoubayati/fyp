import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { StudyGroup } from '../api/types';
import { formatDateTime } from '../lib/format';
import { colors, radius, spacing, typography } from '../theme';
import { Card } from './Card';

interface StudyGroupCardProps {
  group: StudyGroup;
  onPress: () => void;
}

export function StudyGroupCard({ group, onPress }: StudyGroupCardProps) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => pressed && styles.pressed}>
      <Card style={styles.card}>
        <View style={styles.header}>
          <Text style={[typography.heading, styles.title]} numberOfLines={2}>
            {group.title}
          </Text>
          {group.is_member ? <Text style={styles.badge}>Joined</Text> : null}
        </View>
        {group.course ? (
          <Text style={typography.caption}>
            {group.course.code} · {group.course.name}
          </Text>
        ) : null}
        <View style={styles.meta}>
          <Ionicons name="time-outline" size={16} color={colors.textMuted} />
          <Text style={typography.caption}>{formatDateTime(group.starts_at)}</Text>
        </View>
        <View style={styles.meta}>
          <Ionicons name="people-outline" size={16} color={colors.textMuted} />
          <Text style={typography.caption}>
            {group.members_count}/{group.capacity} students{group.is_full ? ' · Full' : ''}
          </Text>
        </View>
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pressed: { opacity: 0.85 },
  card: { gap: spacing.xs, marginBottom: spacing.sm },
  header: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  title: { flex: 1 },
  badge: {
    ...typography.caption,
    color: colors.success,
    fontWeight: '700',
    borderWidth: 1,
    borderColor: colors.success,
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    overflow: 'hidden',
  },
  meta: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
});
