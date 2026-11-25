import { Pressable, StyleSheet, Text, View } from 'react-native';

import { CampusTag } from '../api/types';
import { TAG_OPTIONS } from '../lib/tags';
import { colors, radius, spacing, typography } from '../theme';

interface TagPickerProps {
  value: CampusTag[];
  onChange: (tags: CampusTag[]) => void;
  /** Most tags that can be picked; tapping another one when full does nothing. */
  max?: number;
}

/** Multi-select chips for interests, event tags and project tags. */
export function TagPicker({ value, onChange, max }: TagPickerProps) {
  function toggle(tag: CampusTag) {
    if (value.includes(tag)) {
      onChange(value.filter((t) => t !== tag));
    } else if (max === undefined || value.length < max) {
      onChange([...value, tag]);
    }
  }

  return (
    <View style={styles.row}>
      {TAG_OPTIONS.map((option) => {
        const selected = value.includes(option.value);

        return (
          <Pressable
            key={option.value}
            accessibilityRole="checkbox"
            accessibilityState={{ checked: selected }}
            onPress={() => toggle(option.value)}
            style={[styles.chip, selected && styles.selected]}
          >
            <Text style={[styles.label, selected && styles.labelSelected]}>{option.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  chip: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  selected: { borderColor: colors.primary, backgroundColor: colors.primary },
  label: { ...typography.body, fontSize: 14 },
  labelSelected: { color: colors.onPrimary, fontWeight: '600' },
});
