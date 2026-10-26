# App privacy answers (App Store Connect) and Google Play data safety

Answers for Release 1, checked against the code on 26 October 2026. Update them before any release
that collects something new (for example coaches' squad views in Release 2, or showing children's
photos in the app).

## Apple: App Privacy

App Store Connect → the app → App Privacy → Get Started.

**Do you or your third-party partners collect data from this app?** Yes.

The app itself sends only the sign-in details, the device details and the push token. But members
also fill in ASTRO forms on the club website *from inside the app* (registration, editing a child,
medical details, photos, Direct Debit set-up). Apple counts data entered in web pages your app opens,
so those are declared too. Declaring more than the app strictly sends is allowed; declaring less
is a common reason for rejection.

For **every** data type below, the follow-up answers are the same:

- **Used for:** App Functionality only. (Not analytics, advertising, personalisation, product personalisation or other purposes.)
- **Linked to the user's identity:** Yes.
- **Used for tracking:** No.

| Apple category | Data type | Why |
|---|---|---|
| Contact Info | Name | Account and children's names (sign-in, Family screen, ASTRO forms) |
| Contact Info | Email Address | Sign-in |
| Contact Info | Phone Number | ASTRO forms (guardian and emergency contacts) |
| Contact Info | Physical Address | ASTRO registration form |
| Contact Info | Other User Contact Info | Emergency contacts in ASTRO forms |
| Health & Fitness | Health | Medical details in ASTRO forms (the app shows only whether they're on file) |
| Financial Info | Payment Info | Subscriptions and payment history shown in Family; Direct Debit set-up on the website |
| Financial Info | Other Financial Info | Amounts outstanding (arrears) |
| User Content | Photos or Videos | Child photo upload in ASTRO forms |
| User Content | Other User Content | Consents given in ASTRO forms |
| Identifiers | User ID | The member's account ID |
| Identifiers | Device ID | Push notification token |
| Other Data | Other Data Types | Device model name, iPhone/Android, app version (signed-in devices list); children's dates of birth and gender in ASTRO forms |

Not collected (answer "No" / leave unticked): Precise and Coarse Location, Sensitive Info (the
Health type covers medical), Fitness, Credit Info, Contacts, Emails or Text Messages, Audio Data,
Gameplay Content, Customer Support, Browsing History, Search History, Purchases, Product
Interaction, Advertising Data, Other Usage Data, Crash Data, Performance Data, Other Diagnostic Data.

**Privacy Policy URL:** https://www.wombourneallstars.co.uk/privacy-policy/ (publish the app
section from `docs/privacy/privacy-notice-app-section.md` first).

**Tracking:** the app does not use the App Tracking Transparency prompt and must not; it does no
tracking.

## Google Play: Data safety (for the Android release)

Play Console → App content → Data safety. Same data types as above, mapped to Google's categories:

| Google category | Data types | Collected | Shared | Optional? | Purpose |
|---|---|---|---|---|---|
| Personal info | Name, Email address, Address, Phone number, User IDs, Other info (date of birth, gender, emergency contacts) | Yes | No | Required | App functionality, Account management |
| Financial info | Purchase history (subscription payments), Other financial info | Yes | No | Required | App functionality |
| Health and fitness | Health info | Yes | No | Required (club registration) | App functionality |
| Photos and videos | Photos | Yes | No | Optional | App functionality |
| App info and performance | none | No | | | |
| Device or other IDs | Device or other IDs (push token) | Yes | No | Optional (notifications) | App functionality |

- **Is all user data encrypted in transit?** Yes (HTTPS only).
- **Do you provide a way for users to request that their data is deleted?** Yes: in the app
  (More → Delete my account), and Google also needs a **web link** for deletion requests. Use the
  club's contact page, or add a short "Delete your account" page before the Android release.
- "Shared" is No: Expo, Apple and Google only deliver notifications on the club's behalf
  (service providers), which Google does not count as sharing.
