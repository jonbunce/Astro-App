import { BlurView } from 'expo-blur';
import { GlassView, isGlassEffectAPIAvailable, isLiquidGlassAvailable } from 'expo-glass-effect';
import type { ReactNode } from 'react';
import { Platform, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, radius as radii } from '@/theme';

type Tone = 'default' | 'gold' | 'red' | 'quiet';

const liquid = Platform.OS === 'ios' && isLiquidGlassAvailable() && isGlassEffectAPIAvailable();

const tones: Record<Tone, { tint: string; border: string; fill: string }> = {
  default: { tint: 'rgba(20,22,26,0.35)', border: colors.glassBorder, fill: 'rgba(28,30,36,0.55)' },
  quiet: { tint: 'rgba(20,22,26,0.2)', border: 'rgba(255,255,255,0.10)', fill: 'rgba(28,30,36,0.45)' },
  gold: { tint: 'rgba(227,154,0,0.22)', border: 'rgba(227,154,0,0.45)', fill: 'rgba(60,44,12,0.7)' },
  red: { tint: 'rgba(200,16,46,0.28)', border: 'rgba(255,90,110,0.36)', fill: 'rgba(70,14,24,0.72)' },
};

/**
 * Frosted panel. Apple's Liquid Glass on iOS 26+, a system blur on older iOS,
 * and a tinted translucent panel on Android.
 */
export function Glass({
  children,
  style,
  tone = 'default',
  radius = radii.card,
}: {
  children?: ReactNode;
  style?: StyleProp<ViewStyle>;
  tone?: Tone;
  radius?: number;
}) {
  const t = tones[tone];
  const shape: ViewStyle = { borderRadius: radius, borderCurve: 'continuous', overflow: 'hidden' };

  if (liquid) {
    return (
      <GlassView
        glassEffectStyle="regular"
        colorScheme="dark"
        tintColor={t.tint}
        style={[shape, tone !== 'default' && { borderWidth: 1, borderColor: t.border }, style]}>
        {children}
      </GlassView>
    );
  }

  return (
    <View style={[shape, styles.edge, { borderColor: t.border }, style]}>
      {Platform.OS === 'ios' ? (
        <BlurView intensity={40} tint="systemUltraThinMaterialDark" style={StyleSheet.absoluteFill} />
      ) : null}
      <View
        pointerEvents="none"
        style={[StyleSheet.absoluteFill, { backgroundColor: Platform.OS === 'ios' ? t.tint : t.fill }]}
      />
      <View pointerEvents="none" style={styles.highlight} />
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  edge: { borderWidth: 1 },
  highlight: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 1,
    backgroundColor: colors.glassHighlight,
  },
});
