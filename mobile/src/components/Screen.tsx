import { ReactNode } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { colors, spacing } from '../theme';

interface ScreenProps {
  children: ReactNode;
  /** Wrap the content in a ScrollView (use for forms and long lists). */
  scroll?: boolean;
  /** Respect the top safe area. Turn off under a navigation header. */
  safeArea?: boolean;
}

export function Screen({ children, scroll = false, safeArea = true }: ScreenProps) {
  const body = scroll ? (
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.flex, styles.content]}>{children}</View>
  );

  if (!safeArea) {
    return <View style={styles.root}>{body}</View>;
  }

  return (
    <SafeAreaView style={styles.root} edges={['top', 'left', 'right']}>
      {body}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
  flex: { flex: 1 },
  content: { flexGrow: 1, padding: spacing.md },
});
