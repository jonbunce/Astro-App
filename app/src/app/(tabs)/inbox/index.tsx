import { router } from 'expo-router';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { useInbox, useMarkRead } from '@/api/hooks';
import type { InboxItem } from '@/api/types';
import { Backdrop } from '@/components/Backdrop';
import { Glass } from '@/components/Glass';
import { InboxAvatar } from '@/components/InboxAvatar';
import { Kicker } from '@/components/Kicker';
import { Screen } from '@/components/Screen';
import { Title, Txt } from '@/components/Txt';
import { img } from '@/images';
import { relativeTime } from '@/lib/website';
import { colors, fonts } from '@/theme';

export default function Inbox() {
  const inbox = useInbox();
  const markRead = useMarkRead();
  const items = inbox.data ?? [];
  const unread = items.filter((m) => !m.read);
  const read = items.filter((m) => m.read);

  function open(item: InboxItem) {
    router.push({ pathname: '/inbox/[id]', params: { id: String(item.id) } });
  }

  return (
    <Screen
      backdrop={<Backdrop photo={img.grassBall} position={{ left: '90%', top: '50%' }} height={230} redWash={false} />}
      refreshing={inbox.isRefetching}
      onRefresh={() => inbox.refetch()}>
      <View style={styles.header}>
        <View style={{ gap: 10 }}>
          <Kicker>{unread.length ? `${unread.length} unread` : 'All caught up'}</Kicker>
          <Title>Inbox</Title>
        </View>
        {unread.length ? (
          <Pressable accessibilityRole="button" onPress={() => unread.forEach((m) => markRead.mutate(m.id))}>
            <Glass radius={20} style={styles.markAll}>
              <Txt style={{ fontFamily: fonts.semibold, fontSize: 13 }}>Mark all read</Txt>
            </Glass>
          </Pressable>
        ) : null}
      </View>

      {inbox.isLoading ? <ActivityIndicator color={colors.gold} style={{ marginTop: 40 }} /> : null}

      {unread.map((m) => (
        <Pressable key={m.id} accessibilityRole="button" accessibilityLabel={`Unread: ${m.title}`} onPress={() => open(m)}>
          <Glass style={styles.unread}>
            <InboxAvatar item={m} size={44} />
            <View style={{ flex: 1, gap: 4 }}>
              <View style={styles.titleRow}>
                <Txt variant="strong" style={{ flex: 1 }}>
                  {m.title}
                </Txt>
                <Txt variant="caption" style={{ color: colors.gold, fontFamily: fonts.bold }}>
                  {relativeTime(m.createdAt)}
                </Txt>
              </View>
              <Txt numberOfLines={3} style={{ color: 'rgba(255,255,255,0.82)', fontSize: 14 }}>
                {m.body}
              </Txt>
              {m.from ? <Txt variant="caption">{m.from}</Txt> : null}
            </View>
          </Glass>
        </Pressable>
      ))}

      {read.length ? (
        <Glass tone="quiet">
          {read.map((m, i) => (
            <Pressable
              key={m.id}
              accessibilityRole="button"
              onPress={() => open(m)}
              style={[styles.readRow, i < read.length - 1 && styles.divider]}>
              <InboxAvatar item={m} size={40} />
              <View style={{ flex: 1, gap: 3 }}>
                <View style={styles.titleRow}>
                  <Txt style={{ flex: 1, fontFamily: fonts.semibold }}>{m.title}</Txt>
                  <Txt variant="caption">{relativeTime(m.createdAt)}</Txt>
                </View>
                <Txt variant="small" numberOfLines={2}>
                  {m.body}
                </Txt>
              </View>
            </Pressable>
          ))}
        </Glass>
      ) : null}

      <Txt variant="caption" style={{ textAlign: 'center' }}>
        Every message is also sent to you by email.
      </Txt>
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' },
  markAll: { height: 40, paddingHorizontal: 14, justifyContent: 'center' },
  unread: { flexDirection: 'row', gap: 12, padding: 14 },
  titleRow: { flexDirection: 'row', gap: 8, alignItems: 'baseline' },
  readRow: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14 },
  divider: { borderBottomWidth: 1, borderBottomColor: colors.line },
});
