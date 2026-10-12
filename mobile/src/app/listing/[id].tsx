import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { deleteListing, fetchListing, markListingSold, rateSeller, setListingStatus } from '@/api/listings';
import { Listing, Student } from '@/api/types';
import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { StudentPicker } from '@/components/StudentPicker';
import { CATEGORY_LABELS, CONDITION_LABELS, formatPrice } from '@/lib/listings';
import { colors, spacing, typography } from '@/theme';

export default function ListingDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const listingId = Number(id);
  const [listing, setListing] = useState<Listing | null>(null);
  const [choosingBuyer, setChoosingBuyer] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchListing(listingId)
      .then(setListing)
      .catch((e) => setError(getErrorMessage(e)));
  }, [listingId]);

  const run = useCallback(async (action: () => Promise<void>) => {
    setBusy(true);
    setError(null);

    try {
      await action();
    } catch (e) {
      setError(getErrorMessage(e));
    } finally {
      setBusy(false);
    }
  }, []);

  if (!listing) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  const toggleReserved = () =>
    run(async () => setListing(await setListingStatus(listing.id, listing.status === 'reserved' ? 'available' : 'reserved')));
  const sellTo = (student: Student) =>
    run(async () => {
      setListing(await markListingSold(listing.id, student.id));
      setChoosingBuyer(false);
    });
  const rate = (stars: number) =>
    run(async () => {
      await rateSeller(listing.id, stars);
      setListing(await fetchListing(listing.id));
    });
  const remove = () =>
    run(async () => {
      await deleteListing(listing.id);
      router.back();
    });

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.title}>{listing.title}</Text>
        <Text style={styles.price}>{formatPrice(listing.price)}</Text>
        <Text style={typography.caption}>
          {CATEGORY_LABELS[listing.category]} · {CONDITION_LABELS[listing.condition]}
          {listing.course ? ` · ${listing.course.code}` : ''}
        </Text>
        {listing.status !== 'available' ? (
          <Text style={styles.status}>{listing.status === 'sold' ? 'Sold' : 'Reserved'}</Text>
        ) : null}
        {listing.description ? <Text style={[typography.body, styles.description]}>{listing.description}</Text> : null}
        {listing.seller ? (
          <Text style={styles.sellerLink} onPress={() => router.push(`/student/${listing.seller?.id}`)}>
            Sold by {listing.seller.name}
          </Text>
        ) : null}
      </Card>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {listing.is_buyer && listing.status === 'sold' ? (
        <Card style={styles.card}>
          {listing.rating ? (
            <Text style={typography.body}>You rated this seller {listing.rating.stars}/5. Thank you!</Text>
          ) : (
            <>
              <Text style={typography.heading}>How was the seller?</Text>
              <View style={styles.stars}>
                {[1, 2, 3, 4, 5].map((stars) => (
                  <Button key={stars} title={String(stars)} variant="secondary" onPress={() => rate(stars)} disabled={busy} />
                ))}
              </View>
            </>
          )}
        </Card>
      ) : null}

      {listing.is_seller && listing.status !== 'sold' ? (
        <View style={styles.actions}>
          {choosingBuyer ? (
            <>
              <Text style={typography.heading}>Who bought it?</Text>
              <StudentPicker onPick={sellTo} />
              <Button title="Cancel" variant="secondary" onPress={() => setChoosingBuyer(false)} />
            </>
          ) : (
            <>
              <Button title="Mark as sold" onPress={() => setChoosingBuyer(true)} loading={busy} />
              <Button
                title={listing.status === 'reserved' ? 'Make available again' : 'Mark as reserved'}
                variant="secondary"
                onPress={toggleReserved}
                loading={busy}
              />
              <Button title="Delete listing" variant="danger" onPress={remove} loading={busy} />
            </>
          )}
        </View>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.sm, marginBottom: spacing.md },
  price: { ...typography.heading, color: colors.primary },
  status: { ...typography.caption, fontWeight: '700', color: colors.danger },
  description: { marginTop: spacing.xs },
  actions: { gap: spacing.sm },
  stars: { flexDirection: 'row', gap: spacing.sm, flexWrap: 'wrap' },
  sellerLink: { ...typography.caption, color: colors.primary, fontWeight: '600' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
