import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, StyleSheet, Text, TextInput, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { fetchListings } from '@/api/listings';
import { Listing, ListingCategory } from '@/api/types';
import { Button } from '@/components/Button';
import { EmptyState } from '@/components/EmptyState';
import { ListingCard } from '@/components/ListingCard';
import { Loading } from '@/components/Loading';
import { SegmentedControl } from '@/components/SegmentedControl';
import { CATEGORY_OPTIONS } from '@/lib/listings';
import { colors, radius, spacing, typography } from '@/theme';

type Scope = 'browse' | 'mine';

const SCOPES: { value: Scope; label: string }[] = [
  { value: 'browse', label: 'Browse' },
  { value: 'mine', label: 'My listings' },
];

const CATEGORIES: { value: ListingCategory | 'all'; label: string }[] = [{ value: 'all', label: 'All' }, ...CATEGORY_OPTIONS];

export default function Market() {
  const [scope, setScope] = useState<Scope>('browse');
  const [category, setCategory] = useState<ListingCategory | 'all'>('all');
  const [search, setSearch] = useState('');
  const [listings, setListings] = useState<Listing[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Refetch on focus (e.g. after posting or selling) and when the filters change.
  useFocusEffect(
    useCallback(() => {
      let active = true;
      setError(null);

      const timer = setTimeout(() => {
        fetchListings({
          search: search.trim(),
          category: category === 'all' ? null : category,
          mine: scope === 'mine',
        })
          .then((result) => active && setListings(result))
          .catch((e) => active && setError(getErrorMessage(e)))
          .finally(() => active && setLoading(false));
      }, 250);

      return () => {
        active = false;
        clearTimeout(timer);
      };
    }, [scope, category, search]),
  );

  return (
    <View style={styles.root}>
      <View style={styles.top}>
        <SegmentedControl options={SCOPES} value={scope} onChange={setScope} />
        <TextInput
          accessibilityLabel="Search listings"
          placeholder="Search books, notes, gear"
          placeholderTextColor={colors.textMuted}
          value={search}
          onChangeText={setSearch}
          autoCapitalize="none"
          autoCorrect={false}
          style={styles.search}
        />
        <FlatList
          horizontal
          showsHorizontalScrollIndicator={false}
          data={CATEGORIES}
          keyExtractor={(item) => item.value}
          contentContainerStyle={styles.categories}
          renderItem={({ item }) => (
            <Text
              onPress={() => setCategory(item.value)}
              accessibilityRole="button"
              style={[styles.category, item.value === category && styles.categorySelected]}
            >
              {item.label}
            </Text>
          )}
        />
        <Button title="Sell an item" onPress={() => router.push('/listing/new')} />
        {error ? <Text style={styles.error}>{error}</Text> : null}
      </View>

      {loading ? (
        <Loading />
      ) : (
        <FlatList
          data={listings}
          keyExtractor={(listing) => String(listing.id)}
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <EmptyState
              icon="pricetag-outline"
              title={scope === 'mine' ? 'You have nothing listed' : 'Nothing for sale yet'}
              message={scope === 'mine' ? 'Items you sell will show up here.' : 'Used books and gear from students will appear here.'}
            />
          }
          renderItem={({ item }) => <ListingCard listing={item} onPress={() => router.push(`/listing/${item.id}`)} />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
  top: { padding: spacing.md, gap: spacing.sm },
  search: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    fontSize: 16,
    color: colors.text,
  },
  categories: { gap: spacing.sm },
  category: {
    ...typography.body,
    fontSize: 14,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    overflow: 'hidden',
  },
  categorySelected: { borderColor: colors.primary, backgroundColor: colors.primary, color: colors.onPrimary },
  list: { paddingHorizontal: spacing.md, paddingBottom: spacing.lg, flexGrow: 1 },
  error: { ...typography.caption, color: colors.danger },
});
