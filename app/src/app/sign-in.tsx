import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  TextInput,
  View,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAuth } from '@/auth/AuthContext';
import { PrimaryButton } from '@/components/Buttons';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { Txt } from '@/components/Txt';
import { img } from '@/images';
import { colors, fonts, SITE_URL } from '@/theme';

export default function SignIn() {
  const { signIn } = useAuth();
  const insets = useSafeAreaInsets();
  const { width, height } = useWindowDimensions();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit() {
    if (!email || !password) {
      setError('Enter your email and password.');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await signIn(email, password);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not sign in. Please try again.');
      setBusy(false);
    }
  }

  const photoHeight = Math.round(height * 0.7);

  return (
    <View style={styles.root}>
      <View pointerEvents="none" style={StyleSheet.absoluteFill}>
        <Image
          source={img.wallBadge}
          contentFit="cover"
          contentPosition={{ left: '50%', top: '40%' }}
          style={{ position: 'absolute', top: -20, left: 0, width, height: photoHeight }}
        />
        <LinearGradient
          colors={['rgba(11,12,15,0.3)', 'rgba(11,12,15,0.1)', 'rgba(11,12,15,0.75)', colors.carbon]}
          locations={[0, 0.3, 0.7, 0.96]}
          style={{ position: 'absolute', top: 0, left: 0, width, height: photoHeight }}
        />
        <LinearGradient
          colors={['rgba(200,16,46,0.4)', 'rgba(200,16,46,0)']}
          start={{ x: 0, y: 0 }}
          end={{ x: 0.55, y: 0.45 }}
          style={{ position: 'absolute', top: 0, left: 0, width, height: photoHeight }}
        />
        <Image source={img.glowRed} style={styles.glow} />
      </View>

      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={[styles.content, { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 24 }]}>
          <Kicker>Wombourne Allstars FC</Kicker>

          <View style={styles.bottom}>
            <View style={{ gap: 6 }}>
              <Image source={img.astro} contentFit="contain" style={styles.astro} accessibilityLabel="ASTRO" />
              <Txt style={{ color: 'rgba(255,255,255,0.86)' }}>The club app for parents, coaches and committee.</Txt>
            </View>

            <Glass radius={30} style={styles.form}>
              <TextInput
                accessibilityLabel="Email"
                placeholder="Email"
                placeholderTextColor="rgba(255,255,255,0.6)"
                autoCapitalize="none"
                autoComplete="email"
                keyboardType="email-address"
                textContentType="username"
                value={email}
                onChangeText={setEmail}
                style={styles.input}
              />
              <TextInput
                accessibilityLabel="Password"
                placeholder="Password"
                placeholderTextColor="rgba(255,255,255,0.6)"
                secureTextEntry
                autoComplete="password"
                textContentType="password"
                value={password}
                onChangeText={setPassword}
                onSubmitEditing={submit}
                returnKeyType="go"
                style={styles.input}
              />
              {error ? (
                <Txt variant="small" style={{ color: colors.alert }} accessibilityLiveRegion="polite">
                  {error}
                </Txt>
              ) : null}
              <PrimaryButton label="Sign in" onPress={submit} loading={busy} />
              <View style={styles.links}>
                <Pressable onPress={() => WebBrowser.openBrowserAsync(`${SITE_URL}/account-reset/`)} hitSlop={8}>
                  <Txt variant="link">Forgot password?</Txt>
                </Pressable>
                <Pressable onPress={() => WebBrowser.openBrowserAsync(`${SITE_URL}/account-register/`)} hitSlop={8}>
                  <Txt variant="link" style={{ color: colors.white }}>
                    Create an account
                  </Txt>
                </Pressable>
              </View>
            </Glass>
            <Txt variant="caption" style={{ textAlign: 'center' }}>
              Same email and password as the club website.
            </Txt>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.carbon },
  glow: { position: 'absolute', width: 460, height: 460, right: -220, bottom: -200 },
  content: { flexGrow: 1, paddingHorizontal: 20, justifyContent: 'space-between', gap: 24 },
  bottom: { gap: 16 },
  astro: { width: 210, height: 76 },
  form: { padding: 18, gap: 12 },
  input: {
    height: 54,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.2)',
    backgroundColor: 'rgba(0,0,0,0.32)',
    paddingHorizontal: 18,
    color: colors.white,
    fontFamily: fonts.body,
    fontSize: 16,
  },
  links: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
});
