import { Ionicons } from '@expo/vector-icons';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { answerRequest, deleteProject, fetchProject, leaveProject, requestToJoin, updateProject } from '@/api/projects';
import { Project } from '@/api/types';
import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Input } from '@/components/Input';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { TagList } from '@/components/TagList';
import { colors, spacing, typography } from '@/theme';

export default function ProjectDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const projectId = Number(id);
  const [project, setProject] = useState<Project | null>(null);
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Reload on focus, so coming back from "Edit" shows the changes.
  useFocusEffect(
    useCallback(() => {
      fetchProject(projectId)
        .then(setProject)
        .catch((e) => setError(getErrorMessage(e)));
    }, [projectId]),
  );

  const run = useCallback(async (action: () => Promise<void>) => {
    setBusy(true);
    setError(null);

    try {
      await action();
    } catch (e) {
      setError(getErrorMessage(e));
    } finally {
      setBusy(false);
    }
  }, []);

  if (!project) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  const handleRequest = () => run(async () => setProject(await requestToJoin(project.id, message.trim() || null)));
  const handleLeave = () =>
    run(async () => {
      await leaveProject(project.id);
      setProject(await fetchProject(project.id));
    });
  const handleAnswer = (userId: number, status: 'accepted' | 'declined') =>
    run(async () => setProject(await answerRequest(project.id, userId, status)));
  const handleOpenClose = () => run(async () => setProject(await updateProject(project.id, { is_open: !project.is_open })));
  const handleDelete = () =>
    run(async () => {
      await deleteProject(project.id);
      router.back();
    });

  const canAsk = project.is_open && !project.is_full;

  return (
    <Screen scroll safeArea={false}>
      <Card style={styles.card}>
        <Text style={typography.title}>{project.title}</Text>
        <TagList tags={project.tags} />
        {project.owner ? (
          <Text style={[typography.caption, styles.link]} onPress={() => router.push(`/student/${project.owner?.id}`)}>
            Posted by {project.owner.name}
          </Text>
        ) : null}
        <Text style={[typography.body, styles.description]}>{project.description}</Text>
        <View style={styles.meta}>
          <Ionicons name="construct-outline" size={18} color={colors.textMuted} />
          <Text style={typography.body}>Skills needed</Text>
        </View>
        <TagList words={project.skills_needed} />
        <View style={styles.meta}>
          <Ionicons name="people-outline" size={18} color={colors.textMuted} />
          <Text style={typography.body}>
            {project.members_count}/{project.team_size} on the team
            {!project.is_open ? ' · Closed' : project.is_full ? ' · Full' : ''}
          </Text>
        </View>
        {project.members?.map((member) => (
          <Text key={member.id} style={[typography.caption, styles.link]} onPress={() => router.push(`/student/${member.id}`)}>
            {member.name}
            {member.major ? ` · ${member.major}` : ''}
          </Text>
        ))}
      </Card>

      {project.is_owner && project.requests ? (
        <Card style={styles.card}>
          <Text style={typography.heading}>Requests to join</Text>
          {project.requests.length === 0 ? <Text style={typography.caption}>No requests waiting.</Text> : null}
          {project.requests.map((request) => (
            <View key={request.id} style={styles.request}>
              <Text style={[typography.body, styles.link]} onPress={() => router.push(`/student/${request.id}`)}>
                {request.name}
                {request.major ? ` · ${request.major}` : ''}
              </Text>
              <TagList words={request.skills} />
              {request.message ? <Text style={typography.caption}>{`"${request.message}"`}</Text> : null}
              <View style={styles.row}>
                <View style={styles.flex}>
                  <Button title="Accept" onPress={() => handleAnswer(request.id, 'accepted')} disabled={busy || project.is_full} />
                </View>
                <View style={styles.flex}>
                  <Button title="Decline" variant="secondary" onPress={() => handleAnswer(request.id, 'declined')} disabled={busy} />
                </View>
              </View>
            </View>
          ))}
        </Card>
      ) : null}

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.actions}>
        {project.is_owner ? (
          <>
            <Button title="Edit project" variant="secondary" onPress={() => router.push(`/project/edit/${project.id}`)} disabled={busy} />
            <Button
              title={project.is_open ? 'Stop looking for members' : 'Look for members again'}
              variant="secondary"
              onPress={handleOpenClose}
              loading={busy}
            />
            <Button title="Delete project" variant="danger" onPress={handleDelete} loading={busy} />
          </>
        ) : project.my_status === 'accepted' ? (
          <Button title="Leave the team" variant="secondary" onPress={handleLeave} loading={busy} />
        ) : project.my_status === 'pending' ? (
          <>
            <Text style={[typography.caption, styles.note]}>Request sent. You will get a notification when the owner answers.</Text>
            <Button title="Withdraw my request" variant="secondary" onPress={handleLeave} loading={busy} />
          </>
        ) : project.my_status === 'declined' ? (
          <Text style={[typography.caption, styles.note]}>The owner declined your request.</Text>
        ) : (
          <>
            {canAsk ? (
              <Input
                label="Message to the owner (optional)"
                value={message}
                onChangeText={setMessage}
                maxLength={300}
                multiline
                style={styles.message}
              />
            ) : null}
            <Button
              title={!project.is_open ? 'Not looking for members' : project.is_full ? 'Team is full' : 'Ask to join'}
              onPress={handleRequest}
              loading={busy}
              disabled={!canAsk}
            />
          </>
        )}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.sm, marginBottom: spacing.md },
  meta: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  link: { color: colors.primary },
  description: { marginVertical: spacing.xs },
  request: { gap: spacing.xs, paddingTop: spacing.sm, borderTopWidth: 1, borderTopColor: colors.border },
  row: { flexDirection: 'row', gap: spacing.sm },
  flex: { flex: 1 },
  actions: { gap: spacing.sm },
  message: { minHeight: 72, paddingTop: spacing.sm, textAlignVertical: 'top' },
  note: { textAlign: 'center' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
