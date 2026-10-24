import { Image } from 'expo-image';
import { Pressable, StyleSheet, View } from 'react-native';

import type { NewsItem } from '@/api/types';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { Txt } from '@/components/Txt';
import { imageSource } from '@/images';
import { formatDate, openPage, relativeTime } from '@/lib/website';
import { colors } from '@/theme';

export function NewsRow({ item }: { item: NewsItem }) {
  const recent = Date.now() - new Date(item.date).getTime() < 7 * 86_400_000;
  return (
    <Pressable accessibilityRole="link" onPress={() => openPage(item.url, item.team ?? item.category ?? 'News')}>
      {({ pressed }) => (
        <Glass radius={22} style={[styles.row, pressed && { opacity: 0.85 }]}>
          {item.image ? (
            <Image source={imageSource(item.image)} contentFit="cover" style={styles.thumb} />
          ) : (
            <View style={[styles.thumb, { backgroundColor: colors.carbon700 }]} />
          )}
          <View style={{ flex: 1, gap: 4 }}>
            {item.team ? <Kicker small>{item.team}</Kicker> : <Txt variant="label" style={{ fontSize: 10 }}>{item.category ?? 'Club news'}</Txt>}
            <Txt variant="strong" numberOfLines={2} style={{ lineHeight: 19 }}>
              {item.title}
            </Txt>
            <Txt variant="caption">{recent ? relativeTime(item.date) : formatDate(item.date)}</Txt>
          </View>
        </Glass>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 10 },
  thumb: { width: 64, height: 64, borderRadius: 16 },
});
