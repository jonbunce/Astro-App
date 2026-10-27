# Data protection impact assessment: the Allstars app

**Controller:** Wombourne Allstars FC (the Club), through its committee
**Prepared by:** Jon Bunce, with Claude (AI assistant), 26 October 2026, for committee review
**Covers:** Release 1 of the Allstars app for iPhone (parents' features), the `allstars-app`
website plugin and the parts of ASTRO the app uses. Revisit before Release 2 (coaches and
committee) and before the Android launch.

Laid out along the lines of the ICO's DPIA template. Strictly, a DPIA may not be required here
(the app adds no new kinds of personal data), but it shows children's information, so the Club
should have one on file.

## 1. Why a DPIA

- The app shows information about **children**: names, teams, subs and payments, consents, and
  whether medical details are on file.
- Health information is **special category data**. The app shows only *whether* it is on file,
  never the details, but it is still near the edge of that data.
- It is a **new way of accessing** data the Club already holds, from personal phones, and adds
  push notifications through outside services.

## 2. The processing

**Nature.** A read-only view, on the member's own phone, of what ASTRO already holds about them
and their children; club messages and team news mirrored into an in-app inbox; optional push
notifications. All changes (details, consents, medical, photos, payments, Direct Debits) happen on
the existing website pages, opened from the app already signed in.

**Data used.**

| Data | Source | New? |
|---|---|---|
| Account: name, email, club roles | ASTRO / WordPress | No |
| Children: name, team, status, subs breakdown, payments, arrears, consents, "medical/emergency on file" flags | ASTRO | No |
| Club messages and team news sent to the member | ASTRO messaging | Copy kept as the inbox |
| Sign-in: device model name, iPhone/Android, app version, created and last-used times | The app | **Yes** |
| Push notification token | The phone, if the member allows notifications | **Yes** |
| Failed sign-in counts (email + IP address, 15 minutes) | The website | **Yes** (short-lived) |

Not used: medical answers, addresses, phone numbers, emergency contacts, children's photos (not
shown in Release 1), location, contacts, advertising IDs, analytics.

**Scope.** Members with a website account: in October 2026, 338 families with an account for
390 players registered this season, out of about 415 website accounts in all (including coaches
and committee). Data covers the current season. Until launch, only accounts on the tester list can use the app.

**Context.** Members already trust the Club with this data through ASTRO and see it on the
website. Parents expect to see their own children's information; nobody sees another family's.
The app is not aimed at children, and signing in needs a club account held by an adult.

**Purposes.** Give members easy access to their own membership information and club
communications, and reduce missed messages (push alongside email).

## 3. Consultation

- Committee: review this document and the club's updated privacy notice (Revision 2), which now covers the app.
- Welfare/safeguarding officer: review the children's information shown and the notification text.
- Members: told through the launch message and the updated privacy notice; testers' feedback during TestFlight.

## 4. Necessity and proportionality

- **Lawful basis:** performance of the membership contract, the same as the website (Article
  6(1)(b)). Notifications only with the phone's permission. The Article 9 condition for health data
  is the existing explicit consent given at registration; the app adds no new health processing.
- **Data minimisation:** the app shows less than the website does (no medical answers, contact
  details or photos), keeps nothing on the phone except its sign-in key, and collects only what
  sign-in management and notifications need.
- **Transparency:** new section in the Club's privacy notice, linked from both app stores.
- **Rights:** access and correction as now through the website; in-app account closure (required
  by Apple); "Signed-in devices" lets members end sign-ins themselves.
- **Processors / transfers:** website hosting (SiteGround, as now); Expo (USA) for push delivery,
  receiving only the push token and generic text; Apple and Google deliver notifications to the
  phone. No other third parties.
- **Retention:** sign-ins end after 180 days unused; ended sign-ins and notification records are
  deleted after 30 days; the inbox goes with the account.

## 5. Risks and measures

Likelihood: remote / possible / probable. Severity: minimal / significant / severe.

| # | Risk | Measures in place | Residual |
|---|---|---|---|
| 1 | A stranger's account sees another family's children (e.g. by signing up with a parent's email) | Security release 2.29.0: email confirmation before linking existing family records; registrations tied to the account that started them; app shows only the signed-in account's own children | Remote / severe → **low** |
| 2 | Lost or stolen phone shows family data | Data isn't stored on the phone; sign-in key is kept in secure storage tied to that device; phone lock applies; the member can sign the phone out from another device, and changing the password ends every app sign-in | Possible / significant → **low** |
| 3 | Notification text on a lock screen reveals something private | Notification text is always generic ("New message from the club"); details only after opening the app | Remote / minimal → **low** |
| 4 | Website caching serves one family's data to another | Every app response is marked no-store/private; checked on live (cache MISS) | Remote / severe → **low** |
| 5 | An app sign-in key is used to reach other parts of the website | Keys only work on the app's own routes; checked to be refused by the WordPress API, admin pages and admin-ajax | Remote / severe → **low** |
| 6 | Password guessing | 5 failed attempts per email in 15 minutes pauses sign-in; SiteGround security plugin as before | Possible / significant → **low** |
| 7 | Data sent abroad / to third parties | Only the push token and generic text go to Expo (USA), Apple, Google; no analytics or advertising | Remote / minimal → **low** |
| 8 | Closed accounts' data kept longer than needed | Closing the account stops all access at once and emails the registration officer and treasurer a checklist; deletion is manual | Possible / significant → **medium**: needs the action below |
| 9 | Android: website pages opened from the app share Chrome's cookies, so the member may stay signed in to the website in Chrome after signing out of the app | Not applicable to Release 1 (iPhone only) | **Must be fixed before the Android release** |
| 10 | The app is published under one person's Apple and Google accounts (the Club is unincorporated), so continuity depends on that person | Committee letter authorising publication; the Club stays data controller; code and server are the Club's | Possible / significant → **medium**: needs the action below |
| 11 | Club messages reach the wrong person's inbox | Inbox copies only go to accounts linked to the family record, or that have confirmed their email; same recipients as the existing emails | Remote / significant → **low** |

## 6. Actions

1. **Account closures:** the committee agrees who acts on closure emails, and within what time
   (suggest 30 days; the privacy notice promises deletion within 90 days of leaving).
2. **Android (before its release):** make website pages opened from the app sign out when the
   member returns to the app, or open them in an in-app web view with its own cookies.
3. **Continuity:** record in committee minutes that the app is published by Jon Bunce on the
   Club's behalf, with a plan to transfer it if the Club incorporates or Jon steps down.
4. **Privacy notice:** publish Revision 2, which covers the app, before the App Store
   submission.
5. **Review** this DPIA before Release 2, which adds coaches' squad views (more children's data
   per account, including emergency contacts).

## 7. Sign-off

| Item | Name / date | Notes |
|---|---|---|
| Measures approved by (committee) | | |
| Residual risks approved by | | |
| Safeguarding officer comments | | |
| Next review | Before Release 2 | |
