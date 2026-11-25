import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { CampusEvent } from '../api/types';
import { formatDateTime } from '../lib/format';
import { colors, radius, spacing, typography } from '../theme';
import { Card } from './Card';
import { TagList } from './TagList';

interface EventCardProps {
  event: CampusEvent;
  onPress: () => void;
}

export function EventCard({ event, onPress }: EventCardProps) {
  const people = event.capacity ? `${event.attendees_count}/${event.capacity} going` : `${event.attendees_count} going`;

  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => pressed && styles.pressed}>
      <Card style={styles.card}>
        <View style={styles.header}>
          <Text style={[typography.heading, styles.title]} numberOfLines={2}>
            {event.title}
          </Text>
          {event.is_going ? <Text style={styles.badge}>Going</Text> : null}
        </View>
        <TagList tags={event.tags} />
        <View style={styles.meta}>
          <Ionicons name="time-outline" size={16} color={colors.textMuted} />
          <Text style={typography.caption}>{formatDateTime(event.starts_at)}</Text>
        </View>
        <View style={styles.meta}>
          <Ionicons name="location-outline" size={16} color={colors.textMuted} />
          <Text style={typography.caption} numberOfLines={1}>
            {event.location}
          </Text>
        </View>
        <View style={styles.meta}>
          <Ionicons name="people-outline" size={16} color={colors.textMuted} />
          <Text style={typography.caption}>
            {people}
            {event.is_full ? ' · Full' : ''}
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
