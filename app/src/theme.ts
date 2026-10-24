// Design tokens: the website's "Carbon & Gold" palette (allstars-frontpage/assets/front.css)
// carried into the app's dark, glass "matchday" look.

export const colors = {
  carbon: '#0b0c0f',
  carbon800: '#14161a',
  carbon700: '#1d2025',
  red: '#C8102E',
  redBright: '#dc1637',
  redDeep: '#b30e29',
  redDark: '#8c0a20',
  gold: '#E39A00',
  goldLight: '#f4b63f',
  ink: '#14161a',
  white: '#ffffff',
  textMuted: 'rgba(255,255,255,0.74)',
  textFaint: 'rgba(255,255,255,0.64)',
  line: 'rgba(255,255,255,0.08)',
  glassBorder: 'rgba(255,255,255,0.14)',
  glassHighlight: 'rgba(255,255,255,0.18)',
  alert: '#ff6b80',
} as const;

// Font families are registered under these names in src/app/_layout.tsx.
// Never combine them with fontWeight/fontStyle: the files already carry weight and slant.
export const fonts = {
  display: 'ArchivoCondensedBlackItalic',
  displaySemi: 'ArchivoSemiCondensedExtraBoldItalic',
  black: 'ArchivoBlackItalic',
  heavy: 'ArchivoExtraBold',
  body: 'Inter_400Regular',
  medium: 'Inter_500Medium',
  semibold: 'Inter_600SemiBold',
  bold: 'Inter_700Bold',
} as const;

export const radius = {
  card: 26,
  tile: 20,
  small: 16,
  pill: 999,
} as const;

export const space = {
  screen: 16,
  gap: 14,
} as const;

export const SITE_URL = process.env.EXPO_PUBLIC_SITE_URL ?? 'https://www.wombourneallstars.co.uk';
