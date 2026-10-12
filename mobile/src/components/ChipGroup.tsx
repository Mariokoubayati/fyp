import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, radius, spacing, typography } from '../theme';

interface ChipGroupProps<T extends string | number> {
  options: { value: T; label: string }[];
  value: T | null;
  onChange: (value: T) => void;
}

export function ChipGroup<T extends string | number>({ options, value, onChange }: ChipGroupProps<T>) {
  return (
    <View style={styles.row}>
      {options.map((option) => {
        const selected = option.value === value;

        return (
          <Pressable
            key={option.value}
            accessibilityRole="button"
            accessibilityState={{ selected }}
            onPress={() => onChange(option.value)}
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
