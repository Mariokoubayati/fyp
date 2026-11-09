import { Ionicons } from '@expo/vector-icons';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { fetchBadges } from '@/api/messaging';
import { colors, spacing } from '@/theme';

const REFRESH_MS = 30_000;

function Badge({ count }: { count: number }) {
  if (count <= 0) {
    return null;
  }
  return (
    <View style={styles.badge}>
      <Text style={styles.badgeText}>{count > 99 ? '99+' : count}</Text>
    </View>
  );
}

/** Chat and notification icons with unread badges, refreshed while the screen is visible. */
export function HeaderActions() {
  const [badges, setBadges] = useState({ messages: 0, notifications: 0 });

  useFocusEffect(
    useCallback(() => {
      let active = true;
      const load = () =>
        fetchBadges()
          .then((b) => active && setBadges(b))
          .catch(() => undefined);

      load();
      const timer = setInterval(load, REFRESH_MS);
      return () => {
        active = false;
        clearInterval(timer);
      };
    }, []),
  );

  return (
    <View style={styles.row}>
      <Pressable accessibilityLabel="Messages" hitSlop={8} onPress={() => router.push('/messages')}>
        <Ionicons name="chatbubbles-outline" size={24} color={colors.text} />
        <Badge count={badges.messages} />
      </Pressable>
      <Pressable accessibilityLabel="Notifications" hitSlop={8} onPress={() => router.push('/notifications')}>
        <Ionicons name="notifications-outline" size={24} color={colors.text} />
        <Badge count={badges.notifications} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: spacing.md, marginRight: spacing.md },
  badge: {
    position: 'absolute',
    top: -4,
    right: -8,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    paddingHorizontal: 4,
    backgroundColor: colors.danger,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: { color: colors.onPrimary, fontSize: 11, fontWeight: '700' },
});
