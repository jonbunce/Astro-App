# Allstars app (Expo)

```bash
npm install
npx expo start
```

- Runs on built-in sample data until `EXPO_PUBLIC_API_BASE` is set (copy `.env.example` to `.env`).
- Liquid Glass (iOS 26) via `expo-glass-effect`, with a blur fallback on older iOS and a tinted panel on Android.
- Push notifications, deep links and the website handoff need a development build (`npx eas-cli@latest build --profile development`), not Expo Go.
- Fonts: Archivo static instances generated from the Google Fonts variable font (SIL OFL, see `assets/fonts/Archivo-OFL.txt`), Inter via `@expo-google-fonts/inter`.
