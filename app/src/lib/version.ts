import Constants from 'expo-constants';

/** This build's version from app.json, e.g. "1.0.0". */
export const appVersion: string = Constants.expoConfig?.version ?? '0.0.0';

/** Compare dotted version numbers: negative if a < b, 0 if equal, positive if a > b. */
export function compareVersions(a: string, b: string) {
  const pa = a.split('.').map((n) => parseInt(n, 10) || 0);
  const pb = b.split('.').map((n) => parseInt(n, 10) || 0);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const diff = (pa[i] ?? 0) - (pb[i] ?? 0);
    if (diff) return diff;
  }
  return 0;
}

/** True when the club has said this version is too old to keep using. */
export function needsUpdate(minVersion: string | undefined) {
  return !!minVersion && compareVersions(appVersion, minVersion) < 0;
}
