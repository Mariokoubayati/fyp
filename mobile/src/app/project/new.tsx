import { router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text } from 'react-native';

import { getErrorMessage, getFieldErrors } from '@/api/errors';
import { createProject } from '@/api/projects';
import { CampusTag } from '@/api/types';
import { Button } from '@/components/Button';
import { Input } from '@/components/Input';
import { Screen } from '@/components/Screen';
import { TagPicker } from '@/components/TagPicker';
import { parseSkills } from '@/lib/tags';
import { colors, spacing, typography } from '@/theme';

export default function NewProject() {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [skills, setSkills] = useState('');
  const [teamSize, setTeamSize] = useState('4');
  const [tags, setTags] = useState<CampusTag[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit() {
    setSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    try {
      const project = await createProject({
        title: title.trim(),
        description: description.trim(),
        skills_needed: parseSkills(skills),
        tags,
        team_size: Number(teamSize) || 0,
      });

      router.replace(`/project/${project.id}`);
    } catch (error) {
      const fields = getFieldErrors(error);
      setFieldErrors(fields);
      setFormError(Object.keys(fields).length > 0 ? null : getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  const skillsError = fieldErrors.skills_needed ?? Object.entries(fieldErrors).find(([key]) => key.startsWith('skills_needed.'))?.[1];

  return (
    <Screen scroll safeArea={false}>
      <Input label="Project name" value={title} onChangeText={setTitle} error={fieldErrors.title} maxLength={120} />
      <Input
        label="What is it about?"
        value={description}
        onChangeText={setDescription}
        error={fieldErrors.description}
        multiline
        maxLength={2000}
        style={styles.description}
      />
      <Input
        label="Skills you need, separated by commas"
        placeholder="e.g. Laravel, UI design, Marketing"
        value={skills}
        onChangeText={setSkills}
        error={skillsError}
      />

      <Text style={styles.label}>Topics (up to 3)</Text>
      <TagPicker value={tags} onChange={setTags} max={3} />
      {fieldErrors.tags ? <Text style={styles.error}>{fieldErrors.tags}</Text> : null}

      <Input
        label="Team size, you included (2-10)"
        value={teamSize}
        onChangeText={setTeamSize}
        error={fieldErrors.team_size}
        keyboardType="number-pad"
        maxLength={2}
      />

      {formError ? <Text style={styles.error}>{formError}</Text> : null}

      <Button title="Post project" onPress={handleSubmit} loading={submitting} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  label: { ...typography.caption, fontWeight: '600', marginBottom: spacing.xs },
  description: { minHeight: 96, paddingTop: spacing.sm, textAlignVertical: 'top' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
