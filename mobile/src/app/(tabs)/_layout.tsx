import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { ColorValue } from 'react-native';
import { ComponentProps } from 'react';

import { colors } from '@/theme';

type IconName = ComponentProps<typeof Ionicons>['name'];

function tabIcon(focused: IconName, idle: IconName) {
  return function TabIcon({ focused: isFocused, color, size }: { focused: boolean; color: ColorValue; size: number }) {
    return <Ionicons name={isFocused ? focused : idle} size={size} color={color} />;
  };
}

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textMuted,
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Home', tabBarIcon: tabIcon('home', 'home-outline') }} />
      <Tabs.Screen name="groups" options={{ title: 'Study Groups', tabBarIcon: tabIcon('people', 'people-outline') }} />
      <Tabs.Screen name="market" options={{ title: 'Marketplace', tabBarIcon: tabIcon('pricetag', 'pricetag-outline') }} />
      <Tabs.Screen name="wallet" options={{ title: 'Lending', tabBarIcon: tabIcon('wallet', 'wallet-outline') }} />
      <Tabs.Screen name="profile" options={{ title: 'Profile', tabBarIcon: tabIcon('person', 'person-outline') }} />
    </Tabs>
  );
}
