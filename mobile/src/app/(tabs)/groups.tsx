import { router, useLocalSearchParams } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import { EventsPanel } from '@/components/EventsPanel';
import { ProjectsPanel } from '@/components/ProjectsPanel';
import { SegmentedControl } from '@/components/SegmentedControl';
import { StudyGroupsPanel } from '@/components/StudyGroupsPanel';
import { colors, spacing } from '@/theme';

type Section = 'groups' | 'events' | 'projects';

const SECTIONS: { value: Section; label: string }[] = [
  { value: 'groups', label: 'Study groups' },
  { value: 'events', label: 'Events' },
  { value: 'projects', label: 'Projects' },
];

/**
 * The Campus tab: study groups, events and projects behind one switcher.
 * The section lives in the URL (?section=events), so "See all" links on Home open the right one.
 */
export default function Campus() {
  const { section = 'groups' } = useLocalSearchParams<{ section?: Section }>();

  return (
    <View style={styles.root}>
      <View style={styles.switcher}>
        <SegmentedControl options={SECTIONS} value={section} onChange={(next) => router.setParams({ section: next })} />
      </View>
      {section === 'events' ? <EventsPanel /> : section === 'projects' ? <ProjectsPanel /> : <StudyGroupsPanel />}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
  switcher: { paddingHorizontal: spacing.md, paddingTop: spacing.md },
});
