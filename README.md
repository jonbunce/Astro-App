# Wombourne Allstars app

The club app for parents, coaches and committee (iPhone first, then Android), plus the ASTRO platform it runs on.

| Folder | What it is |
|---|---|
| `app/` | Expo / React Native app (TypeScript, Expo Router) |
| `server/was-registration/` | ASTRO, the club's member platform (WordPress plugin, imported from the live site) |
| `server/allstars-adult-teams/` | Adult teams fixtures/tables plugin (imported from the live site) |
| `docs/` | Build plan and notes |

## The app

```bash
cd app
npm install
npx expo start
```

Scan the QR code with Expo Go on an iPhone. With no API configured the app runs on built-in sample data (`EXPO_PUBLIC_API_BASE` unset); set it in `app/.env` to point at staging or live.

## Server plugins

The plugins under `server/` are the source of truth from now on. Changes go to the SiteGround staging site first, then live (see `docs/build-plan.md`). Never edit the live files directly without updating this repo.
