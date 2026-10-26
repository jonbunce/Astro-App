import { router } from 'expo-router';
import { Pressable, StyleSheet, Text } from 'react-native';

import { Glass } from '@/components/Glass';
import { Txt } from '@/components/Txt';
import { colors, fonts } from '@/theme';

/** Glass back button with the name of the screen it returns to. */
export function BackLink({ label }: { label: string }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={`Back to ${label.toLowerCase()}`} onPress={() => router.back()} style={styles.back}>
      <Glass radius={22} style={styles.circle}>
        <Text style={styles.arrow}>‹</Text>
      </Glass>
      <Txt style={{ fontFamily: fonts.semibold }}>{label}</Txt>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  back: { flexDirection: 'row', alignItems: 'center', gap: 10, alignSelf: 'flex-start' },
  circle: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
  arrow: { color: colors.white, fontSize: 28, lineHeight: 30, marginTop: -2 },
});
