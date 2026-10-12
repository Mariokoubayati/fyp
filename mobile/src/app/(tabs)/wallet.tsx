import { EmptyState } from '@/components/EmptyState';
import { Screen } from '@/components/Screen';

export default function Wallet() {
  return (
    <Screen safeArea={false}>
      <EmptyState icon="wallet-outline" title="No loans yet" message="Loans you give or take will be tracked here." />
    </Screen>
  );
}
