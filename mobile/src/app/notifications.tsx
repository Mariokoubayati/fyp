import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { AppNotification, fetchNotifications, markNotificationsRead } from '@/api/messaging';
import { EmptyState } from '@/components/EmptyState';
import { Loading } from '@/components/Loading';
import { colors, radius, spacing, typography } from '@/theme';

function describe(n: AppNotification): { title: string; body: string } {
  const amount = n.data.amount !== undefined ? `$${n.data.amount}` : 'Your loan';
  switch (n.type) {
    case 'message':
      return { title: `Message from ${n.data.sender?.name ?? 'a student'}`, body: n.data.preview ?? '' };
    case 'loan_accepted':
      return { title: `${n.data.other?.name ?? 'A lender'} accepted your loan request`, body: `${amount} · confirm when the cash is handed over` };
    case 'loan_due_soon':
      return { title: `${amount} is due soon`, body: n.data.due_date ? `Due on ${n.data.due_date}` : '' };
    case 'loan_overdue':
      return { title: `${amount} is overdue`, body: 'Repay as soon as you can. Late loans lower your trust score.' };
  }
}

function open(n: AppNotification) {
  if (n.type === 'message' && n.data.conversation_id) {
    router.push({
      pathname: '/messages/[id]',
      params: { id: String(n.data.conversation_id), name: n.data.sender?.name ?? '', userId: String(n.data.sender?.id ?? '') },
    });
  } else if (n.data.loan_id) {
    router.push(`/loan/${n.data.loan_id}`);
  }
}

export default function Notifications() {
  const [items, setItems] = useState<AppNotification[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Opening the list counts as seeing everything in it.
  useFocusEffect(
    useCallback(() => {
      let active = true;
      fetchNotifications()
        .then(({ items: list, unread }) => {
          if (!active) return;
          setItems(list);
          if (unread > 0) markNotificationsRead().catch(() => undefined);
        })
        .catch((e) => active && setError(getErrorMessage(e)));
      return () => {
        active = false;
      };
    }, []),
  );

  if (items === null && !error) {
    return <Loading />;
  }

  return (
    <FlatList
      style={styles.list}
      contentContainerStyle={styles.content}
      data={items ?? []}
      keyExtractor={(n) => n.id}
      ListHeaderComponent={error ? <Text style={styles.error}>{error}</Text> : null}
      ListEmptyComponent={
        <EmptyState icon="notifications-outline" title="Nothing new" message="Messages and loan updates will show up here." />
      }
      renderItem={({ item }) => {
        const { title, body } = describe(item);
        return (
          <Pressable style={[styles.row, !item.read_at && styles.unread]} onPress={() => open(item)}>
            <View style={styles.text}>
              <Text style={[typography.body, !item.read_at && styles.bold]}>{title}</Text>
              {body ? <Text style={typography.caption}>{body}</Text> : null}
            </View>
            <Text style={typography.caption}>{new Date(item.created_at).toLocaleDateString()}</Text>
          </Pressable>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  list: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.md, gap: spacing.sm, flexGrow: 1 },
  row: {
    flexDirection: 'row',
    gap: spacing.md,
    padding: spacing.md,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
  },
  unread: { borderColor: colors.primary },
  text: { flex: 1, gap: 2 },
  bold: { fontWeight: '700' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.sm },
});
