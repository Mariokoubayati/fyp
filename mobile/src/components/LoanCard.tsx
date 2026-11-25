import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Loan } from '../api/types';
import { STATUS_LABELS } from '../lib/loans';
import { colors, spacing, typography } from '../theme';
import { Card } from './Card';
import { BadgeChips } from './BadgeChips';

interface LoanCardProps {
  loan: Loan;
  onPress: () => void;
}

export function LoanCard({ loan, onPress }: LoanCardProps) {
  const other = loan.my_role === 'borrower' ? loan.lender : loan.borrower;
  const counterpart = other?.name;
  const isLate = loan.status === 'overdue' || loan.status === 'defaulted';

  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => pressed && styles.pressed}>
      <Card style={styles.card}>
        <View style={styles.header}>
          <Text style={typography.heading}>${loan.amount.toFixed(2)}</Text>
          <Text style={[styles.status, isLate && styles.late]}>{STATUS_LABELS[loan.status]}</Text>
        </View>
        {counterpart ? <Text style={typography.caption}>{counterpart}</Text> : null}
        <BadgeChips badges={other?.badges} />
        <Text style={typography.caption}>Due {loan.due_date}</Text>
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pressed: { opacity: 0.85 },
  card: { gap: spacing.xs, marginBottom: spacing.sm },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  status: { ...typography.caption, fontWeight: '700', color: colors.primary },
  late: { color: colors.danger },
});
