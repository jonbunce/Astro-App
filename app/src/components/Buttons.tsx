import { LinearGradient } from 'expo-linear-gradient';
import type { ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { Glass } from '@/components/Glass';
import { colors, fonts } from '@/theme';

type Base = {
  label?: string;
  children?: ReactNode;
  onPress?: () => void;
  disabled?: boolean;
  loading?: boolean;
  style?: StyleProp<ViewStyle>;
  accessibilityLabel?: string;
};

/** Main call to action: club red, italic capitals, red glow. */
export function PrimaryButton({ label, children, onPress, disabled, loading, style, accessibilityLabel }: Base) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled: disabled || loading, busy: loading }}
      disabled={disabled || loading}
      onPress={onPress}
      style={({ pressed }) => [styles.primaryWrap, pressed && styles.pressed, disabled && styles.disabled, style]}>
      <LinearGradient colors={[colors.redBright, colors.redDeep]} style={styles.primary}>
        <View style={styles.primaryHighlight} />
        {loading ? (
          <ActivityIndicator color={colors.white} />
        ) : (
          <View style={styles.row}>
            {label ? <Text style={styles.primaryText}>{label}</Text> : null}
            {children}
          </View>
        )}
      </LinearGradient>
    </Pressable>
  );
}

/** Secondary action on frosted glass. */
export function GlassButton({ label, children, onPress, disabled, style, accessibilityLabel }: Base) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => [pressed && styles.pressed, style]}>
      <Glass radius={27} style={styles.glass}>
        <View style={styles.row}>
          {children}
          {label ? <Text style={styles.glassText}>{label}</Text> : null}
        </View>
      </Glass>
    </Pressable>
  );
}

/** Small solid pill, e.g. "Review" or "Pay now". */
export function PillButton({
  label,
  onPress,
  variant = 'gold',
}: {
  label: string;
  onPress?: () => void;
  variant?: 'gold' | 'red';
}) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [styles.pill, variant === 'red' ? styles.pillRed : styles.pillGold, pressed && styles.pressed]}>
      <Text style={[styles.pillText, variant === 'red' && { color: colors.white }]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pressed: { opacity: 0.85, transform: [{ scale: 0.985 }] },
  disabled: { opacity: 0.5 },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10 },
  primaryWrap: {
    borderRadius: 28,
    shadowColor: colors.red,
    shadowOpacity: 0.5,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 10 },
    elevation: 8,
  },
  primary: { height: 56, borderRadius: 28, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  primaryHighlight: { position: 'absolute', top: 0, left: 0, right: 0, height: 1, backgroundColor: 'rgba(255,255,255,0.35)' },
  primaryText: {
    color: colors.white,
    fontFamily: fonts.displaySemi,
    fontSize: 17,
    letterSpacing: 0.6,
    textTransform: 'uppercase',
  },
  glass: { height: 54, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 18 },
  glassText: { color: colors.white, fontFamily: fonts.bold, fontSize: 15 },
  pill: { height: 44, paddingHorizontal: 16, borderRadius: 22, alignItems: 'center', justifyContent: 'center' },
  pillGold: { backgroundColor: colors.gold },
  pillRed: {
    backgroundColor: colors.redBright,
    shadowColor: colors.red,
    shadowOpacity: 0.5,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 6 },
    elevation: 6,
  },
  pillText: { color: colors.ink, fontFamily: fonts.bold, fontSize: 14 },
});
