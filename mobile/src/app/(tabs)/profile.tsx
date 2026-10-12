import { StyleSheet, Text, View } from 'react-native';

import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Screen } from '@/components/Screen';
import { useAuth } from '@/context/AuthContext';
import { spacing, typography } from '@/theme';

export default function Profile() {
  const { user, signOut } = useAuth();

  return (
    <Screen safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.heading}>{user?.name}</Text>
        <Text style={typography.caption}>{user?.email}</Text>
      </Card>
      <View style={styles.spacer} />
      <Button title="Sign out" variant="secondary" onPress={signOut} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.xs },
  spacer: { height: spacing.md },
});
