import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { fetchHomeFeed, HomeFeed } from '@/api/recommendations';
import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { EmptyState } from '@/components/EmptyState';
import { ListingCard } from '@/components/ListingCard';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { StudyGroupCard } from '@/components/StudyGroupCard';
import { useAuth } from '@/context/AuthContext';
import { colors, spacing, typography } from '@/theme';

export default function Home() {
  const { user } = useAuth();
  const [feed, setFeed] = useState<HomeFeed | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Reload on focus so joins and purchases made elsewhere are reflected.
  useFocusEffect(
    useCallback(() => {
      let active = true;
      setError(null);

      fetchHomeFeed()
        .then((result) => active && setFeed(result))
        .catch((e) => active && setError(getErrorMessage(e)));

      return () => {
        active = false;
      };
    }, []),
  );

  const empty = feed !== null && feed.groups.length === 0 && feed.listings.length === 0;

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.greeting}>
        <Text style={typography.heading}>Hi, {user?.name}</Text>
        <Text style={typography.caption}>
          {feed?.source === 'model'
            ? 'Picked for you from your courses and the people you study with.'
            : 'Study groups and items for your courses.'}
        </Text>
      </Card>

      {error ? <Text style={styles.error}>{error}</Text> : null}
      {feed === null && error === null ? <Loading /> : null}

      {empty ? (
        <View style={styles.empty}>
          <EmptyState
            icon="sparkles-outline"
            title="Nothing to suggest yet"
            message="Add your courses and we will suggest study groups and items for them."
          />
          <Button title="Add my courses" onPress={() => router.push('/courses')} />
        </View>
      ) : null}

      {feed && feed.groups.length > 0 ? (
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={typography.heading}>Study groups for you</Text>
            <Text style={styles.link} onPress={() => router.push('/groups')}>
              See all
            </Text>
          </View>
          {feed.groups.map((group) => (
            <StudyGroupCard key={group.id} group={group} onPress={() => router.push(`/study-group/${group.id}`)} />
          ))}
        </View>
      ) : null}

      {feed && feed.listings.length > 0 ? (
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={typography.heading}>For your courses</Text>
            <Text style={styles.link} onPress={() => router.push('/market')}>
              See all
            </Text>
          </View>
          {feed.listings.map((listing) => (
            <ListingCard key={listing.id} listing={listing} onPress={() => router.push(`/listing/${listing.id}`)} />
          ))}
        </View>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  greeting: { gap: spacing.xs, marginBottom: spacing.md },
  section: { gap: spacing.sm, marginBottom: spacing.lg },
  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  link: { ...typography.caption, color: colors.primary },
  empty: { gap: spacing.md },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.sm },
});
