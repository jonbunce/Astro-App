import { Image } from 'expo-image';
import * as Linking from 'expo-linking';
import { Platform, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import type { AppConfig } from '@/api/types';
import { Backdrop } from '@/components/Backdrop';
import { PrimaryButton } from '@/components/Buttons';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { Title, Txt } from '@/components/Txt';
import { img } from '@/images';
import { appVersion } from '@/lib/version';
import { colors, space } from '@/theme';

/** Shown instead of the app when the club's minimum version is newer than this build. */
export function UpdateRequired({ config }: { config: AppConfig }) {
  const insets = useSafeAreaInsets();
  const android = Platform.OS === 'android';
  const store = android ? config.storeUrls?.android : config.storeUrls?.ios;
  const storeName = android ? 'Google Play' : 'the App Store';

  return (
    <View style={styles.root}>
      <Backdrop photo={img.grassBall} position={{ left: '70%', top: '50%' }} height={420} />
      <View style={[styles.content, { paddingTop: insets.top + 72, paddingBottom: insets.bottom + 32 }]}>
        <Image source={img.badge} style={styles.badge} accessibilityLabel="Wombourne Allstars FC badge" />
        <View style={{ gap: 10 }}>
          <Kicker>New version</Kicker>
          <Title size={56}>Time to update</Title>
        </View>
        <Glass style={styles.card}>
          <Txt>
            This version of the Allstars app is too old to keep working with ASTRO. Update it from {storeName} to carry on.
          </Txt>
          <Txt variant="caption">
            You have version {appVersion}. The club needs {config.minVersion} or newer.
          </Txt>
        </Glass>
        <View style={{ flex: 1 }} />
        {store ? (
          <PrimaryButton label="Update the app" onPress={() => void Linking.openURL(store)} />
        ) : (
          <Txt variant="small" style={{ textAlign: 'center' }}>
            {android
              ? 'Open Google Play and update Allstars.'
              : "Open the App Store (or TestFlight, if you're testing the app) and update Allstars."}
          </Txt>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.carbon },
  content: { flex: 1, paddingHorizontal: space.screen, gap: 18 },
  badge: { width: 64, height: 64 },
  card: { padding: 16, gap: 10 },
});
