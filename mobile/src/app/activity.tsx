import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { fetchMyActivity } from '@/api/activity';
import { getErrorMessage } from '@/api/errors';
import { Activity } from '@/api/types';
import { BadgeGrid } from '@/components/BadgeGrid';
import { BarChart } from '@/components/BarChart';
import { Card } from '@/components/Card';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { colors, spacing, typography } from '@/theme';

const ACTIVITY_SERIES = [
  { key: 'marketplace', label: 'Marketplace', color: colors.primary },
  { key: 'loans', label: 'Loans', color: '#0EA5E9' },
  { key: 'groups', label: 'Study groups', color: '#F59E0B' },
  { key: 'events', label: 'Events', color: '#10B981' },
];

const MONEY_SERIES = [
  { key: 'earned', label: 'Earned', color: colors.success },
  { key: 'spent', label: 'Spent', color: '#F97316' },
];

function money(value: number): string {
  return `$${Number.isInteger(value) ? value : value.toFixed(2)}`;
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <View style={styles.stat}>
      <Text style={styles.statValue}>{value}</Text>
      <Text style={typography.caption}>{label}</Text>
    </View>
  );
}

export default function MyActivity() {
  const [activity, setActivity] = useState<Activity | null>(null);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      fetchMyActivity()
        .then((data) => {
          setActivity(data);
          setError(null);
        })
        .catch((e) => setError(getErrorMessage(e)));
    }, []),
  );

  if (!activity) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  const { marketplace, lending, groups, events, projects, months, badges } = activity;
  const earnedCount = badges.filter((badge) => badge.earned_at !== null).length;
  const labels = months.map((month) => month.label);
  const repaid = lending.repaid_on_time + lending.repaid_late;

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.heading}>Badges</Text>
        <Text style={typography.caption}>
          {earnedCount} of {badges.length} earned. Badges show on your profile; they never change your trust score.
        </Text>
        <BadgeGrid badges={badges} />
      </Card>

      <Card style={styles.card}>
        <Text style={typography.heading}>This semester</Text>
        <Text style={typography.caption}>What you did each month</Text>
        <BarChart labels={labels} series={ACTIVITY_SERIES} values={months as unknown as Record<string, number>[]} />
      </Card>

      <Card style={styles.card}>
        <Text style={typography.heading}>Marketplace</Text>
        <View style={styles.stats}>
          <Stat label={`Sold (${marketplace.sold})`} value={money(marketplace.earned)} />
          <Stat label={`Bought (${marketplace.bought})`} value={money(marketplace.spent)} />
          <Stat label="For sale now" value={marketplace.for_sale} />
        </View>
        {marketplace.earned + marketplace.spent > 0 ? (
          <BarChart
            labels={labels}
            series={MONEY_SERIES}
            values={months as unknown as Record<string, number>[]}
            mode="grouped"
            height={90}
            formatTotal={money}
          />
        ) : null}
      </Card>

      <Card style={styles.card}>
        <Text style={typography.heading}>Lending</Text>
        <View style={styles.stats}>
          <Stat label={`Borrowed (${lending.borrowed})`} value={money(lending.borrowed_amount)} />
          <Stat label={`Lent (${lending.lent})`} value={money(lending.lent_amount)} />
          <Stat label="Open loans" value={lending.open} />
        </View>
        {repaid > 0 ? (
          <Text style={typography.caption}>
            You paid back {lending.repaid_on_time} of {repaid} {repaid === 1 ? 'loan' : 'loans'} on time.
          </Text>
        ) : null}
      </Card>

      <Card style={styles.card}>
        <Text style={typography.heading}>Study groups</Text>
        <View style={styles.stats}>
          <Stat label="Joined" value={groups.joined} />
          <Stat label="Started" value={groups.started} />
          <Stat label="Coming up" value={groups.upcoming} />
        </View>
      </Card>

      <Card style={styles.card}>
        <Text style={typography.heading}>Events and projects</Text>
        <View style={styles.stats}>
          <Stat label="Events went to" value={events.went} />
          <Stat label="Coming up" value={events.upcoming} />
          <Stat label="Hosted" value={events.hosted} />
        </View>
        <View style={styles.stats}>
          <Stat label="Projects you lead" value={projects.owned} />
          <Stat label="Teams you joined" value={projects.joined} />
          <View style={styles.stat} />
        </View>
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.sm, marginBottom: spacing.md },
  stats: { flexDirection: 'row', gap: spacing.sm },
  stat: { flex: 1 },
  statValue: { ...typography.heading, color: colors.primary },
  error: { ...typography.caption, color: colors.danger },
});
