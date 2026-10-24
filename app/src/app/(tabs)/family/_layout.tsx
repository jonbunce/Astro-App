import { Stack } from 'expo-router';

import { colors } from '@/theme';

export default function FamilyLayout() {
  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.carbon } }} />;
}
