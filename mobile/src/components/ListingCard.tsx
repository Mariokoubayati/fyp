import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Listing } from '../api/types';
import { CATEGORY_LABELS, CONDITION_LABELS, formatPrice } from '../lib/listings';
import { colors, radius, spacing, typography } from '../theme';
import { Card } from './Card';

interface ListingCardProps {
  listing: Listing;
  onPress: () => void;
}

export function ListingCard({ listing, onPress }: ListingCardProps) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => pressed && styles.pressed}>
      <Card style={styles.card}>
        <View style={styles.header}>
          <Text style={[typography.heading, styles.title]} numberOfLines={2}>
            {listing.title}
          </Text>
          <Text style={styles.price}>{formatPrice(listing.price)}</Text>
        </View>
        <Text style={typography.caption}>
          {CATEGORY_LABELS[listing.category]} · {CONDITION_LABELS[listing.condition]}
          {listing.seller ? ` · ${listing.seller.name}` : ''}
        </Text>
        {listing.status !== 'available' ? (
          <Text style={[styles.badge, listing.status === 'sold' && styles.badgeSold]}>
            {listing.status === 'sold' ? 'Sold' : 'Reserved'}
          </Text>
        ) : null}
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pressed: { opacity: 0.85 },
  card: { gap: spacing.xs, marginBottom: spacing.sm },
  header: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  title: { flex: 1 },
  price: { ...typography.heading, color: colors.primary },
  badge: {
    ...typography.caption,
    alignSelf: 'flex-start',
    color: colors.textMuted,
    fontWeight: '700',
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    overflow: 'hidden',
  },
  badgeSold: { color: colors.danger, borderColor: colors.danger },
});
