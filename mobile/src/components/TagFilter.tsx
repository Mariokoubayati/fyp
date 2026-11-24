import { Pressable, ScrollView, StyleSheet, Text } from 'react-native';

import { CampusTag } from '../api/types';
import { TAG_OPTIONS } from '../lib/tags';
import { colors, radius, spacing } from '../theme';

interface TagFilterProps {
  value: CampusTag | null;
  onChange: (tag: CampusTag | null) => void;
}

/** One-line, side-scrolling topic filter: "All topics" plus every tag. */
export function TagFilter({ value, onChange }: TagFilterProps) {
  const options: { value: CampusTag | null; label: string }[] = [{ value: null, label: 'All topics' }, ...TAG_OPTIONS];

  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
      {options.map((option) => {
        const selected = option.value === value;

        return (
          <Pressable
            key={option.label}
            accessibilityRole="button"
            accessibilityState={{ selected }}
            onPress={() => onChange(option.value)}
            style={[styles.chip, selected && styles.selected]}
          >
            <Text style={[styles.label, selected && styles.labelSelected]}>{option.label}</Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: { gap: spacing.xs },
  chip: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.sm,
    paddingVertical: 6,
  },
  selected: { borderColor: colors.primary, backgroundColor: colors.primary },
  label: { fontSize: 13, color: colors.text },
  labelSelected: { color: colors.onPrimary, fontWeight: '600' },
});
