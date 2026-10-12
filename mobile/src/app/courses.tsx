import { Ionicons } from '@expo/vector-icons';
import { useCallback, useEffect, useState } from 'react';
import { FlatList, StyleSheet, Text, TextInput, View } from 'react-native';

import { enroll, fetchCourses, fetchMyCourses, leave } from '@/api/courses';
import { getErrorMessage } from '@/api/errors';
import { Course } from '@/api/types';
import { EmptyState } from '@/components/EmptyState';
import { ListItem } from '@/components/ListItem';
import { Loading } from '@/components/Loading';
import { colors, radius, spacing, typography } from '@/theme';

export default function CoursesScreen() {
  const [search, setSearch] = useState('');
  const [courses, setCourses] = useState<Course[]>([]);
  const [enrolledIds, setEnrolledIds] = useState<Set<number>>(new Set());
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchMyCourses()
      .then((mine) => setEnrolledIds(new Set(mine.map((course) => course.id))))
      .catch((e) => setError(getErrorMessage(e)));
  }, []);

  useEffect(() => {
    // Debounce so we do not hit the API on every keystroke.
    const timer = setTimeout(() => {
      fetchCourses(search.trim())
        .then(setCourses)
        .catch((e) => setError(getErrorMessage(e)))
        .finally(() => setLoading(false));
    }, 250);

    return () => clearTimeout(timer);
  }, [search]);

  const toggle = useCallback(
    async (course: Course) => {
      const wasEnrolled = enrolledIds.has(course.id);
      const apply = (enrolled: boolean) =>
        setEnrolledIds((current) => {
          const next = new Set(current);
          if (enrolled) {
            next.add(course.id);
          } else {
            next.delete(course.id);
          }
          return next;
        });

      apply(!wasEnrolled);
      setError(null);

      try {
        await (wasEnrolled ? leave(course.id) : enroll(course.id));
      } catch (e) {
        apply(wasEnrolled);
        setError(getErrorMessage(e));
      }
    },
    [enrolledIds],
  );

  if (loading) {
    return <Loading />;
  }

  return (
    <View style={styles.root}>
      <TextInput
        accessibilityLabel="Search courses"
        placeholder="Search by code or name"
        placeholderTextColor={colors.textMuted}
        value={search}
        onChangeText={setSearch}
        autoCapitalize="none"
        autoCorrect={false}
        style={styles.search}
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <FlatList
        data={courses}
        keyExtractor={(course) => String(course.id)}
        contentContainerStyle={styles.list}
        keyboardShouldPersistTaps="handled"
        ListEmptyComponent={<EmptyState icon="search-outline" title="No courses found" />}
        renderItem={({ item }) => {
          const enrolled = enrolledIds.has(item.id);

          return (
            <ListItem
              title={item.name}
              subtitle={item.code}
              onPress={() => toggle(item)}
              right={
                <Ionicons
                  name={enrolled ? 'checkmark-circle' : 'add-circle-outline'}
                  size={26}
                  color={enrolled ? colors.success : colors.textMuted}
                />
              }
            />
          );
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
  search: {
    minHeight: 48,
    margin: spacing.md,
    marginBottom: spacing.sm,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    fontSize: 16,
    color: colors.text,
  },
  error: { ...typography.caption, color: colors.danger, marginHorizontal: spacing.md },
  list: { paddingHorizontal: spacing.md, flexGrow: 1 },
});
