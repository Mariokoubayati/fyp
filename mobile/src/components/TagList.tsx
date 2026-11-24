import { StyleSheet, Text, View } from 'react-native';

import { CampusTag } from '../api/types';
import { TAG_LABELS } from '../lib/tags';
import { colors, radius, spacing } from '../theme';

/** Small read-only pills: tags, or plain words such as skills. */
export function TagList({ tags, words }: { tags?: CampusTag[]; words?: string[] }) {
  const items = [...(tags ?? []).map((tag) => TAG_LABELS[tag]), ...(words ?? [])];

  if (items.length === 0) {
    return null;
  }

  return (
    <View style={styles.row}>
      {items.map((item) => (
        <Text key={item} style={styles.pill}>
          {item}
        </Text>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  pill: {
    fontSize: 12,
    color: colors.primary,
    backgroundColor: '#EEF2FF',
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    overflow: 'hidden',
  },
});
