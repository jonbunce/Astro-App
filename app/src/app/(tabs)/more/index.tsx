import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import { useConfig, useMe } from '@/api/hooks';
import type { InfoLink } from '@/api/types';
import { useAuth } from '@/auth/AuthContext';
import { Backdrop } from '@/components/Backdrop';
import { Glass } from '@/components/Glass';
import { Screen } from '@/components/Screen';
import { Heading, Title, Txt } from '@/components/Txt';
import { img, imageSource } from '@/images';
import { openPage, openSignedIn } from '@/lib/website';
import { colors, fonts } from '@/theme';

const roleNames: Record<string, string> = {
  parent: 'Parent',
  coach: 'Coach',
  committee: 'Committee',
  treasurer: 'Treasurer',
  social_media: 'Social media',
  registration_officer: 'Registrations',
};

export default function More() {
  const me = useMe();
  const config = useConfig();
  const { signOut } = useAuth();
  const club = config.data?.clubPages ?? [];
  const photoTiles = club.filter((p) => p.image).slice(0, 2);
  const plainTiles = club.filter((p) => !p.image).slice(0, 2);

  function confirmSignOut() {
    Alert.alert('Sign out?', 'You can sign back in with your ASTRO email and password.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Sign out', style: 'destructive', onPress: () => void signOut() },
    ]);
  }

  return (
    <Screen backdrop={<Backdrop photo={img.wallBadge} position={{ left: '50%', top: '45%' }} height={372} redWash={false} />}>
      <Glass style={styles.profile}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{me.data?.firstName.charAt(0) ?? ''}</Text>
        </View>
        <View style={{ flex: 1, gap: 4 }}>
          <Title size={32} style={{ textShadowRadius: 0 }}>
            {me.data?.firstName ?? ''}
          </Title>
          <Txt variant="small" numberOfLines={1}>
            {[me.data?.roles.map((r) => roleNames[r]).join(', '), me.data?.email].filter(Boolean).join(' · ')}
          </Txt>
        </View>
        <Pressable accessibilityRole="button" onPress={() => openSignedIn('/account-portal/')} style={styles.profileBtn}>
          <Txt style={{ fontFamily: fonts.semibold, fontSize: 13 }}>Profile</Txt>
        </Pressable>
      </Glass>

      <Glass tone="quiet" radius={20} style={styles.accredited}>
        <Image
          source={img.faAccredited}
          contentFit="contain"
          style={{ width: 116, height: 40 }}
          accessibilityLabel="FA Accredited Club, two stars, part of England Football"
        />
        <Image source={img.badge} style={{ width: 40, height: 40 }} accessibilityLabel="Wombourne Allstars FC badge" />
      </Glass>

      <View style={{ gap: 8 }}>
        <Heading style={{ fontSize: 20, marginHorizontal: 4 }}>The club</Heading>
        <View style={styles.tiles}>
          {photoTiles.map((p) => (
            <PhotoTile key={p.url} page={p} />
          ))}
          {plainTiles.map((p) => (
            <Pressable key={p.url} accessibilityRole="link" onPress={() => openPage(p.url, p.title)} style={styles.tileWrap}>
              <Glass radius={18} style={styles.tile}>
                <Txt style={{ fontFamily: fonts.semibold, fontSize: 14 }}>{p.title}</Txt>
              </Glass>
            </Pressable>
          ))}
        </View>
      </View>

      <View style={{ gap: 8 }}>
        <Heading style={{ fontSize: 20, marginHorizontal: 4 }}>Help & account</Heading>
        <Glass radius={22} style={{ paddingHorizontal: 16 }}>
          {(config.data?.helpPages ?? []).map((p) => (
            <Row key={p.url} label={p.title} onPress={() => openPage(p.url, p.title)} chevron />
          ))}
          <Row label="Notifications" value="Push + email" onPress={() => openSignedIn('/account-portal/')} />
          <Row label="Signed-in devices" onPress={() => router.push('/more/devices')} chevron />
          <Row label="Sign out" bold onPress={confirmSignOut} />
          <Row label="Delete my account" danger last onPress={() => router.push('/delete-account')} />
        </Glass>
      </View>
    </Screen>
  );
}

function PhotoTile({ page }: { page: InfoLink }) {
  return (
    <Pressable accessibilityRole="link" onPress={() => openPage(page.url, page.title)} style={styles.tileWrap}>
      <View style={styles.photoTile}>
        <Image source={imageSource(page.image)} contentFit="cover" contentPosition={{ left: '50%', top: '75%' }} style={StyleSheet.absoluteFill} />
        <LinearGradient colors={['rgba(11,12,15,0.1)', 'rgba(11,12,15,0.8)']} style={StyleSheet.absoluteFill} />
        <Txt variant="strong" style={{ fontSize: 14 }}>
          {page.title}
        </Txt>
      </View>
    </Pressable>
  );
}

function Row({
  label,
  value,
  onPress,
  chevron,
  bold,
  danger,
  last,
}: {
  label: string;
  value?: string;
  onPress: () => void;
  chevron?: boolean;
  bold?: boolean;
  danger?: boolean;
  last?: boolean;
}) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={[styles.row, !last && styles.divider]}>
      <Txt style={[bold && { fontFamily: fonts.semibold }, danger && { color: colors.alert, fontFamily: fonts.semibold }]}>{label}</Txt>
      {value ? <Txt style={{ color: colors.gold, fontFamily: fonts.semibold, fontSize: 13 }}>{value}</Txt> : null}
      {chevron ? <Txt style={{ color: colors.textFaint, fontSize: 20 }}>›</Txt> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  profile: { flexDirection: 'row', alignItems: 'center', gap: 14, paddingVertical: 14, paddingHorizontal: 16 },
  avatar: { width: 52, height: 52, borderRadius: 26, backgroundColor: colors.gold, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: colors.ink, fontFamily: fonts.black, fontSize: 22 },
  profileBtn: {
    height: 40,
    paddingHorizontal: 14,
    borderRadius: 20,
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.18)',
  },
  accredited: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 14, paddingVertical: 10 },
  tiles: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  tileWrap: { width: '48.8%' },
  photoTile: {
    height: 64,
    borderRadius: 18,
    overflow: 'hidden',
    justifyContent: 'flex-end',
    padding: 12,
    paddingBottom: 10,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.14)',
  },
  tile: { height: 50, justifyContent: 'center', paddingHorizontal: 14 },
  row: { minHeight: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  divider: { borderBottomWidth: 1, borderBottomColor: colors.line },
});
