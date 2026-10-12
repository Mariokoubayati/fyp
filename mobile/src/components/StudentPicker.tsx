import { useEffect, useState } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';

import { getErrorMessage } from '../api/errors';
import { searchStudents } from '../api/students';
import { Student } from '../api/types';
import { colors, radius, spacing, typography } from '../theme';
import { ListItem } from './ListItem';

interface StudentPickerProps {
  onPick: (student: Student) => void;
}

/** Search-as-you-type list of other students. */
export function StudentPicker({ onPick }: StudentPickerProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Student[]>([]);
  const [error, setError] = useState<string | null>(null);

  const isSearchable = query.trim().length >= 2;

  useEffect(() => {
    if (!isSearchable) {
      return;
    }

    const timer = setTimeout(() => {
      searchStudents(query.trim())
        .then(setResults)
        .catch((e) => setError(getErrorMessage(e)));
    }, 250);

    return () => clearTimeout(timer);
  }, [query, isSearchable]);

  return (
    <View>
      <TextInput
        accessibilityLabel="Search students by name"
        placeholder="Search students by name"
        placeholderTextColor={colors.textMuted}
        value={query}
        onChangeText={setQuery}
        autoCapitalize="none"
        autoCorrect={false}
        style={styles.input}
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
      {(isSearchable ? results : []).map((student) => (
        <ListItem
          key={student.id}
          title={student.name}
          subtitle={[student.major, student.study_year ? `Year ${student.study_year}` : null].filter(Boolean).join(' · ')}
          onPress={() => onPick(student)}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  input: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    fontSize: 16,
    color: colors.text,
    marginBottom: spacing.sm,
  },
  error: { ...typography.caption, color: colors.danger },
});
