import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';

import { useEndSession, useSessions } from '@/api/hooks';
import type { Session } from '@/api/types';
import { BackLink } from '@/components/BackLink';
import { Backdrop } from '@/components/Backdrop';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { Screen } from '@/components/Screen';
import { Title, Txt } from '@/components/Txt';
import { img } from '@/images';
import { formatDate, relativeTime } from '@/lib/website';
import { colors, fonts } from '@/theme';

const platforms: Record<string, string> = { ios: 'iOS', android: 'Android' };

export default function Devices() {
  const sessions = useSessions();
  const endSession = useEndSession();
  const list = sessions.data ?? [];

  function confirmSignOut(s: Session) {
    Alert.alert(`Sign out ${s.device}?`, 'It will need your ASTRO email and password to get back in.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Sign out',
        style: 'destructive',
        onPress: () =>
          endSession.mutate(s.id, {
            onError: (e) => Alert.alert('Not signed out', e instanceof Error ? e.message : 'Please try again.'),
          }),
      },
    ]);
  }

  return (
    <Screen
      backdrop={<Backdrop photo={img.wallBadge} position={{ left: '50%', top: '45%' }} height={300} redWash={false} />}
      refreshing={sessions.isRefetching}
      onRefresh={() => sessions.refetch()}>
      <BackLink label="More" />
      <View style={{ marginTop: 28, gap: 10 }}>
        <Kicker>Your account</Kicker>
        <Title size={48}>Signed-in devices</Title>
        <Txt variant="small" style={{ color: 'rgba(255,255,255,0.82)' }}>
          Phones using the Allstars app with your ASTRO login. If you don't recognise one, sign it out and change your
          password on the club website.
        </Txt>
      </View>

      {sessions.isLoading ? <ActivityIndicator color={colors.gold} style={{ marginTop: 32 }} /> : null}
      {sessions.isError ? <Txt variant="small">We couldn't load your devices. Pull down to try again.</Txt> : null}

      {list.length ? (
        <Glass radius={22} style={{ paddingHorizontal: 16 }}>
          {list.map((s, i) => (
            <View key={s.id} style={[styles.row, i < list.length - 1 && styles.divider]}>
              <View style={{ flex: 1, gap: 3 }}>
                <View style={styles.nameRow}>
                  <Txt variant="strong">{s.device}</Txt>
                  {s.current ? (
                    <View style={styles.thisPhone}>
                      <Txt style={styles.thisPhoneText}>This phone</Txt>
                    </View>
                  ) : null}
                </View>
                <Txt variant="caption">
                  {[platforms[s.platform], lastUsed(s.lastUsedAt), `signed in ${formatDate(s.createdAt, { day: 'numeric', month: 'short' })}`]
                    .filter(Boolean)
                    .join(' · ')}
                </Txt>
              </View>
              {s.current ? null : (
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel={`Sign out ${s.device}`}
                  disabled={endSession.isPending}
                  onPress={() => confirmSignOut(s)}
                  style={styles.signOut}>
                  <Txt style={{ fontFamily: fonts.semibold, fontSize: 13, color: colors.alert }}>Sign out</Txt>
                </Pressable>
              )}
            </View>
          ))}
        </Glass>
      ) : null}

      <Txt variant="caption" style={{ textAlign: 'center' }}>
        To sign out this phone, use Sign out on the More screen.
      </Txt>
    </Screen>
  );
}

/** "used today", "used yesterday", "used 3 days ago", "used 19 Oct". */
function lastUsed(iso: string) {
  const when = relativeTime(iso);
  if (/^\d{2}:\d{2}$/.test(when)) return 'used today';
  return `used ${when.charAt(0).toLowerCase()}${when.slice(1)}`;
}

const styles = StyleSheet.create({
  row: { minHeight: 64, flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  divider: { borderBottomWidth: 1, borderBottomColor: colors.line },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  thisPhone: { backgroundColor: 'rgba(227,154,0,0.18)', borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2 },
  thisPhoneText: { color: colors.goldLight, fontFamily: fonts.bold, fontSize: 11, lineHeight: 15 },
  signOut: {
    height: 36,
    paddingHorizontal: 12,
    borderRadius: 18,
    justifyContent: 'center',
    backgroundColor: 'rgba(255,107,128,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(255,107,128,0.3)',
  },
});
