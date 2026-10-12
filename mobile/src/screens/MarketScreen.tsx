import { EmptyState } from '../components/EmptyState';
import { Screen } from '../components/Screen';

export function MarketScreen() {
  return (
    <Screen safeArea={false}>
      <EmptyState icon="pricetag-outline" title="Nothing for sale yet" message="Used books and gear from students will appear here." />
    </Screen>
  );
}
