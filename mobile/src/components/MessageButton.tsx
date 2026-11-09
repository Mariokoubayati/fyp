import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { startConversation } from '@/api/messaging';
import { Button } from '@/components/Button';
import { spacing } from '@/theme';

interface MessageButtonProps {
  userId: number;
  title?: string;
}

/** Opens (or reopens) the chat with a student. */
export function MessageButton({ userId, title = 'Message' }: MessageButtonProps) {
  const [busy, setBusy] = useState(false);

  async function open() {
    setBusy(true);
    try {
      const conversation = await startConversation(userId);
      router.push({ pathname: '/messages/[id]', params: { id: String(conversation.id), name: conversation.other.name, userId: String(userId) } });
    } catch (e) {
      Alert.alert('Could not open the chat', getErrorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.wrap}>
      <Button title={title} variant="secondary" onPress={open} loading={busy} />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: spacing.sm },
});
