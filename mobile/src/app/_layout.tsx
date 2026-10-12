import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

import { SplashScreenController } from '@/components/SplashScreenController';
import { AuthProvider, useAuth } from '@/context/AuthContext';

function RootStack() {
  const { user } = useAuth();

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Protected guard={!!user}>
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="edit-profile" options={{ headerShown: true, title: 'Edit profile' }} />
        <Stack.Screen name="courses" options={{ headerShown: true, title: 'My courses' }} />
      </Stack.Protected>
      <Stack.Protected guard={!user}>
        <Stack.Screen name="sign-in" />
        <Stack.Screen name="register" options={{ headerShown: true, title: 'Create account' }} />
      </Stack.Protected>
    </Stack>
  );
}

export default function RootLayout() {
  return (
    <AuthProvider>
      <SplashScreenController />
      <StatusBar style="auto" />
      <RootStack />
    </AuthProvider>
  );
}
