import { Ionicons } from '@expo/vector-icons';
import { ComponentProps } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { BadgeProgress, EarnedBadge } from '../api/types';
import { colors, radius, spacing, typography } from '../theme';

type IconName = ComponentProps<typeof Ionicons>['name'];

function isProgress(badge: EarnedBadge | BadgeProgress): badge is BadgeProgress {
  return 'goal' in badge;
}

/**
 * Badges as tiles. Earned ones are in color; the others are grey with progress ("2/3").
 * Pass earned badges only (a classmate's profile) or every badge with progress (My activity).
 */
export function BadgeGrid({ badges }: { badges: (EarnedBadge | BadgeProgress)[] }) {
  return (
    <View style={styles.grid}>
      {badges.map((badge) => {
        const earned = badge.earned_at !== null;

        return (
          <View
            key={badge.key}
            style={[styles.tile, !earned && styles.locked]}
            accessible
            accessibilityLabel={`${badge.title}${earned ? ', earned' : isProgress(badge) ? `, ${badge.progress} of ${badge.goal}` : ''}. ${badge.description}`}
          >
            <View style={[styles.icon, earned ? styles.iconEarned : styles.iconLocked]}>
              <Ionicons name={badge.icon as IconName} size={22} color={earned ? colors.onPrimary : colors.textMuted} />
            </View>
            <Text style={[styles.title, !earned && styles.muted]} numberOfLines={2}>
              {badge.title}
            </Text>
            <Text style={styles.description} numberOfLines={3}>
              {badge.description}
            </Text>
            {!earned && isProgress(badge) ? (
              <View style={styles.progress}>
                <View style={[styles.progressFill, { width: `${(badge.progress / badge.goal) * 100}%` }]} />
              </View>
            ) : null}
            {!earned && isProgress(badge) ? (
              <Text style={styles.count}>
                {badge.progress}/{badge.goal}
              </Text>
            ) : null}
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  tile: {
    width: '31%',
    flexGrow: 1,
    minWidth: 96,
    alignItems: 'center',
    gap: spacing.xs,
    padding: spacing.sm,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  locked: { backgroundColor: colors.background },
  icon: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  iconEarned: { backgroundColor: colors.primary },
  iconLocked: { backgroundColor: colors.border },
  title: { fontSize: 13, fontWeight: '600', color: colors.text, textAlign: 'center' },
  muted: { color: colors.textMuted },
  description: { ...typography.caption, fontSize: 11, textAlign: 'center' },
  progress: { width: '100%', height: 4, borderRadius: 2, backgroundColor: colors.border, overflow: 'hidden' },
  progressFill: { height: '100%', backgroundColor: colors.primary },
  count: { fontSize: 11, color: colors.textMuted },
});
