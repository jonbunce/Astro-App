import { StyleSheet, Text, type TextProps } from 'react-native';

import { colors, fonts } from '@/theme';

type Variant = 'body' | 'strong' | 'small' | 'caption' | 'label' | 'link';

export function Txt({ variant = 'body', style, ...rest }: TextProps & { variant?: Variant }) {
  return <Text {...rest} style={[styles.base, styles[variant], style]} />;
}

/** Big condensed italic capitals, like the website's headline type. */
export function Title({ size = 52, style, ...rest }: TextProps & { size?: number }) {
  return (
    <Text
      accessibilityRole="header"
      {...rest}
      style={[styles.title, { fontSize: size, lineHeight: Math.round(size * 0.92) }, style]}
    />
  );
}

/** Section headings ("Matchday", "Latest"). */
export function Heading({ style, ...rest }: TextProps) {
  return <Text accessibilityRole="header" {...rest} style={[styles.heading, style]} />;
}

/** Big italic numbers (subs, stats). */
export function Figure({ size = 24, style, ...rest }: TextProps & { size?: number }) {
  return <Text {...rest} style={[styles.figure, { fontSize: size, lineHeight: Math.round(size * 1.05) }, style]} />;
}

const styles = StyleSheet.create({
  base: { color: colors.white, fontFamily: fonts.body, fontSize: 15, lineHeight: 21 },
  body: {},
  strong: { fontFamily: fonts.bold },
  small: { fontSize: 13, lineHeight: 18, color: colors.textMuted },
  caption: { fontSize: 12, lineHeight: 16, color: colors.textFaint },
  label: {
    fontFamily: fonts.bold,
    fontSize: 11,
    lineHeight: 14,
    letterSpacing: 1.4,
    textTransform: 'uppercase',
    color: colors.gold,
  },
  link: { fontFamily: fonts.bold, fontSize: 14, color: colors.gold },
  title: {
    color: colors.white,
    fontFamily: fonts.display,
    textTransform: 'uppercase',
    textShadowColor: 'rgba(0,0,0,0.5)',
    textShadowOffset: { width: 0, height: 4 },
    textShadowRadius: 18,
  },
  heading: {
    color: colors.white,
    fontFamily: fonts.display,
    fontSize: 22,
    lineHeight: 24,
    textTransform: 'uppercase',
  },
  figure: { color: colors.white, fontFamily: fonts.black },
});
