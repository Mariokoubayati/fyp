import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { Conversation, fetchConversations } from '@/api/messaging';
import { EmptyState } from '@/components/EmptyState';
import { Loading } from '@/components/Loading';
import { colors, radius, spacing, typography } from '@/theme';

function timeLabel(iso: string | null): string {
  if (!iso) {
    return '';
  }
  const date = new Date(iso);
  const sameDay = date.toDateString() === new Date().toDateString();
  return sameDay ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : date.toLocaleDateString();
}

export default function Inbox() {
  const [items, setItems] = useState<Conversation[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      setItems(await fetchConversations());
      setError(null);
    } catch (e) {
      setError(getErrorMessage(e));
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (items === null && !error) {
    return <Loading />;
  }

  return (
    <FlatList
      style={styles.list}
      contentContainerStyle={styles.content}
      data={items ?? []}
      keyExtractor={(c) => String(c.id)}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={async () => {
            setRefreshing(true);
            await load();
            setRefreshing(false);
          }}
        />
      }
      ListHeaderComponent={error ? <Text style={styles.error}>{error}</Text> : null}
      ListEmptyComponent={
        <EmptyState
          icon="chatbubbles-outline"
          title="No messages yet"
          message="Open a student, a listing or a loan and tap Message to start a chat."
        />
      }
      renderItem={({ item }) => (
        <Pressable
          style={styles.row}
          onPress={() =>
            router.push({
              pathname: '/messages/[id]',
              params: { id: String(item.id), name: item.other.name, userId: String(item.other.id) },
            })
          }
        >
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{item.other.name.charAt(0).toUpperCase()}</Text>
          </View>
          <View style={styles.middle}>
            <Text style={[typography.body, item.unread_count > 0 && styles.bold]} numberOfLines={1}>
              {item.other.name}
              {item.blocked ? ' (blocked)' : ''}
            </Text>
            <Text style={typography.caption} numberOfLines={1}>
              {item.last_message ? `${item.last_message.is_mine ? 'You: ' : ''}${item.last_message.preview}` : ''}
            </Text>
          </View>
          <View style={styles.right}>
            <Text style={typography.caption}>{timeLabel(item.last_message_at)}</Text>
            {item.unread_count > 0 ? (
              <View style={styles.unread}>
                <Text style={styles.unreadText}>{item.unread_count}</Text>
              </View>
            ) : null}
          </View>
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  list: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.md, gap: spacing.sm, flexGrow: 1 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    padding: spacing.md,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: colors.onPrimary, fontWeight: '700', fontSize: 16 },
  middle: { flex: 1, gap: 2 },
  right: { alignItems: 'flex-end', gap: spacing.xs },
  bold: { fontWeight: '700' },
  unread: {
    minWidth: 20,
    height: 20,
    borderRadius: 10,
    paddingHorizontal: 6,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  unreadText: { color: colors.onPrimary, fontSize: 12, fontWeight: '700' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.sm },
});
