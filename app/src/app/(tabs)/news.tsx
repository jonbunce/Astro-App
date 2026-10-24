import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { useNews } from '@/api/hooks';
import { Backdrop } from '@/components/Backdrop';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { NewsRow } from '@/components/NewsRow';
import { Screen } from '@/components/Screen';
import { Title, Txt } from '@/components/Txt';
import { img, imageSource } from '@/images';
import { formatDate, openPage } from '@/lib/website';
import { colors, fonts } from '@/theme';

const filters = [
  { key: 'all', label: 'All' },
  { key: 'mine', label: 'My teams' },
  { key: 'club', label: 'Club' },
  { key: 'adults', label: 'Adults' },
] as const;

type Filter = (typeof filters)[number]['key'];

export default function News() {
  const [filter, setFilter] = useState<Filter>('all');
  const news = useNews(filter);
  const items = news.data ?? [];
  const featured = items.find((n) => n.featured);
  const rest = items.filter((n) => n !== featured);

  return (
    <Screen
      backdrop={<Backdrop photo={img.walking} position={{ left: '50%', top: '70%' }} height={240} redWash={false} />}
      refreshing={news.isRefetching}
      onRefresh={() => news.refetch()}>
      <View style={styles.header}>
        <View style={{ gap: 10 }}>
          <Kicker>Club & team news</Kicker>
          <Title>News</Title>
        </View>
        <Image source={img.badge} style={styles.badge} accessibilityLabel="Wombourne Allstars FC badge" />
      </View>

      <Glass radius={26} style={styles.filters}>
        {filters.map((f) => {
          const on = f.key === filter;
          return (
            <Pressable
              key={f.key}
              accessibilityRole="button"
              accessibilityState={{ selected: on }}
              onPress={() => setFilter(f.key)}
              style={[styles.filter, on && styles.filterOn]}>
              <Text style={[styles.filterText, on && { color: colors.white, fontFamily: fonts.bold }]}>{f.label}</Text>
            </Pressable>
          );
        })}
      </Glass>

      {news.isLoading ? <ActivityIndicator color={colors.gold} style={{ marginTop: 40 }} /> : null}

      {featured ? (
        <Pressable accessibilityRole="link" onPress={() => openPage(featured.url, featured.category ?? 'News')} style={styles.featured}>
          <Image source={imageSource(featured.image)} contentFit="cover" contentPosition={{ left: '50%', top: '30%' }} style={styles.featuredImage} />
          <LinearGradient
            colors={['rgba(11,12,15,0)', 'rgba(11,12,15,0.88)', colors.carbon]}
            locations={[0, 0.45, 1]}
            style={styles.featuredText}>
            <Txt variant="label">
              {featured.category ?? 'Club news'} · {formatDate(featured.date)}
            </Txt>
            <Text style={styles.featuredTitle}>{featured.title}</Text>
          </LinearGradient>
        </Pressable>
      ) : null}

      <View style={{ gap: 10 }}>
        {rest.map((n) => (
          <NewsRow key={n.id} item={n} />
        ))}
        {!news.isLoading && items.length === 0 ? (
          <Txt variant="small" style={{ textAlign: 'center', marginTop: 24 }}>
            No news here yet.
          </Txt>
        ) : null}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' },
  badge: { width: 52, height: 52 },
  filters: { flexDirection: 'row', padding: 5, gap: 4 },
  filter: { flex: 1, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  filterOn: { backgroundColor: colors.red },
  filterText: { color: 'rgba(255,255,255,0.82)', fontFamily: fonts.semibold, fontSize: 13 },
  featured: {
    height: 286,
    borderRadius: 28,
    borderCurve: 'continuous',
    overflow: 'hidden',
    justifyContent: 'flex-end',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.14)',
    backgroundColor: colors.carbon,
  },
  featuredImage: { position: 'absolute', top: 0, left: 0, right: 0, height: 230 },
  featuredText: { paddingTop: 70, paddingHorizontal: 18, paddingBottom: 18, gap: 8 },
  featuredTitle: { color: colors.white, fontFamily: fonts.display, fontSize: 28, lineHeight: 27, textTransform: 'uppercase' },
});
