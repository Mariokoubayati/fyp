import { StyleSheet, Text, View } from 'react-native';

import { TrustSummary } from '../api/types';
import { TIER_LABELS } from '../lib/loans';
import { colors, spacing, typography } from '../theme';
import { Card } from './Card';

const COMPONENT_LABELS: Record<keyof TrustSummary['breakdown'], string> = {
  repayment: 'Loan repayment',
  marketplace: 'Marketplace',
  vouches: 'Vouches',
  account: 'Account',
  engagement: 'Engagement',
};

export function TrustCard({ trust }: { trust: TrustSummary }) {
  return (
    <Card style={styles.card}>
      <View style={styles.header}>
        <Text style={styles.score}>{trust.score}</Text>
        <View>
          <Text style={typography.heading}>Trust score</Text>
          <Text style={typography.caption}>
            {TIER_LABELS[trust.tier]} · borrow up to ${trust.max_loan}
          </Text>
        </View>
      </View>

      {(Object.keys(COMPONENT_LABELS) as (keyof TrustSummary['breakdown'])[]).map((key) => {
        const points = trust.breakdown[key];

        return (
          <View key={key} style={styles.row}>
            <Text style={typography.caption}>{COMPONENT_LABELS[key]}</Text>
            <Text style={[styles.points, points < 0 && styles.negative]}>
              {points > 0 ? `+${points}` : points}
            </Text>
          </View>
        );
      })}
    </Card>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.xs, marginBottom: spacing.md },
  header: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, marginBottom: spacing.sm },
  score: { fontSize: 40, fontWeight: '800', color: colors.primary },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  points: { ...typography.caption, fontWeight: '700', color: colors.success },
  negative: { color: colors.danger },
});
