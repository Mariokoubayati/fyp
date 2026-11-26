import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

import { SplashScreenController } from '@/components/SplashScreenController';
import { StartupError } from '@/components/StartupError';
import { AuthProvider, useAuth } from '@/context/AuthContext';

function RootStack() {
  const { user, isLoading, restoreError } = useAuth();

  // Mount the screens only once we know whether the student is signed in. Mounting earlier
  // made the guard redirect every opened link (and future notification taps) to Home.
  if (isLoading) {
    return null;
  }

  if (restoreError) {
    return <StartupError message={restoreError} />;
  }

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Protected guard={!!user}>
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="edit-profile" options={{ headerShown: true, title: 'Edit profile' }} />
        <Stack.Screen name="courses" options={{ headerShown: true, title: 'My courses' }} />
        <Stack.Screen name="activity" options={{ headerShown: true, title: 'My activity' }} />
        <Stack.Screen name="study-group/new" options={{ headerShown: true, title: 'New study group' }} />
        <Stack.Screen name="study-group/[id]" options={{ headerShown: true, title: 'Study group' }} />
        <Stack.Screen name="event/new" options={{ headerShown: true, title: 'Post an event' }} />
        <Stack.Screen name="event/[id]" options={{ headerShown: true, title: 'Event' }} />
        <Stack.Screen name="event/edit/[id]" options={{ headerShown: true, title: 'Edit event' }} />
        <Stack.Screen name="project/new" options={{ headerShown: true, title: 'Post a project' }} />
        <Stack.Screen name="project/[id]" options={{ headerShown: true, title: 'Project' }} />
        <Stack.Screen name="project/edit/[id]" options={{ headerShown: true, title: 'Edit project' }} />
        <Stack.Screen name="student/[id]" options={{ headerShown: true, title: 'Student' }} />
        <Stack.Screen name="loan/new" options={{ headerShown: true, title: 'Ask for a loan' }} />
        <Stack.Screen name="loan/[id]" options={{ headerShown: true, title: 'Loan' }} />
        <Stack.Screen name="listing/new" options={{ headerShown: true, title: 'Sell an item' }} />
        <Stack.Screen name="listing/[id]" options={{ headerShown: true, title: 'Listing' }} />
        <Stack.Screen name="messages/index" options={{ headerShown: true, title: 'Messages' }} />
        <Stack.Screen name="messages/[id]" options={{ headerShown: true, title: 'Chat' }} />
        <Stack.Screen name="notifications" options={{ headerShown: true, title: 'Notifications' }} />
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
