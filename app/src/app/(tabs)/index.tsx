import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { useFamily, useHome, useMe } from '@/api/hooks';
import type { Fixture } from '@/api/types';
import { Backdrop } from '@/components/Backdrop';
import { PillButton } from '@/components/Buttons';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { NewsRow } from '@/components/NewsRow';
import { Screen } from '@/components/Screen';
import { Heading, Title, Txt } from '@/components/Txt';
import { crestSource, img, imageSource } from '@/images';
import { formatDate } from '@/lib/website';
import { colors, fonts } from '@/theme';

export default function Home() {
  const me = useMe();
  const home = useHome();
  const family = useFamily();
  // Anything owed is worked out from the family's own data, not sent with the club content.
  const owing = family.data?.find((c) => c.arrears);
  const today = formatDate(new Date().toISOString(), { weekday: 'long', day: 'numeric', month: 'short' });
  const [next, ...others] = home.data?.fixtures ?? [];

  return (
    <Screen
      backdrop={<Backdrop photo={img.grassBall} position={{ left: '40%', top: '60%' }} height={330} />}
      refreshing={home.isRefetching}
      onRefresh={() => {
        me.refetch();
        home.refetch();
        family.refetch();
      }}>
      <View style={styles.header}>
        <View style={{ gap: 10 }}>
          <Kicker>{today}</Kicker>
          <Title>{me.data ? `Hi ${me.data.firstName}` : 'Hello'}</Title>
        </View>
        <Image source={img.badge} style={styles.badge} accessibilityLabel="Wombourne Allstars FC badge" />
      </View>

      {owing?.arrears ? (
        <Glass tone="gold" radius={22} style={styles.alert}>
          <Image source={crestSource(owing.team.crest)} style={styles.alertCrest} />
          <View style={{ flex: 1, gap: 2 }}>
            <Txt variant="strong">{`${owing.firstName}'s subs need attention`}</Txt>
            <Txt variant="small" style={{ color: 'rgba(255,255,255,0.8)' }}>
              {owing.arrears.reason}
            </Txt>
          </View>
          <PillButton label="Review" onPress={() => router.navigate('/family')} />
        </Glass>
      ) : null}

      {next ? (
        <View style={{ gap: 10 }}>
          <View style={styles.sectionHead}>
            <Heading>Matchday</Heading>
            <Txt variant="caption" style={{ fontFamily: fonts.bold }}>
              Adult teams
            </Txt>
          </View>
          <MatchdayCard fixture={next} />
          <View style={styles.grid}>
            {others.slice(0, 2).map((f) => (
              <Glass key={f.team} radius={22} style={styles.mini}>
                <Txt variant="label">
                  {f.team} · {formatKickoff(f.kickoff, true)}
                </Txt>
                <Text style={styles.miniOpponent}>{f.opponent}</Text>
                <Txt variant="caption" style={{ color: colors.textMuted }}>
                  {f.homeAway === 'H' ? 'Home' : 'Away'}
                  {f.venue ? ` · ${f.venue}` : ''}
                </Txt>
              </Glass>
            ))}
          </View>
        </View>
      ) : null}

      {home.data?.latest.length ? (
        <View style={{ gap: 10 }}>
          <View style={styles.sectionHead}>
            <Heading>Latest</Heading>
            <Pressable onPress={() => router.navigate('/news')} hitSlop={8}>
              <Txt variant="link">See all</Txt>
            </Pressable>
          </View>
          {home.data.latest.map((n) => (
            <NewsRow key={n.id} item={n} />
          ))}
        </View>
      ) : null}
    </Screen>
  );
}

function formatKickoff(iso: string, short = false) {
  const d = new Date(iso);
  const time = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
  if (d.toDateString() === new Date().toDateString()) return short ? time : `Today ${time}`;
  return short ? `${d.toLocaleDateString('en-GB', { weekday: 'short' })} ${time}` : formatDate(iso, { weekday: 'short', day: 'numeric', month: 'short' }) + ` ${time}`;
}

