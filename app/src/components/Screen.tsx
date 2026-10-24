import type { ReactNode } from 'react';
import { Platform, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, space } from '@/theme';

/** Scrolling screen body on the carbon background; the backdrop sits behind it. */
export function Screen({
  children,
  backdrop,
  refreshing,
  onRefresh,
}: {
  children: ReactNode;
  backdrop?: ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
}) {
  const insets = useSafeAreaInsets();
  // On iOS the Liquid Glass tab bar floats over content, so leave room to scroll past it.
  const bottom = Platform.OS === 'ios' ? insets.bottom + 96 : 32;
  return (
    <View style={styles.root}>
      {backdrop}
      <ScrollView
        contentInsetAdjustmentBehavior="never"
        contentContainerStyle={[styles.content, { paddingTop: insets.top + 12, paddingBottom: bottom }]}
        refreshControl={
          onRefresh ? (
            <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={colors.gold} />
          ) : undefined
        }>
        {children}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.carbon },
  content: { paddingHorizontal: space.screen, gap: 16 },
});
