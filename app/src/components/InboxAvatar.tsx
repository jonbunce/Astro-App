import { Image } from 'expo-image';
import { StyleSheet, Text, View } from 'react-native';

import type { InboxItem } from '@/api/types';
import { img, imageSource } from '@/images';
import { colors } from '@/theme';

/** Who a message is from: the club badge, a team crest, ASTRO, or a payment card icon. */
export function InboxAvatar({ item, size }: { item: InboxItem; size: number }) {
  if (item.type === 'team_news' && item.image) {
    return <Image source={imageSource(item.image)} style={{ width: size, height: size, borderRadius: 12 }} />;
  }
  if (item.type === 'welcome') {
    return (
      <View style={[styles.iconBox, { width: size, height: size, backgroundColor: colors.carbon800 }]}>
        <Image source={img.astro} contentFit="contain" style={{ width: size - 6, height: size - 16 }} />
      </View>
    );
  }
  if (item.type === 'payment') {
    return (
      <View style={[styles.iconBox, { width: size, height: size }]}>
        <Text style={{ color: colors.white, fontSize: 18 }}>£</Text>
      </View>
    );
  }
  return <Image source={img.badge} style={{ width: size, height: size }} accessibilityLabel="Wombourne Allstars FC" />;
}

const styles = StyleSheet.create({
  iconBox: {
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.1)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
});
