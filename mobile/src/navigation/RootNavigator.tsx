import { Loading } from '../components/Loading';
import { useAuth } from '../context/AuthContext';
import { AuthStack } from './AuthStack';
import { MainTabs } from './MainTabs';

export function RootNavigator() {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return <Loading />;
  }

  return user ? <MainTabs /> : <AuthStack />;
}