/** The red "up next" card, styled after the team crests' red/black halftone artwork. */
function MatchdayCard({ fixture }: { fixture: Fixture }) {
  // The club's own first-team photo until the API supplies match photos.
  const photo = imageSource(fixture.photo) ?? (/^men/i.test(fixture.team) ? img.firstTeam : undefined);
  return (
    <Pressable accessibilityRole="button" onPress={() => router.navigate('/news')} style={styles.match}>
      <LinearGradient
        colors={['#d4122f', colors.red, colors.redDark, '#6e0819']}
        locations={[0, 0.5, 0.5, 1]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 0.6 }}
        style={StyleSheet.absoluteFill}
      />
      {photo ? (
        <View style={styles.matchPhotoWrap}>
          <Image
            source={photo}
            contentFit="cover"
            contentPosition={{ left: '50%', top: '20%' }}
            style={StyleSheet.absoluteFill}
            accessibilityLabel={`${fixture.team} player`}
          />
          <LinearGradient
            colors={['rgba(200,16,46,0.9)', 'rgba(200,16,46,0.15)', 'rgba(0,0,0,0)']}
            locations={[0, 0.45, 1]}
            start={{ x: 0, y: 0.5 }}
            end={{ x: 1, y: 0.5 }}
            style={StyleSheet.absoluteFill}
          />
        </View>
      ) : null}
      <View style={styles.matchBody}>
        <View style={styles.matchTeam}>
          <Text style={styles.matchTeamText}>{fixture.team}</Text>
        </View>
        <Text style={styles.matchWhen}>
          {formatKickoff(fixture.kickoff)} · {fixture.homeAway === 'H' ? 'Home' : 'Away'}
        </Text>
        <Text style={styles.matchOpponent} numberOfLines={1} adjustsFontSizeToFit>
          {fixture.opponent}
        </Text>
        <View style={styles.matchFoot}>
          {fixture.position ? (
            <View style={styles.matchChip}>
              <Text style={styles.matchChipText}>
                {fixture.position}
                {fixture.league ? ` · ${fixture.league}` : ''}
              </Text>
            </View>
          ) : null}
          <View style={{ flexDirection: 'row', gap: 3 }} accessibilityLabel={`Recent form ${fixture.form?.join(' ') ?? ''}`}>
            {fixture.form?.slice(-4).map((r, i) => (
              <View key={i} style={[styles.form, r === 'W' ? styles.formWin : styles.formOther]}>
                <Text style={[styles.formText, r === 'W' && { color: colors.red }]}>{r}</Text>
              </View>
            ))}
          </View>
        </View>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  badge: { width: 58, height: 58 },
  alert: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, paddingLeft: 14 },
  alertCrest: { width: 40, height: 40, borderRadius: 12, borderWidth: 1.5, borderColor: colors.gold },
  sectionHead: { flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between' },
  grid: { flexDirection: 'row', gap: 10 },
  mini: { flex: 1, padding: 14, gap: 5 },
  miniOpponent: { color: colors.white, fontFamily: fonts.displaySemi, fontSize: 19, lineHeight: 20, textTransform: 'uppercase' },
  match: {
    height: 196,
    borderRadius: 28,
    borderCurve: 'continuous',
    overflow: 'hidden',
    shadowColor: colors.red,
    shadowOpacity: 0.35,
    shadowRadius: 22,
    shadowOffset: { width: 0, height: 18 },
  },
  matchPhotoWrap: { position: 'absolute', right: 0, top: 0, bottom: 0, width: '56%' },
  matchBody: { flex: 1, padding: 18, width: '62%' },
  matchTeam: {
    alignSelf: 'flex-start',
    paddingHorizontal: 11,
    paddingVertical: 5,
    borderRadius: 14,
    backgroundColor: 'rgba(0,0,0,0.26)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.24)',
  },
  matchTeamText: { color: colors.white, fontFamily: fonts.bold, fontSize: 12 },
  matchWhen: {
    marginTop: 14,
    color: 'rgba(255,255,255,0.92)',
    fontFamily: fonts.bold,
    fontSize: 11,
    letterSpacing: 1.6,
    textTransform: 'uppercase',
  },
  matchOpponent: { color: colors.white, fontFamily: fonts.display, fontSize: 58, lineHeight: 56, textTransform: 'uppercase' },
  matchFoot: { marginTop: 'auto', flexDirection: 'row', alignItems: 'center', gap: 8 },
  matchChip: {
    paddingHorizontal: 11,
    paddingVertical: 6,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.16)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.3)',
  },
  matchChipText: { color: colors.white, fontFamily: fonts.bold, fontSize: 12 },
  form: { width: 18, height: 18, borderRadius: 5, alignItems: 'center', justifyContent: 'center' },
  formWin: { backgroundColor: colors.white },
  formOther: { borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.75)' },
  formText: { color: colors.white, fontFamily: fonts.heavy, fontSize: 10 },
});
