import { Ionicons } from '@expo/vector-icons';
import { Stack, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useRef, useState } from 'react';
import {
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
  /** Which bottom sheet is open: the options menu, the report reasons, or the block confirmation. */
  const [sheet, setSheet] = useState<'menu' | 'report' | 'block' | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
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

  // In-app sheets instead of Alert, so the menu also works in the browser build.
  function report(reason: ReportReason) {
    setSheet(null);
    if (!otherId) return;
    reportStudent(otherId, reason, conversationId)
      .then(() => setNotice('Report sent. Thank you, we will review it.'))
      .catch((e) => setError(getErrorMessage(e)));
  }

  function block() {
    setSheet(null);
    if (!otherId) return;
    blockStudent(otherId)
      .then(() => setNotice(`You blocked ${params.name ?? 'this student'}. Neither of you can send messages now.`))
      .catch((e) => setError(getErrorMessage(e)));
  }

  return (
    <SafeAreaView style={styles.root} edges={['bottom', 'left', 'right']}>
      <Stack.Screen
        options={{
          title: params.name ?? 'Chat',
          headerRight: () =>
            otherId ? (
              <Pressable accessibilityLabel="Chat options" hitSlop={8} onPress={() => setSheet('menu')}>
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
        {notice ? <Text style={styles.notice}>{notice}</Text> : null}
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

      <Modal visible={sheet !== null} transparent animationType="fade" onRequestClose={() => setSheet(null)}>
        <Pressable style={styles.backdrop} onPress={() => setSheet(null)}>
          <View style={styles.sheet}>
            {sheet === 'menu' ? (
              <>
                <Text style={typography.heading}>{params.name ?? 'Chat'}</Text>
                <Pressable style={styles.option} onPress={() => setSheet('report')}>
                  <Text style={typography.body}>Report</Text>
                </Pressable>
                <Pressable style={styles.option} onPress={() => setSheet('block')}>
                  <Text style={[typography.body, styles.danger]}>Block</Text>
                </Pressable>
              </>
            ) : null}
            {sheet === 'report' ? (
              <>
                <Text style={typography.heading}>Why are you reporting?</Text>
                {REPORT_REASONS.map((r) => (
                  <Pressable key={r.value} style={styles.option} onPress={() => report(r.value)}>
                    <Text style={typography.body}>{r.label}</Text>
                  </Pressable>
                ))}
              </>
            ) : null}
            {sheet === 'block' ? (
              <>
                <Text style={typography.heading}>Block {params.name ?? 'this student'}?</Text>
                <Text style={typography.caption}>Neither of you will be able to send messages. You can unblock later.</Text>
                <Pressable style={styles.option} onPress={block}>
                  <Text style={[typography.body, styles.danger]}>Block</Text>
                </Pressable>
              </>
            ) : null}
            <Pressable style={styles.option} onPress={() => setSheet(null)}>
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
  danger: { color: colors.danger },
  notice: { ...typography.caption, color: colors.success, paddingHorizontal: spacing.md },
});
