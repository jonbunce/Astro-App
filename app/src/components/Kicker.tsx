import { StyleSheet, Text, View, type ViewStyle } from 'react-native';

import { colors, fonts } from '@/theme';

/** Slanted red label, the app's broadcast-graphics accent. */
export function Kicker({ children, small, style }: { children: string; small?: boolean; style?: ViewStyle }) {
  return (
    <View style={[styles.tag, small && styles.tagSmall, style]}>
      <Text style={[styles.text, small && styles.textSmall]}>{children}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  tag: {
    alignSelf: 'flex-start',
    backgroundColor: colors.red,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 3,
    transform: [{ skewX: '-14deg' }],
  },
  tagSmall: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 2 },
  text: {
    color: colors.white,
    fontFamily: fonts.heavy,
    fontSize: 11,
    letterSpacing: 1.5,
    textTransform: 'uppercase',
    transform: [{ skewX: '14deg' }],
  },
  textSmall: { fontSize: 10, letterSpacing: 1 },
});
