import { router, useLocalSearchParams } from 'expo-router';
import { useEffect } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { useInbox, useMarkRead } from '@/api/hooks';
import type { InboxItem } from '@/api/types';
import { BackLink } from '@/components/BackLink';
import { Backdrop } from '@/components/Backdrop';
import { GlassButton, PrimaryButton } from '@/components/Buttons';
import { Glass } from '@/components/Glass';
import { InboxAvatar } from '@/components/InboxAvatar';
import { Kicker } from '@/components/Kicker';
import { Screen } from '@/components/Screen';
import { Title, Txt } from '@/components/Txt';
import { crestSource, img } from '@/images';
import { openPage } from '@/lib/website';
import { colors } from '@/theme';

const kinds: Record<InboxItem['type'], string> = {
  club_message: 'Club message',
  team_news: 'Team news',
  payment: 'Subs',
  welcome: 'Welcome',
};

export default function InboxMessage() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const inbox = useInbox();
  const { mutate: markRead } = useMarkRead();
  const item = inbox.data?.find((m) => m.id === Number(id));

  // Opening a message reads it (the list updates straight away, so this runs once).
  useEffect(() => {
    if (item && !item.read) markRead(item.id);
  }, [item, markRead]);

  if (!item) {
    return (
      <Screen backdrop={<Backdrop />}>
        <BackLink label="Inbox" />
        {inbox.isLoading ? (
          <ActivityIndicator color={colors.gold} style={{ marginTop: 40 }} />
        ) : (
          <Txt variant="small">This message is no longer in your inbox.</Txt>
        )}
      </Screen>
    );
  }

  const backdrop =
    item.type === 'team_news' && item.image ? (
      <Backdrop photo={crestSource(item.image)} height={320} fadeLeft redWash={false} />
    ) : (
      <Backdrop photo={img.grassBall} position={{ left: '90%', top: '50%' }} height={260} redWash={false} />
    );
  const sent = new Date(item.createdAt);

  return (
    <Screen backdrop={backdrop}>
      <BackLink label="Inbox" />

      <View style={styles.head}>
        <Kicker>{kinds[item.type]}</Kicker>
        <Title size={40} style={{ lineHeight: 42 }}>
          {item.title}
        </Title>
        <View style={styles.from}>
          <InboxAvatar item={item} size={36} />
          <View style={{ flex: 1 }}>
            <Txt style={{ fontSize: 14 }}>{item.from ?? 'Wombourne Allstars'}</Txt>
            <Txt variant="caption">
              {sent.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })},{' '}
              {sent.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}
            </Txt>
          </View>
        </View>
      </View>

      <Glass style={styles.body}>
        <Txt selectable style={{ fontSize: 16, lineHeight: 24 }}>
          {item.body}
        </Txt>
      </Glass>

      {item.type === 'team_news' && item.url ? (
        <PrimaryButton label="Read the article" onPress={() => openPage(item.url!, 'Team news')} />
      ) : null}
      {item.type === 'payment' ? <GlassButton label="See my family" onPress={() => router.navigate('/family')} /> : null}

      {item.type === 'club_message' ? (
        <Txt variant="caption" style={{ textAlign: 'center' }}>
          This message was also sent to you by email.
        </Txt>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  head: { marginTop: 28, gap: 12 },
  from: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  body: { padding: 18 },
});
