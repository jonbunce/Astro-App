import { Inter_400Regular, Inter_500Medium, Inter_600SemiBold, Inter_700Bold } from '@expo-google-fonts/inter';
import { QueryClientProvider } from '@tanstack/react-query';
import { useFonts } from 'expo-font';
import { DarkTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';

import { useConfig } from '@/api/hooks';
import { queryClient } from '@/api/queryClient';
import { AuthProvider, useAuth } from '@/auth/AuthContext';
import { UpdateRequired } from '@/components/UpdateRequired';
import { usePush } from '@/lib/push';
import { needsUpdate } from '@/lib/version';
import { colors, fonts } from '@/theme';

SplashScreen.preventAutoHideAsync();

const theme = {
  ...DarkTheme,
  colors: { ...DarkTheme.colors, background: colors.carbon, card: colors.carbon800, primary: colors.red },
};

export default function RootLayout() {
  const [fontsLoaded] = useFonts({
    [fonts.display]: require('@/assets/fonts/ArchivoCondensedBlackItalic.ttf'),
    [fonts.displaySemi]: require('@/assets/fonts/ArchivoSemiCondensedExtraBoldItalic.ttf'),
    [fonts.black]: require('@/assets/fonts/ArchivoBlackItalic.ttf'),
    [fonts.heavy]: require('@/assets/fonts/ArchivoExtraBold.ttf'),
    Inter_400Regular,
    Inter_500Medium,
    Inter_600SemiBold,
    Inter_700Bold,
  });

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ThemeProvider value={theme}>
          <StatusBar style="light" />
          <RootStack fontsLoaded={fontsLoaded} />
        </ThemeProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

function RootStack({ fontsLoaded }: { fontsLoaded: boolean }) {
  const { status } = useAuth();
  // Checked signed in or out; if the config can't be loaded the app carries on as normal.
  const config = useConfig();
  const ready = fontsLoaded && status !== 'loading';
  usePush(status === 'signedIn');

  useEffect(() => {
    if (ready) SplashScreen.hideAsync();
  }, [ready]);

  if (!ready) return null;
  if (config.data && needsUpdate(config.data.minVersion)) return <UpdateRequired config={config.data} />;

  const signedIn = status === 'signedIn';
  return (
    <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.carbon } }}>
      <Stack.Protected guard={signedIn}>
        <Stack.Screen name="(tabs)" />
        <Stack.Screen
          name="web"
          options={{
            headerShown: true,
            headerTintColor: colors.white,
            headerStyle: { backgroundColor: colors.carbon800 },
            headerTitleStyle: { fontFamily: fonts.bold },
            headerBackButtonDisplayMode: 'minimal',
          }}
        />
        <Stack.Screen name="page/[slug]" />
        <Stack.Screen name="delete-account" options={{ presentation: 'modal' }} />
      </Stack.Protected>
      <Stack.Protected guard={!signedIn}>
        <Stack.Screen name="sign-in" />
      </Stack.Protected>
    </Stack>
  );
}
