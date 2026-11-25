import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { StyleSheet, Text } from 'react-native';

import { getErrorMessage } from '@/api/errors';
import { fetchProject, updateProject } from '@/api/projects';
import { Project } from '@/api/types';
import { ProjectForm } from '@/components/ProjectForm';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { colors, typography } from '@/theme';

export default function EditProject() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const projectId = Number(id);
  const [project, setProject] = useState<Project | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchProject(projectId)
      .then(setProject)
      .catch((e) => setError(getErrorMessage(e)));
  }, [projectId]);

  if (!project) {
    return error ? (
      <Screen>
        <Text style={styles.error}>{error}</Text>
      </Screen>
    ) : (
      <Loading />
    );
  }

  return (
    <ProjectForm
      initial={project}
      submitLabel="Save changes"
      onSubmit={async (payload) => {
        await updateProject(project.id, payload);
        router.back();
      }}
    />
  );
}

const styles = StyleSheet.create({
  error: { ...typography.caption, color: colors.danger },
});
