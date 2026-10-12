import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { Button } from '../../components/Button';
import { Input } from '../../components/Input';
import { Screen } from '../../components/Screen';
import { useAuth } from '../../context/AuthContext';
import { AuthStackParamList } from '../../navigation/types';
import { colors, spacing, typography } from '../../theme';

type Props = NativeStackScreenProps<AuthStackParamList, 'Register'>;

export function RegisterScreen({ navigation }: Props) {
  const { signUp } = useAuth();
  const [name, setName] = useState('');
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
      await signUp(name.trim(), email.trim().toLowerCase(), password);
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
        <Text style={typography.title}>Create account</Text>
        <Text style={typography.caption}>Join your campus community.</Text>
      </View>

      <Input
        label="Full name"
        value={name}
        onChangeText={setName}
        error={fieldErrors.name}
        autoComplete="name"
      />
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
        label="Password (min. 8 characters)"
        value={password}
        onChangeText={setPassword}
        error={fieldErrors.password}
        autoCapitalize="none"
        autoComplete="new-password"
        secureTextEntry
      />

      {formError ? <Text style={styles.formError}>{formError}</Text> : null}

      <Button title="Sign up" onPress={handleSubmit} loading={submitting} />
      <View style={styles.spacer} />
      <Button title="I already have an account" variant="secondary" onPress={() => navigation.goBack()} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: { marginTop: spacing.xl, marginBottom: spacing.xl, gap: spacing.xs },
  formError: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
  spacer: { height: spacing.sm },
});
