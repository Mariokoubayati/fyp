import { Ionicons } from '@expo/vector-icons';
import { StyleSheet, Text, View } from 'react-native';

import { useAuth } from '../context/AuthContext';
import { colors, spacing, typography } from '../theme';
import { Button } from './Button';
import { Screen } from './Screen';

/**
 * Shown on app start when a stored session exists but the server cannot be reached.
 * The student is still signed in, so they are offered a retry, not the sign-in screen.
 */
export function StartupError({ message }: { message: string }) {
  const { retryRestore, retrying, signOut } = useAuth();

  return (
    <Screen>
      <View style={styles.container}>
        <Ionicons name="cloud-offline-outline" size={48} color={colors.textMuted} />
        <Text style={[typography.heading, styles.title]}>You are still signed in</Text>
        <Text style={[typography.caption, styles.message]}>{message}</Text>
        <View style={styles.actions}>
          <Button title="Try again" onPress={retryRestore} loading={retrying} />
          <Button title="Sign out" variant="secondary" onPress={signOut} />
        </View>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.lg },
  title: { marginTop: spacing.md, textAlign: 'center' },
  message: { marginTop: spacing.xs, marginBottom: spacing.lg, textAlign: 'center' },
  actions: { alignSelf: 'stretch', gap: spacing.sm },
});
