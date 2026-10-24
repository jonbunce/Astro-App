import type { ImageSource } from 'expo-image';

/** A bundled asset (require) or a remote image. */
export type Src = number | ImageSource;

// Club media taken from the website's media library. The live app gets team crests
// and news images from the API; these bundled copies cover branding and sample data.
export const img = {
  badge: require('@/assets/images/brand/badge.png'),
  astro: require('@/assets/images/brand/astro-white.png'),
  faAccredited: require('@/assets/images/brand/fa-accredited.png'),
  futures: require('@/assets/images/brand/futures.jpg'),
  wallBadge: require('@/assets/images/brand/wall-badge.jpg'),
  grassBall: require('@/assets/images/brand/grass-ball.jpg'),
  pitch: require('@/assets/images/brand/pitch-wide.jpg'),
  walking: require('@/assets/images/brand/walking.jpg'),
  firstTeam: require('@/assets/images/brand/first-team.jpg'),
  astroLaunch: require('@/assets/images/brand/astro-launch.jpg'),
  glowRed: require('@/assets/images/brand/glow-red.png'),
  glowGold: require('@/assets/images/brand/glow-gold.png'),
} as const;

const crests: Record<string, Src> = {
  panthers: require('@/assets/images/brand/crest-panthers.jpg'),
  sharks: require('@/assets/images/brand/crest-sharks.jpg'),
  jets: require('@/assets/images/brand/crest-jets.jpg'),
  falcons: require('@/assets/images/brand/crest-falcons.jpg'),
  scorpions: require('@/assets/images/brand/crest-scorpions.jpg'),
  mavericks: require('@/assets/images/brand/crest-mavericks.jpg'),
};

const local: Record<string, Src> = {
  wallBadge: img.wallBadge,
  futures: img.futures,
  astroLaunch: img.astroLaunch,
  pitch: img.pitch,
  walking: img.walking,
};

/** A remote URL from the API, or a bundled key from the sample data. */
export function imageSource(ref?: string | null): Src | undefined {
  if (!ref) return undefined;
  if (/^https?:\/\//.test(ref)) return { uri: ref };
  return crests[ref] ?? local[ref];
}

export function crestSource(ref?: string | null): Src {
  return imageSource(ref) ?? img.badge;
}
