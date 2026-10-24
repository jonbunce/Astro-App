import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { router } from 'expo-router';
import { useEffect } from 'react';
import { Platform } from 'react-native';

import { api, isMock } from '@/api/client';
import { colors } from '@/theme';

// Push only exists on phones; the web build is a development preview.
const supported = Platform.OS === 'ios' || Platform.OS === 'android';

// Show club notifications while the app is open too.
if (supported) Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

const projectId: string | undefined =
  Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;

/**
 * Ask for permission and register this phone for club push notifications.
 * Needs a development or store build (Expo Go can't receive push) and an EAS project ID.
 */
async function register() {
  if (isMock || !Device.isDevice || !projectId) return;
  if (Constants.executionEnvironment === ExecutionEnvironment.StoreClient) return;

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('club', {
      name: 'Club messages',
      importance: Notifications.AndroidImportance.HIGH,
      lightColor: colors.red,
    });
  }

  const current = await Notifications.getPermissionsAsync();
  let granted = current.granted || current.ios?.status === Notifications.IosAuthorizationStatus.PROVISIONAL;
  if (!granted) granted = (await Notifications.requestPermissionsAsync()).granted;
  if (!granted) return;

  const { data } = await Notifications.getExpoPushTokenAsync({ projectId });
  await api.registerDevice(data, Platform.OS);
}

/** Where a tapped notification should take the user. */
function open(response: Notifications.NotificationResponse | null | undefined) {
  const type = response?.notification.request.content.data?.type;
  if (type === 'team_news') router.navigate('/news');
  else if (type) router.navigate('/inbox');
}

/** Register for push once signed in, and route notification taps. */
export function usePush(signedIn: boolean) {
  useEffect(() => {
    if (!signedIn || !supported) return;
    register().catch(() => {
      // Registration is retried on the next launch; the app works without push.
    });
    Notifications.getLastNotificationResponseAsync().then(open);
    const sub = Notifications.addNotificationResponseReceivedListener(open);
    return () => sub.remove();
  }, [signedIn]);
}
