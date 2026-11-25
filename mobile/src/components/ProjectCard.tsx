import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Project } from '../api/types';
import { colors, radius, spacing, typography } from '../theme';
import { Card } from './Card';
import { TagList } from './TagList';

interface ProjectCardProps {
  project: Project;
  onPress: () => void;
}

const STATUS_LABELS = { pending: 'Requested', accepted: 'On the team', declined: 'Declined' } as const;

export function ProjectCard({ project, onPress }: ProjectCardProps) {
  const status = project.is_owner ? 'Yours' : project.my_status ? STATUS_LABELS[project.my_status] : null;

  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => pressed && styles.pressed}>
      <Card style={styles.card}>
        <View style={styles.header}>
          <Text style={[typography.heading, styles.title]} numberOfLines={2}>
            {project.title}
          </Text>
          {status ? <Text style={styles.badge}>{status}</Text> : null}
        </View>
        <Text style={typography.caption} numberOfLines={2}>
          {project.description}
        </Text>
        <TagList tags={project.tags} />
        <View style={styles.meta}>
          <Ionicons name="construct-outline" size={16} color={colors.textMuted} />
          <Text style={typography.caption} numberOfLines={1}>
            Needs {project.skills_needed.join(', ')}
          </Text>
        </View>
        <View style={styles.meta}>
          <Ionicons name="people-outline" size={16} color={colors.textMuted} />
          <Text style={typography.caption}>
            {project.members_count}/{project.team_size} on the team
            {!project.is_open ? ' · Closed' : project.is_full ? ' · Full' : ''}
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
    color: colors.primary,
    fontWeight: '700',
    borderWidth: 1,
    borderColor: colors.primary,
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    overflow: 'hidden',
  },
  meta: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
});
