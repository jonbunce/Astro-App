import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, TextInput } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api } from '@/api/client';
import { useAuth } from '@/auth/AuthContext';
import { GlassButton, PrimaryButton } from '@/components/Buttons';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { Title, Txt } from '@/components/Txt';
import { colors, fonts } from '@/theme';

// Apple requires in-app account deletion. ASTRO disables the login straight away and asks the
// registration officer and treasurer to cancel any Direct Debit and handle record keeping.
export default function DeleteAccount() {
  const insets = useSafeAreaInsets();
  const { signOut } = useAuth();
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  async function confirm() {
    setBusy(true);
    try {
      await api.requestDeletion(password);
      Alert.alert(
        'Account deletion requested',
        "You've been signed out. The club will cancel any Direct Debits and confirm by email.",
      );
      await signOut();
    } catch (e) {
      setBusy(false);
      Alert.alert('Not deleted', e instanceof Error ? e.message : 'Please try again.');
    }
  }

  return (
    <KeyboardAvoidingView style={styles.root} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={[styles.content, { paddingTop: insets.top + 24, paddingBottom: insets.bottom + 24 }]}>
        <Kicker>Your account</Kicker>
        <Title size={44}>Delete my account</Title>
        <Glass style={styles.card}>
          <Txt>This will:</Txt>
          <Txt variant="small">• sign you out and turn off your ASTRO login straight away</Txt>
          <Txt variant="small">• ask the club to cancel any Direct Debit for your children</Txt>
          <Txt variant="small">
            • remove your details, apart from records the club must keep for safeguarding and the FA
          </Txt>
        </Glass>
        <TextInput
          accessibilityLabel="Your password"
          placeholder="Enter your password to confirm"
          placeholderTextColor="rgba(255,255,255,0.6)"
          secureTextEntry
          value={password}
          onChangeText={setPassword}
          style={styles.input}
        />
        <PrimaryButton label="Delete my account" onPress={confirm} loading={busy} disabled={!password} />
        <GlassButton label="Keep my account" onPress={() => router.back()} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.carbon },
  content: { paddingHorizontal: 16, gap: 16 },
  card: { padding: 16, gap: 8 },
  input: {
    height: 54,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.2)',
    backgroundColor: 'rgba(255,255,255,0.06)',
    paddingHorizontal: 18,
    color: colors.white,
    fontFamily: fonts.body,
    fontSize: 16,
  },
});
