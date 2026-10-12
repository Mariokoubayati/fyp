import { EmptyState } from '@/components/EmptyState';
import { Screen } from '@/components/Screen';

export default function Groups() {
  return (
    <Screen safeArea={false}>
      <EmptyState icon="people-outline" title="No study groups yet" message="Groups for your courses will appear here." />
    </Screen>
  );
}
