import { router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { getErrorMessage, getFieldErrors } from '@/api/errors';
import { Button } from '@/components/Button';
import { Input } from '@/components/Input';
import { Screen } from '@/components/Screen';
import { useAuth } from '@/context/AuthContext';
import { colors, spacing, typography } from '@/theme';


export default function SignInScreen() {
  const { signIn } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit() {
    setSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    try {
      await signIn(email.trim().toLowerCase(), password);
    } catch (error) {
      const fields = getFieldErrors(error);
      setFieldErrors(fields);
      setFormError(Object.keys(fields).length > 0 ? null : getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Screen scroll>
      <View style={styles.header}>
        <Text style={typography.title}>UniCircle</Text>
        <Text style={typography.caption}>Your campus, in one place.</Text>
      </View>

      <Input
        label="University email"
        value={email}
        onChangeText={setEmail}
        error={fieldErrors.email}
        autoCapitalize="none"
        autoComplete="email"
        autoCorrect={false}
        keyboardType="email-address"
      />
      <Input
        label="Password"
        value={password}
        onChangeText={setPassword}
        error={fieldErrors.password}
        autoCapitalize="none"
        autoComplete="current-password"
        secureTextEntry
      />

      {formError ? <Text style={styles.formError}>{formError}</Text> : null}

      <Button title="Sign in" onPress={handleSubmit} loading={submitting} />
      <View style={styles.spacer} />
      <Button title="Create an account" variant="secondary" onPress={() => router.push('/register')} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: { marginTop: spacing.xl, marginBottom: spacing.xl, gap: spacing.xs },
  formError: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
  spacer: { height: spacing.sm },
});
