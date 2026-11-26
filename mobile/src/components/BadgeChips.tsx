import { Ionicons } from '@expo/vector-icons';
import { ComponentProps } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { BadgeChip } from '../api/types';
import { colors, radius, spacing } from '../theme';

type IconName = ComponentProps<typeof Ionicons>['name'];

/** Small badge pills next to a name, where a student decides whether to buy, lend or accept. */
export function BadgeChips({ badges }: { badges?: BadgeChip[] }) {
  if (!badges || badges.length === 0) {
    return null;
  }

  return (
    <View style={styles.row}>
      {badges.map((badge) => (
        <View key={badge.key} style={styles.chip} accessible accessibilityLabel={`Badge: ${badge.title}`}>
          <Ionicons name={badge.icon as IconName} size={12} color={colors.onPrimary} />
          <Text style={styles.text}>{badge.title}</Text>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: colors.primary,
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
  },
  text: { fontSize: 12, fontWeight: '600', color: colors.onPrimary },
});
