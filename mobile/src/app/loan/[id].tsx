import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { actOnLoan, fetchLoan } from '@/api/loans';
import { Loan } from '@/api/types';
import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { nextStep, STATUS_LABELS, TIER_LABELS } from '@/lib/loans';
import { colors, spacing, typography } from '@/theme';

export default function LoanDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const loanId = Number(id);
  const [loan, setLoan] = useState<Loan | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchLoan(loanId)
      .then(setLoan)
      .catch((e) => setError(getErrorMessage(e)));
  }, [loanId]);

  const act = useCallback(
    async (action: Parameters<typeof actOnLoan>[1]) => {
      setBusy(true);
      setError(null);

      try {
        setLoan(await actOnLoan(loanId, action));
      } catch (e) {
        setError(getErrorMessage(e));
      } finally {
        setBusy(false);
      }
    },
    [loanId],
  );

  if (!loan) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  const step = nextStep(loan);
  const canCancel =
    (loan.my_role === 'borrower' && ['requested', 'accepted'].includes(loan.status)) ||
    (loan.my_role === 'lender' && loan.status === 'accepted');
  const isLate = loan.status === 'overdue' || loan.status === 'defaulted';

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.title}>${loan.amount.toFixed(2)}</Text>
        <Text style={[styles.status, isLate && styles.late]}>{STATUS_LABELS[loan.status]}</Text>
        <Text style={typography.body}>Repay by {loan.due_date}</Text>
        {loan.purpose ? <Text style={typography.body}>{loan.purpose}</Text> : null}
        {loan.borrower ? (
          <Text style={typography.caption}>
            Borrower: {loan.borrower.name} · Trust {loan.borrower.trust_score} ({TIER_LABELS[loan.borrower.trust_tier]})
          </Text>
        ) : null}
        {loan.lender ? <Text style={typography.caption}>Lender: {loan.lender.name}</Text> : null}
      </Card>

      {step?.kind === 'confirm-handover' ? (
        <Text style={[typography.caption, styles.note]}>
          {step.alreadyConfirmed
            ? 'You confirmed the handover. Waiting for the other student.'
            : 'Meet up and hand over the cash, then confirm. The loan starts when both of you confirm.'}
        </Text>
      ) : null}
      {step?.kind === 'confirm-repayment' ? (
        <Text style={[typography.caption, styles.note]}>
          {step.alreadyConfirmed
            ? 'You confirmed the repayment. Waiting for the other student.'
            : 'Once the money is paid back, both of you confirm to close the loan.'}
        </Text>
      ) : null}

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.actions}>
        {step?.kind === 'accept' ? <Button title="Fund this loan" onPress={() => act('accept')} loading={busy} /> : null}
        {step?.kind === 'confirm-handover' && !step.alreadyConfirmed ? (
          <Button title="Confirm cash handed over" onPress={() => act('confirm-handover')} loading={busy} />
        ) : null}
        {step?.kind === 'confirm-repayment' && !step.alreadyConfirmed ? (
          <Button title="Confirm repayment" onPress={() => act('confirm-repayment')} loading={busy} />
        ) : null}
        {canCancel ? (
          <Button
            title={loan.my_role === 'borrower' ? 'Cancel request' : 'Back out'}
            variant="secondary"
            onPress={async () => {
              await act('cancel');
              router.back();
            }}
            loading={busy}
          />
        ) : null}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.sm, marginBottom: spacing.md },
  status: { ...typography.heading, color: colors.primary },
  late: { color: colors.danger },
  note: { marginBottom: spacing.md },
  actions: { gap: spacing.sm },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
