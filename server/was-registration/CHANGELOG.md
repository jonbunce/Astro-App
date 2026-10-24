# ASTRO changelog

## 2.30.0 — groundwork for the Allstars app

Nothing changes on the website. This release adds the parts of ASTRO that the new Allstars app will use. They stay switched off until the app is opened (that is controlled by the separate Allstars app plugin), and even then only work for the accounts the app lets in.

- **Your own family's details in the app:** your profile, your children for this season (team, monthly amount and what it is made up of, next and last payment, anything outstanding, consents, and whether medical and emergency details are on file), and your children's photos, which are shown to exactly the same people who can see them on the website. Medical answers, addresses and phone numbers are never sent to the app.
- **An inbox in the app.** When a club message goes out or team news is published, members who use the app also find it in their app inbox. Emails go out exactly as before. (Database update 2.16.0 adds the inbox table.)
- **Asking for your account to be deleted, from the app.** After re-entering their password, a member's account is closed straight away: it can't be used to sign in to the website or the app, and it is signed out everywhere. The registration officer, treasurer and club admin are emailed a checklist (cancel any Direct Debit, keep what FA and safeguarding rules require, then remove the rest). Nothing is deleted automatically.
- **Tidier database updates:** a few table definitions were corrected so database updates no longer log harmless errors. No data changes.

## 2.29.0 — security fixes

A security release. Nothing changes for families who already have an account linked to their children; the main thing people may notice is the new email confirmation step.

- **Email confirmation before linking existing family records.** Signing up with an email address the club already has on file no longer gives the new account that family's children straight away. ASTRO emails a link to that address first (valid for 48 hours). Once it's clicked, the account is linked and the details the club already holds are kept. Resetting a password through the emailed link also counts as confirming the address. Brand-new families sign up as before; their welcome email now has a "Confirm my email address" button too. Older accounts (for example shop accounts) with a matching record see a "please confirm your email" notice in the portal and are sent the link automatically.
- **Registrations can only be finished by the person who started them.** Each part-finished registration now records which account started it, and the "pick up the most recent unfinished registration" shortcut that could hand one family another family's Direct Debit has gone. (Database update 2.15.0 adds the new column.)
- **The child is always registered to your own family record**, never to one found by the email address on the form.
- **The sibling check only looks at your own family**, and the registration actions no longer respond to visitors who aren't signed in.
- **Children's photos are only shown to people who need them:** admins, committee, social media and registration officers; coaches for children on their own teams; and parents for their own children. Treasurer-only accounts no longer see photos.
- **Safer image uploads.** Child photos, team logos and team news photos must be genuine JPG, PNG or WebP images (SVG and GIF are no longer accepted). Every image is turned the right way up, scaled to at most 2000 pixels and re-saved, which removes hidden data such as the GPS location of the phone that took it. Size limits are unchanged.
- **GoCardless webhooks are refused unless the webhook secret is set** in ASTRO → Settings, so nobody can send fake payment updates.
- **Removed two GoCardless debugging tools** from the admin area, and the "Run sync now" button now has the usual security check.
- **Sign-in:** after signing in you are only ever sent to a page on this website; after 5 wrong passwords for the same email in 15 minutes, sign-in for that email pauses for 15 minutes; and "Sign out" can no longer be triggered by another website.
- **Portal fixes:** "add another team" requests must be for one of your own children, a child's first and last name can't be saved blank, and the photo size limit in the browser now matches the server (8MB).
- **Less logging:** removed debug lines that wrote account and registration IDs to the server error log on every registration.
