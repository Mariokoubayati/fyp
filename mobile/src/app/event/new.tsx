import DateTimePicker from '@react-native-community/datetimepicker';
import { router } from 'expo-router';
import { useState } from 'react';
import { Platform, StyleSheet, Text, View } from 'react-native';

import { getErrorMessage, getFieldErrors } from '@/api/errors';
import { createEvent } from '@/api/events';
import { CampusTag } from '@/api/types';
import { Button } from '@/components/Button';
import { Input } from '@/components/Input';
import { Screen } from '@/components/Screen';
import { TagPicker } from '@/components/TagPicker';
import { formatDateTime } from '@/lib/format';
import { colors, spacing, typography } from '@/theme';

function defaultStart(): Date {
  const date = new Date();
  date.setDate(date.getDate() + 3);
  date.setHours(18, 0, 0, 0);

  return date;
}

export default function NewEvent() {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [location, setLocation] = useState('');
  const [capacity, setCapacity] = useState('');
  const [hours, setHours] = useState('2');
  const [tags, setTags] = useState<CampusTag[]>([]);
  const [startsAt, setStartsAt] = useState(defaultStart);
  const [pickerMode, setPickerMode] = useState<'date' | 'time' | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit() {
    setSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    const duration = Number(hours);
    const endsAt = duration > 0 ? new Date(startsAt.getTime() + duration * 3600 * 1000).toISOString() : null;

    try {
      const event = await createEvent({
        title: title.trim(),
        description: description.trim() || null,
        location: location.trim(),
        starts_at: startsAt.toISOString(),
        ends_at: endsAt,
        capacity: capacity.trim() ? Number(capacity) : null,
        tags,
      });

      router.replace(`/event/${event.id}`);
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
      <Input label="Title" value={title} onChangeText={setTitle} error={fieldErrors.title} maxLength={120} />
      <Input label="Where" value={location} onChangeText={setLocation} error={fieldErrors.location} maxLength={120} />

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
        label="How many hours (optional)"
        value={hours}
        onChangeText={setHours}
        error={fieldErrors.ends_at}
        keyboardType="number-pad"
        maxLength={2}
      />

      <Text style={styles.label}>Topics (up to 3)</Text>
      <TagPicker value={tags} onChange={setTags} max={3} />
      {fieldErrors.tags ? <Text style={styles.error}>{fieldErrors.tags}</Text> : null}

      <Input
        label="Max people (optional, leave empty for no limit)"
        value={capacity}
        onChangeText={setCapacity}
        error={fieldErrors.capacity}
        keyboardType="number-pad"
        maxLength={3}
      />
      <Input
        label="Description (optional)"
        value={description}
        onChangeText={setDescription}
        error={fieldErrors.description}
        multiline
        maxLength={2000}
        style={styles.description}
      />

      {formError ? <Text style={styles.error}>{formError}</Text> : null}

      <Button title="Post event" onPress={handleSubmit} loading={submitting} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  label: { ...typography.caption, fontWeight: '600', marginBottom: spacing.xs },
  gap: { marginVertical: spacing.sm },
  when: { flexDirection: 'row', gap: spacing.sm },
  description: { minHeight: 96, paddingTop: spacing.sm, textAlignVertical: 'top' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
