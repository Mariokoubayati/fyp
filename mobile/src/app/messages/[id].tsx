import { Ionicons } from '@expo/vector-icons';
import { Stack, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useRef, useState } from 'react';
import {
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { getErrorMessage } from '@/api/errors';
import { blockStudent, ChatMessage, fetchMessages, reportStudent, ReportReason, sendMessage } from '@/api/messaging';
import { Loading } from '@/components/Loading';
import { colors, radius, spacing, typography } from '@/theme';

/** How often to check for new messages while the chat is open. */
const POLL_MS = 4000;

const REPORT_REASONS: { value: ReportReason; label: string }[] = [
  { value: 'harassment', label: 'Harassment' },
  { value: 'spam', label: 'Spam' },
  { value: 'scam', label: 'Scam or fraud' },
  { value: 'inappropriate', label: 'Inappropriate content' },
  { value: 'other', label: 'Something else' },
];

export default function Chat() {
  const params = useLocalSearchParams<{ id: string; name?: string; userId?: string }>();
  const conversationId = Number(params.id);
  const otherId = params.userId ? Number(params.userId) : null;

  const [messages, setMessages] = useState<ChatMessage[] | null>(null);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reporting, setReporting] = useState(false);
  const listRef = useRef<FlatList<ChatMessage>>(null);
  const lastId = useRef<number | undefined>(undefined);

  const merge = useCallback((incoming: ChatMessage[]) => {
    if (incoming.length === 0) {
      return;
    }
    setMessages((current) => {
      const byId = new Map((current ?? []).map((m) => [m.id, m]));
      incoming.forEach((m) => byId.set(m.id, m));
      const next = [...byId.values()].sort((a, b) => a.id - b.id);
      lastId.current = next[next.length - 1]?.id;
      return next;
    });
  }, []);

  // Load the latest page, then poll for newer messages while the screen is focused.
  useFocusEffect(
    useCallback(() => {
      let active = true;

      fetchMessages(conversationId)
        .then((page) => {
          if (!active) return;
          setMessages([]);
          merge(page);
        })
        .catch((e) => active && setError(getErrorMessage(e)));

      const timer = setInterval(() => {
        if (lastId.current === undefined) return;
        fetchMessages(conversationId, lastId.current)
          .then((page) => active && merge(page))
          .catch(() => undefined);
      }, POLL_MS);

      return () => {
        active = false;
        clearInterval(timer);
      };
    }, [conversationId, merge]),
  );

  async function send() {
    const body = draft.trim();
    if (!body || sending) return;
    setSending(true);
    setError(null);
    try {
      merge([await sendMessage(conversationId, body)]);
      setDraft('');
    } catch (e) {
      setError(getErrorMessage(e));
    } finally {
      setSending(false);
    }
  }

  function report(reason: ReportReason) {
    setReporting(false);
    if (!otherId) return;
    reportStudent(otherId, reason, conversationId)
      .then(() => Alert.alert('Report sent', 'Thank you. We will review it.'))
      .catch((e) => Alert.alert('Could not send the report', getErrorMessage(e)));
  }

  function block() {
    if (!otherId) return;
    Alert.alert(`Block ${params.name ?? 'this student'}?`, 'Neither of you will be able to send messages.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Block',
        style: 'destructive',
        onPress: () => blockStudent(otherId).catch((e) => Alert.alert('Could not block', getErrorMessage(e))),
      },
    ]);
  }

  function openMenu() {
    Alert.alert(params.name ?? 'Chat', undefined, [
      { text: 'Report', onPress: () => setReporting(true) },
      { text: 'Block', style: 'destructive', onPress: block },
      { text: 'Cancel', style: 'cancel' },
    ]);
  }

  return (
    <SafeAreaView style={styles.root} edges={['bottom', 'left', 'right']}>
      <Stack.Screen
        options={{
          title: params.name ?? 'Chat',
          headerRight: () =>
            otherId ? (
              <Pressable accessibilityLabel="Chat options" hitSlop={8} onPress={openMenu}>
                <Ionicons name="ellipsis-horizontal" size={22} color={colors.text} />
              </Pressable>
            ) : null,
        }}
      />
      <KeyboardAvoidingView style={styles.root} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={90}>
        {messages === null ? (
          <Loading />
        ) : (
          <FlatList
            ref={listRef}
            data={messages}
            keyExtractor={(m) => String(m.id)}
            contentContainerStyle={styles.messages}
            onContentSizeChange={() => listRef.current?.scrollToEnd({ animated: false })}
            ListEmptyComponent={<Text style={[typography.caption, styles.empty]}>Say hello 👋</Text>}
            renderItem={({ item }) => (
              <View style={[styles.bubble, item.is_mine ? styles.mine : styles.theirs]}>
                <Text style={[typography.body, item.is_mine && styles.mineText]}>{item.body}</Text>
                <Text style={[styles.time, item.is_mine && styles.mineTime]}>
                  {new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  {item.is_mine && item.read_at ? ' · Seen' : ''}
                </Text>
              </View>
            )}
          />
        )}
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <View style={styles.composer}>
          <TextInput
            style={styles.input}
            value={draft}
            onChangeText={setDraft}
            placeholder="Write a message"
            multiline
            maxLength={2000}
          />
          <Pressable
            accessibilityLabel="Send"
            style={[styles.send, (!draft.trim() || sending) && styles.sendDisabled]}
            onPress={send}
            disabled={!draft.trim() || sending}
          >
            <Ionicons name="send" size={18} color={colors.onPrimary} />
          </Pressable>
        </View>
      </KeyboardAvoidingView>

      <Modal visible={reporting} transparent animationType="fade" onRequestClose={() => setReporting(false)}>
        <Pressable style={styles.backdrop} onPress={() => setReporting(false)}>
          <View style={styles.sheet}>
            <Text style={typography.heading}>Why are you reporting?</Text>
            {REPORT_REASONS.map((r) => (
              <Pressable key={r.value} style={styles.option} onPress={() => report(r.value)}>
                <Text style={typography.body}>{r.label}</Text>
              </Pressable>
            ))}
            <Pressable style={styles.option} onPress={() => setReporting(false)}>
              <Text style={[typography.body, styles.cancel]}>Cancel</Text>
            </Pressable>
          </View>
        </Pressable>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
  messages: { padding: spacing.md, gap: spacing.sm, flexGrow: 1 },
  empty: { textAlign: 'center', marginTop: spacing.xl },
  bubble: { maxWidth: '80%', padding: spacing.sm, borderRadius: radius.md, gap: 2 },
  mine: { alignSelf: 'flex-end', backgroundColor: colors.primary },
  theirs: { alignSelf: 'flex-start', backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border },
  mineText: { color: colors.onPrimary },
  time: { fontSize: 11, color: colors.textMuted, alignSelf: 'flex-end' },
  mineTime: { color: colors.onPrimary, opacity: 0.8 },
  composer: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: spacing.sm,
    padding: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    backgroundColor: colors.surface,
  },
  input: {
    flex: 1,
    maxHeight: 120,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    fontSize: 16,
    color: colors.text,
  },
  send: { width: 40, height: 40, borderRadius: 20, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
  sendDisabled: { opacity: 0.4 },
  error: { ...typography.caption, color: colors.danger, paddingHorizontal: spacing.md },
  backdrop: { flex: 1, backgroundColor: 'rgba(15, 23, 42, 0.4)', justifyContent: 'flex-end' },
  sheet: { backgroundColor: colors.surface, padding: spacing.md, gap: spacing.xs, borderTopLeftRadius: radius.lg, borderTopRightRadius: radius.lg },
  option: { paddingVertical: spacing.md, borderTopWidth: 1, borderTopColor: colors.border },
  cancel: { color: colors.textMuted, textAlign: 'center' },
});
