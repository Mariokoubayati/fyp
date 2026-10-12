import { StyleSheet, Text } from 'react-native';

import { Card } from '@/components/Card';
import { Screen } from '@/components/Screen';
import { useAuth } from '@/context/AuthContext';
import { spacing, typography } from '@/theme';

export default function Home() {
  const { user } = useAuth();

  return (
    <Screen safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.heading}>Hi, {user?.name}</Text>
        <Text style={typography.caption}>
          Study groups, books for your courses and campus events will show up here.
        </Text>
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.xs },
});
