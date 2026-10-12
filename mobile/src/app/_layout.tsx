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
        <Stack.Screen name="study-group/new" options={{ headerShown: true, title: 'New study group' }} />
        <Stack.Screen name="study-group/[id]" options={{ headerShown: true, title: 'Study group' }} />
        <Stack.Screen name="student/[id]" options={{ headerShown: true, title: 'Student' }} />
        <Stack.Screen name="loan/new" options={{ headerShown: true, title: 'Ask for a loan' }} />
        <Stack.Screen name="loan/[id]" options={{ headerShown: true, title: 'Loan' }} />
        <Stack.Screen name="listing/new" options={{ headerShown: true, title: 'Sell an item' }} />
        <Stack.Screen name="listing/[id]" options={{ headerShown: true, title: 'Listing' }} />
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
