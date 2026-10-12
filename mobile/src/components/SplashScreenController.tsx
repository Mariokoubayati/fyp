import { SplashScreen } from 'expo-router';

import { useAuth } from '@/context/AuthContext';

SplashScreen.preventAutoHideAsync();

/** Keeps the splash screen up until the stored session has been restored. */
export function SplashScreenController() {
  const { isLoading } = useAuth();

  if (!isLoading) {
    SplashScreen.hide();
  }

  return null;
}
