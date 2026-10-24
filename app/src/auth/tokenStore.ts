import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const KEY = 'wa.token';

// Keychain / Keystore on phones. The web build is only used to preview screens during development.
export const tokenStore = {
  get: () => (Platform.OS === 'web' ? AsyncStorage.getItem(KEY) : SecureStore.getItemAsync(KEY)),
  set: (value: string) =>
    Platform.OS === 'web'
      ? AsyncStorage.setItem(KEY, value)
      : SecureStore.setItemAsync(KEY, value, { keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK_THIS_DEVICE_ONLY }),
  clear: () => (Platform.OS === 'web' ? AsyncStorage.removeItem(KEY) : SecureStore.deleteItemAsync(KEY)),
};
