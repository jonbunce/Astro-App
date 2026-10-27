import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { usePage } from '@/api/hooks';
import { BackLink } from '@/components/BackLink';
import { Backdrop } from '@/components/Backdrop';
import { GlassButton } from '@/components/Buttons';
import { Kicker } from '@/components/Kicker';
import { PageBlocks } from '@/components/PageBlocks';
import { Screen } from '@/components/Screen';
import { Title, Txt } from '@/components/Txt';
import { formatDate, openPage } from '@/lib/website';
import { colors, SITE_URL } from '@/theme';

/** A club information page (privacy policy, safeguarding…) as text, straight from the website. */
export default function ClubPage() {
  const { slug, title } = useLocalSearchParams<{ slug: string; title?: string }>();
  const page = usePage(slug);
  const name = title || page.data?.title || '';
  const url = page.data?.url ?? `${SITE_URL}/${slug}/`;

  return (
    <Screen backdrop={<Backdrop />} refreshing={page.isRefetching} onRefresh={() => void page.refetch()}>
      <BackLink label="More" />

      <View style={styles.head}>
        <Kicker>Wombourne Allstars</Kicker>
        <Title size={40} style={{ lineHeight: 42 }}>
          {name}
        </Title>
      </View>

      {page.data ? (
        <>
          <PageBlocks blocks={page.data.blocks} pageUrl={url} title={name} />
          <View style={styles.foot}>
            {page.data.modified ? <Txt variant="caption">Last updated {formatDate(page.data.modified)}</Txt> : null}
            <Txt variant="link" accessibilityRole="link" onPress={() => openPage(url, name)}>
              View on the website
            </Txt>
          </View>
        </>
      ) : page.isError ? (
        <View style={{ gap: 12 }}>
          <Txt variant="small">This page couldn't be loaded. Check your connection, or open it on the website.</Txt>
          <GlassButton label="Open on the website" onPress={() => openPage(url, name)} />
        </View>
      ) : (
        <ActivityIndicator color={colors.gold} style={{ marginTop: 40 }} />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  head: { marginTop: 28, marginBottom: 4, gap: 12 },
  foot: { marginTop: 18, paddingTop: 16, gap: 8, borderTopWidth: 1, borderTopColor: colors.line, alignItems: 'flex-start' },
});
