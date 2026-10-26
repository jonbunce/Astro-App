import { Stack } from 'expo-router';

import { colors } from '@/theme';

export default function InboxLayout() {
  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.carbon } }} />;
}
