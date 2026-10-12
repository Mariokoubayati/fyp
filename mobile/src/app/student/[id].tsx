import { useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { fetchStudent, vouchFor, withdrawVouch } from '@/api/students';
import { StudentProfile } from '@/api/types';
import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { TIER_LABELS } from '@/lib/loans';
import { colors, spacing, typography } from '@/theme';

export default function StudentScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const studentId = Number(id);
  const [student, setStudent] = useState<StudentProfile | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchStudent(studentId)
      .then(setStudent)
      .catch((e) => setError(getErrorMessage(e)));
  }, [studentId]);

  const toggleVouch = useCallback(async () => {
    if (!student) {
      return;
    }

    setBusy(true);
    setError(null);

    try {
      await (student.vouched_by_me ? withdrawVouch(student.id) : vouchFor(student.id));
      setStudent(await fetchStudent(student.id));
    } catch (e) {
      setError(getErrorMessage(e));
    } finally {
      setBusy(false);
    }
  }, [student]);

  if (!student) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  const details = [student.major, student.study_year ? `Year ${student.study_year}` : null].filter(Boolean).join(' · ');

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.title}>{student.name}</Text>
        {details ? <Text style={typography.caption}>{details}</Text> : null}
        {student.bio ? <Text style={typography.body}>{student.bio}</Text> : null}
        <Text style={styles.trust}>
          Trust {student.trust.score} · {TIER_LABELS[student.trust.tier]}
        </Text>
        <Text style={typography.caption}>
          {student.vouch_count} {student.vouch_count === 1 ? 'vouch' : 'vouches'} from classmates
        </Text>
      </Card>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Button
        title={student.vouched_by_me ? 'Withdraw my vouch' : 'Vouch for this student'}
        variant={student.vouched_by_me ? 'secondary' : 'primary'}
        onPress={toggleVouch}
        loading={busy}
      />
      <Text style={[typography.caption, styles.note]}>Only vouch for classmates you personally know and would trust.</Text>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.sm, marginBottom: spacing.md },
  trust: { ...typography.heading, color: colors.primary },
  note: { marginTop: spacing.sm },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
