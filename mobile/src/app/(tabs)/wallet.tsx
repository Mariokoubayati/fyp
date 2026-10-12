import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { fetchLoans, LoanScope } from '@/api/loans';
import { Loan } from '@/api/types';
import { Button } from '@/components/Button';
import { EmptyState } from '@/components/EmptyState';
import { LoanCard } from '@/components/LoanCard';
import { Loading } from '@/components/Loading';
import { SegmentedControl } from '@/components/SegmentedControl';
import { colors, spacing, typography } from '@/theme';

const SCOPES: { value: LoanScope; label: string }[] = [
  { value: 'open', label: 'Requests' },
  { value: 'borrowed', label: 'Borrowed' },
  { value: 'lent', label: 'Lent' },
];

const EMPTY: Record<LoanScope, { title: string; message: string }> = {
  open: { title: 'No open requests', message: 'When a classmate asks for a loan it shows up here.' },
  borrowed: { title: 'No loans yet', message: 'Loans you take will be tracked here.' },
  lent: { title: 'Nothing lent yet', message: 'Loans you fund will be tracked here.' },
};

export default function Wallet() {
  const [scope, setScope] = useState<LoanScope>('open');
  const [loans, setLoans] = useState<Loan[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      setError(null);

      fetchLoans(scope)
        .then((result) => active && setLoans(result))
        .catch((e) => active && setError(getErrorMessage(e)))
        .finally(() => active && setLoading(false));

      return () => {
        active = false;
      };
    }, [scope]),
  );

  return (
    <View style={styles.root}>
      <View style={styles.top}>
        <Text style={typography.caption}>
          UniCircle keeps the record only. Hand over and repay cash in person, then both confirm here.
        </Text>
        <SegmentedControl options={SCOPES} value={scope} onChange={setScope} />
        <Button title="Ask for a loan" onPress={() => router.push('/loan/new')} />
        {error ? <Text style={styles.error}>{error}</Text> : null}
      </View>

      {loading ? (
        <Loading />
      ) : (
        <FlatList
          data={loans}
          keyExtractor={(loan) => String(loan.id)}
          contentContainerStyle={styles.list}
          ListEmptyComponent={<EmptyState icon="wallet-outline" title={EMPTY[scope].title} message={EMPTY[scope].message} />}
          renderItem={({ item }) => <LoanCard loan={item} onPress={() => router.push(`/loan/${item.id}`)} />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
  top: { padding: spacing.md, gap: spacing.sm },
  list: { paddingHorizontal: spacing.md, paddingBottom: spacing.lg, flexGrow: 1 },
  error: { ...typography.caption, color: colors.danger },
});
