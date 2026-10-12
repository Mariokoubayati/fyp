import DateTimePicker from '@react-native-community/datetimepicker';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';

import { getErrorMessage, getFieldErrors } from '@/api/errors';
import { fetchMyCourses } from '@/api/courses';
import { createStudyGroup } from '@/api/studyGroups';
import { Course } from '@/api/types';
import { Button } from '@/components/Button';
import { Input } from '@/components/Input';
import { Loading } from '@/components/Loading';
import { Screen } from '@/components/Screen';
import { formatDateTime } from '@/lib/format';
import { colors, radius, spacing, typography } from '@/theme';

function defaultStart(): Date {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  date.setHours(16, 0, 0, 0);

  return date;
}

export default function NewStudyGroup() {
  const [courses, setCourses] = useState<Course[] | null>(null);
  const [courseId, setCourseId] = useState<number | null>(null);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [location, setLocation] = useState('');
  const [capacity, setCapacity] = useState('6');
  const [startsAt, setStartsAt] = useState(defaultStart);
  const [pickerMode, setPickerMode] = useState<'date' | 'time' | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    fetchMyCourses()
      .then((mine) => {
        setCourses(mine);
        setCourseId(mine[0]?.id ?? null);
      })
      .catch((e) => {
        setCourses([]);
        setFormError(getErrorMessage(e));
      });
  }, []);

  async function handleSubmit() {
    if (courseId === null) {
      return;
    }

    setSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    try {
      const group = await createStudyGroup({
        course_id: courseId,
        title: title.trim(),
        description: description.trim() || null,
        location: location.trim() || null,
        starts_at: startsAt.toISOString(),
        capacity: Number(capacity) || 6,
      });

      router.replace(`/study-group/${group.id}`);
    } catch (error) {
      const fields = getFieldErrors(error);
      setFieldErrors(fields);
      setFormError(Object.keys(fields).length > 0 ? null : getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  if (courses === null) {
    return <Loading />;
  }

  if (courses.length === 0) {
    return (
      <Screen safeArea={false}>
        <Text style={typography.heading}>Enroll in a course first</Text>
        <Text style={[typography.caption, styles.gap]}>
          Study groups belong to a course. Add your courses from your profile, then come back.
        </Text>
        <Button title="Choose my courses" onPress={() => router.replace('/courses')} />
      </Screen>
    );
  }

  return (
    <Screen scroll safeArea={false}>
      <Text style={styles.label}>Course</Text>
      <View style={styles.chips}>
        {courses.map((course) => (
          <Pressable
            key={course.id}
            accessibilityRole="button"
            accessibilityState={{ selected: course.id === courseId }}
            onPress={() => setCourseId(course.id)}
            style={[styles.chip, course.id === courseId && styles.chipSelected]}
          >
            <Text style={[styles.chipText, course.id === courseId && styles.chipTextSelected]}>{course.code}</Text>
          </Pressable>
        ))}
      </View>
      {fieldErrors.course_id ? <Text style={styles.error}>{fieldErrors.course_id}</Text> : null}

      <Input label="Title" value={title} onChangeText={setTitle} error={fieldErrors.title} />
      <Input label="Location (optional)" value={location} onChangeText={setLocation} error={fieldErrors.location} />

      <Text style={styles.label}>When</Text>
      <View style={styles.when}>
        <Button title={startsAt.toLocaleDateString()} variant="secondary" onPress={() => setPickerMode('date')} />
        <Button
          title={startsAt.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}
          variant="secondary"
          onPress={() => setPickerMode('time')}
        />
      </View>
      <Text style={[typography.caption, styles.gap]}>{formatDateTime(startsAt.toISOString())}</Text>
      {fieldErrors.starts_at ? <Text style={styles.error}>{fieldErrors.starts_at}</Text> : null}
      {pickerMode ? (
        <DateTimePicker
          value={startsAt}
          mode={pickerMode}
          minimumDate={new Date()}
          onChange={(_, selected) => {
            setPickerMode(Platform.OS === 'ios' ? pickerMode : null);
            if (selected) {
              setStartsAt(selected);
            }
          }}
        />
      ) : null}

      <Input
        label="Max students (2-30)"
        value={capacity}
        onChangeText={setCapacity}
        error={fieldErrors.capacity}
        keyboardType="number-pad"
        maxLength={2}
      />
      <Input
        label="Description (optional)"
        value={description}
        onChangeText={setDescription}
        error={fieldErrors.description}
        multiline
        style={styles.description}
      />

      {formError ? <Text style={styles.error}>{formError}</Text> : null}

      <Button title="Create group" onPress={handleSubmit} loading={submitting} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  label: { ...typography.caption, fontWeight: '600', marginBottom: spacing.xs },
  gap: { marginVertical: spacing.sm },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  chip: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  chipSelected: { borderColor: colors.primary, backgroundColor: colors.primary },
  chipText: { ...typography.body, fontSize: 14 },
  chipTextSelected: { color: colors.onPrimary, fontWeight: '600' },
  when: { flexDirection: 'row', gap: spacing.sm },
  description: { minHeight: 96, paddingTop: spacing.sm, textAlignVertical: 'top' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
