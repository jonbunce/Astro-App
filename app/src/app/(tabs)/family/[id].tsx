import { Image } from 'expo-image';
import { useLocalSearchParams } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { useChild } from '@/api/hooks';
import { BackLink } from '@/components/BackLink';
import { Backdrop } from '@/components/Backdrop';
import { PrimaryButton } from '@/components/Buttons';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { Screen } from '@/components/Screen';
import { Figure, Title, Txt } from '@/components/Txt';
import { crestSource, img } from '@/images';
import { childStatusLabel, subsNote } from '@/lib/childStatus';
import { formatDate, formatMoney, openSignedIn } from '@/lib/website';
import { colors, fonts } from '@/theme';

export default function ChildDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: child } = useChild(Number(id));

  if (!child) {
    return (
      <Screen backdrop={<Backdrop />}>
        <BackLink label="My family" />
        <Txt variant="small">This child isn't on your account.</Txt>
      </Screen>
    );
  }

  const facts = [
    { label: 'Photography', value: child.consents.photography ? 'Agreed' : 'Not agreed' },
    { label: 'Legal proceedings', value: child.consents.legal ? 'Agreed' : 'Not agreed' },
    { label: 'Medical details', value: child.medicalOnFile ? 'On file' : 'Missing' },
    { label: 'Emergency contact', value: child.emergencyOnFile ? 'On file' : 'Missing' },
  ];
  const last = child.lastPayment;

  return (
    <Screen backdrop={<Backdrop photo={crestSource(child.team.crest)} height={362} fadeLeft redWash={false} />}>
      <BackLink label="My family" />
      <View style={{ marginTop: 36, gap: 8 }}>
        <Kicker>{`${child.team.ageGroup} ${child.team.name}`}</Kicker>
        <Title size={64}>{child.firstName}</Title>
        <Txt variant="small" style={{ color: 'rgba(255,255,255,0.82)' }}>
          {child.participation === 'signed' ? 'Signed player' : 'Train only'} · {childStatusLabel(child)}
        </Txt>
      </View>

      <Glass style={styles.subs}>
        <Txt variant="label">Monthly subs</Txt>
        {subsNote(child) ? (
          <Txt variant="strong" style={{ fontSize: 18 }}>
            {subsNote(child)}
          </Txt>
        ) : (
          <View style={styles.total}>
            <Figure size={46}>{formatMoney(child.monthly)}</Figure>
            <Txt variant="small">a month</Txt>
          </View>
        )}
        {child.breakdown.map((line) => (
          <View key={line.label} style={styles.line}>
            <Txt variant="small">{line.label}</Txt>
            <Txt>{formatMoney(line.amount)}</Txt>
          </View>
        ))}
        {last ? (
          <View style={styles.last}>
            <Text style={[styles.lastMark, last.status !== 'paid' && { color: colors.alert }]}>{last.status === 'paid' ? '✓' : '!'}</Text>
            <Txt variant="small" style={{ flex: 1, color: colors.white }}>
              {last.status === 'paid' ? 'Paid' : 'Failed'} {formatDate(last.date, { day: 'numeric', month: 'short' })} · Direct Debit
            </Txt>
          </View>
        ) : null}
      </Glass>

      <View style={styles.facts} accessibilityLabel="Consents and details">
        {facts.map((f) => (
          <Glass key={f.label} radius={20} style={styles.fact}>
            <Txt variant="caption">{f.label}</Txt>
            <Txt variant="strong">{f.value}</Txt>
          </Glass>
        ))}
      </View>

      <PrimaryButton accessibilityLabel="Update details in ASTRO" label="Update in" onPress={() => openSignedIn('/account-portal/')}>
        <Image source={img.astro} contentFit="contain" style={{ width: 84, height: 30 }} />
      </PrimaryButton>
      <Txt variant="caption" style={{ textAlign: 'center' }}>
        Opens the club website, already signed in. Medical answers are only shown there.
      </Txt>
    </Screen>
  );
}

const styles = StyleSheet.create({
  subs: { padding: 16, gap: 10 },
  total: { flexDirection: 'row', alignItems: 'baseline', gap: 8 },
  line: { flexDirection: 'row', justifyContent: 'space-between' },
  last: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 10,
    paddingHorizontal: 12,
    borderRadius: 16,
    backgroundColor: 'rgba(0,0,0,0.3)',
    borderWidth: 1,
    borderColor: colors.line,
  },
  lastMark: { color: colors.gold, fontFamily: fonts.heavy, fontSize: 16 },
  facts: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  fact: { width: '48.5%', padding: 12, gap: 4 },
});
