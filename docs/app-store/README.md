# App Store submission: checklist and listing

Everything needed to put Release 1 on TestFlight and then the App Store. Related files:
`app-privacy.md` (privacy answers), `review-notes.md` (notes for Apple), `../privacy/` (privacy
notice section and DPIA).

## Before the first build

- [ ] Apple Developer Program, individual, in Jon's name (developer.apple.com/programs/enroll).
- [x] Expo project: `@jonbunce/wombourne-allstars` (ID in `app/app.json`), build profiles in `app/eas.json`.
- [ ] 1024px club badge → `python scripts/make-icons.py badge.png` (icons are currently made from the 516px badge on the website).
- [ ] First development build for Jon's iPhone: `cd app`, then `npx eas-cli build --profile development --platform ios`.
      EAS asks to sign in to the Apple account and sets up certificates, the push notification key and
      device registration itself.

## TestFlight

- [ ] Create the app in App Store Connect: name "Wombourne Allstars", bundle ID `uk.co.wombourneallstars.app`, SKU `wombourne-allstars`, primary language English (UK).
- [ ] `npx eas-cli build --profile production --platform ios`, then `npx eas-cli submit --platform ios --latest`.
- [ ] TestFlight → external group "Club testers": add the 5–10 testers; the first external build needs a short Beta App Review (same demo account and notes).
- [ ] Add the testers' emails to Settings → Allstars App → Tester emails on the website (the app is in testers-only mode).

## App Store

- [x] Privacy notice Revision 2 live on the website (published 27 October 2026; Revision 1 backed up in `~/deploy-backups/` on the server) (`docs/privacy/privacy-notice.md`; `privacy-notice.wp.html` is the same text as WordPress blocks).
- [ ] DPIA signed off by the committee (`docs/privacy/dpia.md`).
- [ ] Demo account for Apple created on the website (see the build plan).
- [ ] Committee letter scanned for the review attachment.
- [ ] App Privacy answers entered (`app-privacy.md`).
- [ ] Screenshots: 6.9" iPhone (1320 × 2868), at least 3, taken from the development build.
- [ ] Availability: United Kingdom only. Price: free.
- [ ] Switch the website to Settings → Allstars App → **On** when the app is released.
- [ ] Once listed, paste the App Store link into Settings → Allstars App → App Store link (used by "Time to update").

## Listing text

**Name** (30): Wombourne Allstars
**Subtitle** (30): Club news, messages and ASTRO
**Category:** Sports. Secondary: none.
**Age rating:** answer No / None to every question → 4+. (No user-generated content, no chat
between users, no advertising, no gambling or contests, no medical content, no unrestricted web
browsing: the app only opens the club's own website pages.)
**Copyright:** 2026 Wombourne Allstars FC
**Support URL:** https://www.wombourneallstars.co.uk/contact-us/
**Marketing URL:** https://www.wombourneallstars.co.uk/
**Privacy Policy URL:** https://www.wombourneallstars.co.uk/privacy-policy/

**Promotional text** (170):
Club messages straight to your phone, your family's teams and monthly subscriptions at a glance, and ASTRO, the club's registration system, whenever you need it.

**Keywords** (100):
wombourne,allstars,football,grassroots,junior,youth,club,astro,staffordshire,fixtures,team news

**Description:**

The official app for members of Wombourne Allstars FC, the grassroots football club for Wombourne
and the surrounding villages.

Sign in with your ASTRO account (the same email and password as the club website) to keep up with
everything in one place.

YOUR FAMILY
See each of your children's teams and registration status, their monthly subscriptions and what
they're made up of, recent and upcoming Direct Debit payments, and their consents. When something
needs updating, the app opens the right page of ASTRO, already signed in.

CLUB MESSAGES
Messages from the club arrive in your inbox, with an optional notification, as
well as by email as before. Team news for your children's teams lands there too.

MATCHDAY AND NEWS
Fixtures and results for the first teams, and all the latest club and team news.

THE CLUB
Our story, the committee, safeguarding, documents and policies, sponsors, and how to get in touch.

The app is for members of Wombourne Allstars FC. To join the club, visit wombourneallstars.co.uk.

**What's New** (for 1.0): First release of the Wombourne Allstars app.
