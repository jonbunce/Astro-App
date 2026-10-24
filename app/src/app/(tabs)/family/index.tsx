import { Image } from 'expo-image';
import { router } from 'expo-router';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { useFamily } from '@/api/hooks';
import type { Child } from '@/api/types';
import { Backdrop } from '@/components/Backdrop';
import { GlassButton, PillButton } from '@/components/Buttons';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { Screen } from '@/components/Screen';
import { Figure, Title, Txt } from '@/components/Txt';
import { crestSource, img } from '@/images';
import { formatDate, formatMoney, openSignedIn } from '@/lib/website';
import { colors } from '@/theme';

export default function Family() {
  const family = useFamily();
  return (
    <Screen
      backdrop={<Backdrop photo={img.pitch} position={{ left: '50%', top: '70%' }} height={300} />}
      refreshing={family.isRefetching}
      onRefresh={() => family.refetch()}>
      <View style={styles.header}>
        <View style={{ gap: 10 }}>
          <Kicker>2026-27 season</Kicker>
          <Title>My family</Title>
        </View>
        <Glass radius={16} style={styles.powered}>
          <Txt style={styles.poweredText}>Powered by</Txt>
          <Image source={img.astro} contentFit="contain" style={{ width: 78, height: 28 }} accessibilityLabel="ASTRO" />
        </Glass>
      </View>

      {family.isLoading ? <ActivityIndicator color={colors.gold} style={{ marginTop: 40 }} /> : null}
      {family.error ? (
        <Txt variant="small" style={{ color: colors.alert }}>
          Couldn't load your family just now. Pull down to try again.
        </Txt>
      ) : null}

      {family.data?.map((child) => <ChildCard key={child.id} child={child} />)}

      <GlassButton label="Register another child" onPress={() => openSignedIn('/registration/')}>
        <Txt style={{ fontSize: 20, lineHeight: 22 }}>+</Txt>
      </GlassButton>
    </Screen>
  );
}

function ChildCard({ child }: { child: Child }) {
  const owing = !!child.arrears;
  const open = () => router.push({ pathname: '/family/[id]', params: { id: String(child.id) } });
  // The card opens the child's details, except the "Pay now" button, which is its own control.
  return (
    <Glass tone={owing ? 'red' : 'default'} style={styles.card}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${child.firstName}, ${child.team.ageGroup} ${child.team.name}. View details`}
        onPress={open}
        style={styles.cardHead}>
        <Image source={crestSource(child.team.crest)} contentFit="cover" style={styles.crest} />
        <View style={{ flex: 1, gap: 6 }}>
          <Title size={30} style={{ textShadowRadius: 0 }}>
            {child.firstName}
          </Title>
          <Kicker small>{`${child.team.ageGroup} ${child.team.name}`}</Kicker>
        </View>
        {owing ? (
          <View style={[styles.status, { backgroundColor: colors.gold }]}>
            <Txt style={[styles.statusText, { color: colors.ink }]}>Payment issue</Txt>
          </View>
        ) : (
          <View style={styles.status}>
            <Txt style={styles.statusText}>{child.status === 'active' ? 'Active' : 'Pending'}</Txt>
          </View>
        )}
      </Pressable>

      {owing && child.arrears ? (
        <View style={styles.owing}>
          <View style={{ flex: 1, gap: 2 }}>
            <Figure size={22}>{formatMoney(child.arrears.total)} due</Figure>
            <Txt variant="caption" style={{ color: 'rgba(255,255,255,0.74)' }}>
              {child.arrears.reason}
            </Txt>
          </View>
          <PillButton label="Pay now" variant="red" onPress={() => openSignedIn('/account-portal/')} />
        </View>
      ) : (
        <Pressable accessible={false} onPress={open} style={styles.stats}>
          <View style={styles.stat}>
            <Figure>{formatMoney(child.monthly)}</Figure>
            <Txt variant="caption">Subs a month</Txt>
          </View>
          <View style={styles.stat}>
            <Figure>{child.nextPayment ? formatDate(child.nextPayment, { day: 'numeric', month: 'short' }) : '—'}</Figure>
            <Txt variant="caption">Next Direct Debit</Txt>
          </View>
        </Pressable>
      )}

      <Pressable accessibilityRole="button" onPress={open} style={styles.more} hitSlop={6}>
        <Txt variant="link">View details</Txt>
        <Txt variant="link">›</Txt>
      </Pressable>
    </Glass>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  powered: { paddingHorizontal: 12, paddingVertical: 8, alignItems: 'flex-end', gap: 2 },
  poweredText: { fontSize: 9, lineHeight: 11, letterSpacing: 1.4, textTransform: 'uppercase', color: colors.textMuted },
  card: { padding: 16, gap: 14 },
  cardHead: { flexDirection: 'row', alignItems: 'center', gap: 14 },
  crest: { width: 60, height: 60, borderRadius: 18, borderWidth: 2, borderColor: colors.gold },
  status: {
    paddingHorizontal: 11,
    paddingVertical: 5,
    borderRadius: 14,
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.2)',
  },
  statusText: { fontSize: 12, lineHeight: 15, fontFamily: 'Inter_700Bold' },
  stats: { flexDirection: 'row', gap: 8 },
  stat: {
    flex: 1,
    borderRadius: 16,
    padding: 10,
    paddingHorizontal: 12,
    backgroundColor: 'rgba(0,0,0,0.3)',
    borderWidth: 1,
    borderColor: colors.line,
    gap: 2,
  },
  owing: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderRadius: 18,
    padding: 12,
    backgroundColor: 'rgba(0,0,0,0.3)',
    borderWidth: 1,
    borderColor: colors.line,
  },
  more: { flexDirection: 'row', justifyContent: 'space-between' },
});
