import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { StyleSheet, Text } from 'react-native';

import { getErrorMessage, getFieldErrors } from '@/api/errors';
import { fetchMyTrust, requestLoan } from '@/api/loans';
import { TrustSummary } from '@/api/types';
import { Button } from '@/components/Button';
import { Input } from '@/components/Input';
import { Screen } from '@/components/Screen';
import { TIER_LABELS } from '@/lib/loans';
import { colors, spacing, typography } from '@/theme';

function defaultDueDate(): string {
  const date = new Date();
  date.setDate(date.getDate() + 14);

  return date.toISOString().slice(0, 10);
}

export default function NewLoan() {
  const [trust, setTrust] = useState<TrustSummary | null>(null);
  const [amount, setAmount] = useState('');
  const [purpose, setPurpose] = useState('');
  const [dueDate, setDueDate] = useState(defaultDueDate);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    fetchMyTrust()
      .then(setTrust)
      .catch(() => setTrust(null));
  }, []);

  async function handleSubmit() {
    setSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    try {
      const loan = await requestLoan({
        amount: Number(amount.replace(',', '.')),
        purpose: purpose.trim() || null,
        due_date: dueDate.trim(),
      });

      router.replace(`/loan/${loan.id}`);
    } catch (error) {
      const fields = getFieldErrors(error);
      setFieldErrors(fields);
      setFormError(Object.keys(fields).length > 0 ? null : getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Screen scroll safeArea={false}>
      {trust ? (
        <Text style={[typography.caption, styles.hint]}>
          Trust tier: {TIER_LABELS[trust.tier]}. You can borrow up to ${trust.max_loan}.
        </Text>
      ) : null}

      <Input
        label="Amount (USD)"
        value={amount}
        onChangeText={setAmount}
        error={fieldErrors.amount}
        keyboardType="decimal-pad"
      />
      <Input label="What is it for? (optional)" value={purpose} onChangeText={setPurpose} error={fieldErrors.purpose} />
      <Input
        label="Repay by (YYYY-MM-DD)"
        value={dueDate}
        onChangeText={setDueDate}
        error={fieldErrors.due_date}
        autoCapitalize="none"
        autoCorrect={false}
      />

      {formError ? <Text style={styles.error}>{formError}</Text> : null}

      <Button title="Post request" onPress={handleSubmit} loading={submitting} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  hint: { marginBottom: spacing.md },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
