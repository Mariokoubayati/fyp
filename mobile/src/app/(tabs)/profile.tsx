import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { resendVerificationEmail } from '@/api/auth';
import { fetchMyTrust } from '@/api/loans';
import { TrustSummary } from '@/api/types';
import { getErrorMessage } from '@/api/errors';
import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Screen } from '@/components/Screen';
import { TrustCard } from '@/components/TrustCard';
import { useAuth } from '@/context/AuthContext';
import { colors, spacing, typography } from '@/theme';

export default function Profile() {
  const { user, signOut } = useAuth();
  const [resendMessage, setResendMessage] = useState<string | null>(null);
  const [resending, setResending] = useState(false);
  const [trust, setTrust] = useState<TrustSummary | null>(null);

  useFocusEffect(
    useCallback(() => {
      fetchMyTrust()
        .then(setTrust)
        .catch(() => setTrust(null));
    }, []),
  );

  async function handleResend() {
    setResending(true);

    try {
      await resendVerificationEmail();
      setResendMessage('Verification link sent. Check your university inbox.');
    } catch (error) {
      setResendMessage(getErrorMessage(error));
    } finally {
      setResending(false);
    }
  }

  const details = [user?.major, user?.study_year ? `Year ${user.study_year}` : null].filter(Boolean).join(' · ');

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.heading}>{user?.name}</Text>
        <Text style={typography.caption}>{user?.email}</Text>
        {details ? <Text style={typography.body}>{details}</Text> : null}
        {user?.bio ? <Text style={typography.body}>{user.bio}</Text> : null}
      </Card>

      {trust ? <TrustCard trust={trust} /> : null}

      {user && !user.email_verified_at ? (
        <Card style={[styles.card, styles.banner]}>
          <Text style={typography.heading}>Verify your email</Text>
          <Text style={typography.caption}>
            Confirm your university address so other students can trust your account.
          </Text>
          {resendMessage ? <Text style={typography.caption}>{resendMessage}</Text> : null}
          <Button title="Resend verification email" variant="secondary" onPress={handleResend} loading={resending} />
        </Card>
      ) : null}

      <View style={styles.actions}>
        <Button title="Edit profile" variant="secondary" onPress={() => router.push('/edit-profile')} />
        <Button title="My courses" variant="secondary" onPress={() => router.push('/courses')} />
        <Button title="Sign out" variant="secondary" onPress={signOut} />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.xs, marginBottom: spacing.md },
  banner: { borderColor: colors.primary, borderWidth: 1, gap: spacing.sm },
  actions: { gap: spacing.sm },
});
