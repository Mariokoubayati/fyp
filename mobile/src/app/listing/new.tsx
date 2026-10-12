import { router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text } from 'react-native';

import { getErrorMessage, getFieldErrors } from '@/api/errors';
import { createListing } from '@/api/listings';
import { ListingCategory, ListingCondition } from '@/api/types';
import { Button } from '@/components/Button';
import { ChipGroup } from '@/components/ChipGroup';
import { Input } from '@/components/Input';
import { Screen } from '@/components/Screen';
import { CATEGORY_OPTIONS, CONDITION_OPTIONS } from '@/lib/listings';
import { colors, spacing, typography } from '@/theme';

export default function NewListing() {
  const [title, setTitle] = useState('');
  const [price, setPrice] = useState('');
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState<ListingCategory>('books');
  const [condition, setCondition] = useState<ListingCondition>('good');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit() {
    setSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    try {
      const listing = await createListing({
        title: title.trim(),
        description: description.trim() || null,
        price: Number(price.replace(',', '.')),
        category,
        condition,
        course_id: null,
      });

      router.replace(`/listing/${listing.id}`);
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
      <Input label="Title" value={title} onChangeText={setTitle} error={fieldErrors.title} />
      <Input
        label="Price (USD, 0 for free)"
        value={price}
        onChangeText={setPrice}
        error={fieldErrors.price}
        keyboardType="decimal-pad"
      />

      <Text style={styles.label}>Category</Text>
      <ChipGroup options={CATEGORY_OPTIONS} value={category} onChange={setCategory} />
      {fieldErrors.category ? <Text style={styles.error}>{fieldErrors.category}</Text> : null}

      <Text style={styles.label}>Condition</Text>
      <ChipGroup options={CONDITION_OPTIONS} value={condition} onChange={setCondition} />
      {fieldErrors.condition ? <Text style={styles.error}>{fieldErrors.condition}</Text> : null}

      <Input
        label="Description (optional)"
        value={description}
        onChangeText={setDescription}
        error={fieldErrors.description}
        multiline
        style={styles.description}
      />

      {formError ? <Text style={styles.error}>{formError}</Text> : null}

      <Button title="Post listing" onPress={handleSubmit} loading={submitting} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  label: { ...typography.caption, fontWeight: '600', marginBottom: spacing.xs },
  description: { minHeight: 96, paddingTop: spacing.sm, textAlignVertical: 'top' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
});
