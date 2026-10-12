import { Ionicons } from '@expo/vector-icons';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { ComponentProps } from 'react';

import { GroupsScreen } from '../screens/GroupsScreen';
import { HomeScreen } from '../screens/HomeScreen';
import { MarketScreen } from '../screens/MarketScreen';
import { ProfileScreen } from '../screens/ProfileScreen';
import { WalletScreen } from '../screens/WalletScreen';
import { colors } from '../theme';
import { MainTabParamList } from './types';

type IconName = ComponentProps<typeof Ionicons>['name'];

const ICONS: Record<keyof MainTabParamList, { focused: IconName; idle: IconName }> = {
  Home: { focused: 'home', idle: 'home-outline' },
  Groups: { focused: 'people', idle: 'people-outline' },
  Market: { focused: 'pricetag', idle: 'pricetag-outline' },
  Wallet: { focused: 'wallet', idle: 'wallet-outline' },
  Profile: { focused: 'person', idle: 'person-outline' },
};

const Tab = createBottomTabNavigator<MainTabParamList>();

export function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textMuted,
        tabBarIcon: ({ focused, color, size }) => (
          <Ionicons name={focused ? ICONS[route.name].focused : ICONS[route.name].idle} size={size} color={color} />
        ),
      })}
    >
      <Tab.Screen name="Home" component={HomeScreen} />
      <Tab.Screen name="Groups" component={GroupsScreen} options={{ title: 'Study Groups' }} />
      <Tab.Screen name="Market" component={MarketScreen} options={{ title: 'Marketplace' }} />
      <Tab.Screen name="Wallet" component={WalletScreen} options={{ title: 'Lending' }} />
      <Tab.Screen name="Profile" component={ProfileScreen} />
    </Tab.Navigator>
  );
}
