import { router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text } from 'react-native';

import { updateProfile } from '@/api/auth';
import { getErrorMessage, getFieldErrors } from '@/api/errors';
import { Button } from '@/components/Button';
import { Input } from '@/components/Input';
import { Screen } from '@/components/Screen';
import { useAuth } from '@/context/AuthContext';
import { colors, spacing, typography } from '@/theme';

export default function EditProfileScreen() {
  const { user, updateUser } = useAuth();
  const [name, setName] = useState(user?.name ?? '');
  const [major, setMajor] = useState(user?.major ?? '');
  const [studyYear, setStudyYear] = useState(user?.study_year ? String(user.study_year) : '');
  const [bio, setBio] = useState(user?.bio ?? '');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSave() {
    setSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    try {
      const updated = await updateProfile({
        name: name.trim(),
        major: major.trim() || null,
        study_year: studyYear.trim() ? Number(studyYear) : null,
        bio: bio.trim() || null,
      });

      updateUser(updated);
      router.back();
    } catch (error) {
      const fields = getFieldErrors(error);
      setFieldErrors(fields);
      setFormError(Object.keys(fields).length > 0 ? null : getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Screen scroll safeArea={false}>
      <Input label="Full name" value={name} onChangeText={setName} error={fieldErrors.name} autoComplete="name" />
      <Input label="Major" value={major} onChangeText={setMajor} error={fieldErrors.major} />
      <Input
        label="Year of study (1-6)"
        value={studyYear}
        onChangeText={setStudyYear}
        error={fieldErrors.study_year}
        keyboardType="number-pad"
        maxLength={1}
      />
      <Input
        label="Bio"
        value={bio}
        onChangeText={setBio}
        error={fieldErrors.bio}
        multiline
        maxLength={500}
        style={styles.bio}
      />

      {formError ? <Text style={styles.formError}>{formError}</Text> : null}

      <Button title="Save" onPress={handleSave} loading={submitting} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  bio: { minHeight: 96, paddingTop: spacing.sm, textAlignVertical: 'top' },
  formError: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
