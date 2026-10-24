# Wombourne Allstars app + ASTRO upgrade: build plan

## Context
The club wants its own app for **parents, coaches and committee**, iPhone first and then Android. Requirements:
- **Every ASTRO feature** in the app.
- **The news section** and the **club information pages**.
- **Push notifications alongside email**, plus an in-app inbox.
- **Ship as soon as possible**: a small first release, then regular updates.

Today ASTRO (the `was-registration` plugin, v2.28.0, on the live WordPress site) has **no API an app can use**:
- Its only REST route is the GoCardless webhook.
- Everything else is admin-ajax handlers and shortcode pages tied to the website login cookie and one shared `was_nonce`.
- Messaging is email only, and the plugin fires no hooks.

The code survey also found live security holes, including one that lets an account take over another family's data. It also found logic buried in AJAX handlers, a fragile season rollover, and a missing Apple requirement (in-app account deletion). So the project is **ASTRO v3 (fixes, API and upgrades) plus a new Expo app**, delivered in releases.

**Decisions made with the user:**
- **Build:** Expo/React Native in TypeScript, with cloud builds (EAS) from this Windows PC.
- **Accounts:** an individual Apple account in Jon's name (the club is unincorporated). Google Play individual account later.
- **Messaging:** push and email together, plus an inbox.
- **Timeline:** as soon as possible.
- **Testing and code:** a SiteGround staging copy for testing; code in a private GitHub repo.

Costs: Apple $99/yr, Google $25 once. Expo builds and push are free at our volume.

**Strategy for "as soon as possible": read in the app, edit on the website for 1.0.**
- Release 1 adds read-only endpoints over the existing ASTRO getters.
- Anything that changes data opens the existing, proven website flow inside the app, already signed in (the "handoff" below).
- So the live registration and GoCardless code is barely touched before launch, apart from security fixes.
- The bigger refactor and native editing come in Release 2.

**App Store risk to manage:** guideline 5.1.1(ix) says apps that need sensitive data (children's medical details, Direct Debit) "should be submitted by a legal entity".
- **Mitigation for 1.0:**
  - Medical and payment changes stay on the website.
  - No 100 Club (a lottery, guideline 5.3.4).
  - UK-only release.
  - A signed committee letter authorising use of the club name and badge (5.2.1).
  - Clear review notes.
- **Contingency if Apple rejects on these grounds:** incorporate the club, e.g. as a CIO or company limited by guarantee. Then enrol as an organisation with a D-U-N-S number and transfer the app. None of the code is wasted.

---

## R0: foundations and security (start now)

**Jon's actions (the Apple account is on the critical path):** Expo Go can't receive push, deep links or handoff returns, so all of that needs a development build, and a development build needs the Apple account.
1. Enrol in the Apple Developer Program as an individual.
2. Create a free Expo account.
3. Create the SiteGround staging copy (Site Tools → WordPress → Staging).
4. Authorise GitHub (`/mcp` → github) for the private repo.
5. Send a 1024px club badge PNG. The app's colours reuse the site's tokens from `allstars-frontpage/assets/front.css`: red `#C8102E`/`#a00d24`, gold `#E39A00`, ink `#14161a`, paper `#f6f7f9`.
6. Get a short committee letter authorising the app's use of the club name and badge.
7. Pick 5–10 TestFlight testers.

**Code home:** a git repo **outside OneDrive** at `C:\dev\allstars-app`, added to this session, with the private GitHub repo as its remote.
```
app/                      Expo app
server/was-registration/  pulled from live (ASTRO)
server/allstars-app/      new plugin: app auth, devices, push, content API
server/allstars-adult-teams/  (small public wrapper for view_model)
scripts/deploy.sh         staging|live: php -l, server backup as <file>.bak-YYYYMMDD,
                          scp, `wp sg purge`; a SiteGround backup first if the database changes
scripts/api-smoke.sh      per-role access tests (see Verification)
```

**Staging safety. Staging is a full clone of live, with live GoCardless keys, cron jobs and real families' data.** Before using it:
- Blank the GoCardless and Facebook credentials, or switch them to sandbox.
- Set `DISABLE_WP_CRON`.
- Add a staging-only must-use plugin that sends every email to one test inbox and only sends push to allow-listed test users.
- Keep it password-protected and `noindex`.

**Spike (half a day), done on staging.** A throwaway route proves three things:
- (a) The Bearer token reaches PHP, checking both `HTTP_AUTHORIZATION` and `REDIRECT_HTTP_AUTHORIZATION`, with an `X-WA-Token` header as a fallback.
- (b) SiteGround's cache never serves one user's response to another. Check the `X-Proxy-Cache` header and exclude `/wp-json/wa/*`, `/wp-json/was/*` and the handoff URL.
- (c) The sgcaptcha bot challenge doesn't return HTML to the app.

**Security fixes (ship to the live website in R0, tested on staging first):**

| # | Fix |
|---|---|
| **S12** | **Account takeover:** `WAS_Accounts::ajax_register` (class-accounts.php:302–335) and `current_guardian()` (441–453) link any existing guardian row with the same email, with no email verification. Someone registering with a legacy parent's email inherits their children and medical data. **Fix:** send an email verification link before linking or updating an existing guardian. |
| S1 | `class-registration.php` `ajax_submit_form` (about 410–436) falls back to the newest pending row with `primary_guardian_id = 0`. **Fix:** add a `created_by_user` column set in `create_pending_player`, and require a matching token plus that owner. |
| S2 | Same handler (about 516–555) finds the guardian by the email typed into the form. **Fix:** use `current_guardian()` for signed-in users. |
| S3 | `ajax_check_sibling` works logged out and reveals children's names. **Fix:** require login and check only the current guardian. |
| S4 | **Fix:** remove the logged-out handlers for `ajax_start_gc` and `ajax_submit_form`. |
| S5 | `was_serve_player_photo_ajax` lets any coach or officer see any child. **Fix:** coaches see only their own teams; officers only where their role needs it. |
| S6 | **Fix:** block SVG team logos (`class-portal.php` about 1137). Validate every upload with `wp_check_filetype_and_ext` plus `getimagesize`, and strip EXIF data including GPS. |
| S7 | **Fix:** the GoCardless webhook rejects requests when no secret is set. |
| S8 | **Fix:** delete the debug handlers `WAS_Admin::ajax_gc_debug` and `ajax_gc_test_payments`. Add a nonce to `ajax_run_sync`. |
| S9 | **Fix:** use `wp_validate_redirect` on `redirect_to` and add a logout nonce. Check that sg-security's login limiter covers both the AJAX and API logins, and add a throttle only if it doesn't. Don't fire `wp_login` manually, because sg-security's 2FA reacts to it. |
| S10 | **Fix:** `ajax_request_second_team` checks the child belongs to the parent. `ajax_update_child` requires the name fields. Upload limits match (8MB). |
| S11 | **Fix:** remove the `[WAS-SUBMIT]` debug logging. |

---

## R1 "Parents" → TestFlight, then App Store 1.0

### Server: new plugin `allstars-app` (namespace `wa/v1`)
- **Tables:**
  - `wa_app_sessions`: user_id, token_hash (sha256 of a random 32-byte token), device_name, platform, app_version, created, last_used, expires, revoked.
  - `wa_app_devices`: session_id, user_id, expo_push_token, prefs, enabled.
  - `wa_push_queue`.
- **Auth:**
  - Endpoints: `POST /auth/login` (`wp_authenticate`), `POST /auth/logout`, and `GET`/`DELETE /auth/sessions`.
  - A `determine_current_user` filter accepts the token **only on `/wa/v1/` and `/was/v1/` requests**, so it can never unlock `/wp/v2`, admin-ajax or wp-admin.
  - Tokens expire after 180 days of no use.
  - All of a user's tokens are revoked on password reset or change (`after_password_reset`, `profile_update`), role removal, or a deletion request.
  - Every authenticated response sends `nocache_headers()`.
- **Handoff to the website:**
  - `POST /auth/handoff {path}` returns a single-use token valid for 60 seconds. `GET /app-handoff?t=` sets the WP auth cookie and redirects safely to the path.
  - The app opens it with `WebBrowser.openAuthSessionAsync(..., { preferEphemeralSession: true })`, so no web session survives after app sign-out.
  - The handoff URL adds `app=1` and the page adds a body class that hides the Flatsome header and footer (signed-in pages already bypass the cache).
  - At the end of each flow (`WAS_Registration::handle_gc_return`, `complete_rejoin`, registration complete, profile saved), the page redirects to `wombourneallstars://done`, which closes the session.
  - **It covers:** new registration, rejoin, Direct Debit set-up, "Pay £X now", editing a child, consents, medical, photo, profile and password reset.
- **Push:**
  - The sender is a function `wa_queue_push($user_ids, $title, $body, $data)`.
  - A cron job sends batches of 100 to the Expo Push API using an access token held as a wp-config constant.
  - It checks delivery receipts and switches off `DeviceNotRegistered` tokens.
  - A real SiteGround cron job runs `wp cron event run --due-now` every 5 minutes.
  - **Push text stays generic.** For example "New message from Wombourne Allstars" or "New U9 Tigers team news". Never names, medical or payment details. The full content loads inside the app after sign-in.
- **Public content (cached in transients):**
  - `GET /content/home`: latest news and the adult teams' next game and last result.
    - This comes from a new public wrapper in `allstars-adult-teams` around the private `view_model($slot, $data, $limit, $now)`.
    - The wrapper returns only a whitelisted set of fields.
  - `GET /content/news?team=`: club posts plus approved team news (`_was_news_team_id`), with `link` and featured image.
  - `GET /config`:
    - minimum app version, so old versions can be forced to update;
    - info pages as an ordered list of page IDs, chosen on a small settings screen: Our Story, Meet the Committee, Safeguarding, Document Library, Sponsors Directory, Contact Us, Privacy Policy and so on;
    - support links.
  - Unpublish the stray "Sample Post" (id 3664).

### Server: ASTRO (`was-registration`, R1 changes only, kept small)
- **New `includes/class-rest.php`, namespace `was/v1`, read-only plus three small writes.** It calls the existing getters, and a small new `WAS_Permissions` class with `can_view_player` and `coach_team_ids` checks access.
  - `GET /me`: user, roles, guardian, profile complete, capability flags.
  - `GET /family`: children, team(s), status, breakdown from `was_player_payment_breakdown`, payments from `WAS_Database::get_player_payments`, `WAS_Arrears::summary`, consents, and whether medical details are on file. It does **not** send the medical answers.
  - `GET /players/{id}/photo`: an authenticated image stream.
  - Inbox: `GET /inbox`, `GET /inbox/unread-count`, `POST /inbox/{id}/read`.
  - `POST /me/delete-request`, which needs the password:
    1. Disables login.
    2. Revokes tokens.
    3. Emails the registration officer and treasurer to cancel the mandate and handle FA and safeguarding retention.
    4. Logs the request.
    The anonymisation tool waits until R3.
- **`was_inbox` table**, added through the existing `install()` with a `WAS_DB_VERSION` bump: id, user_id, type, title, body, data, message_id, created_at, read_at.
- **Hooks**, so the app plugin stays separate from ASTRO:
  - `do_action( 'was_message_delivered', $message, $recipients )` inside `WAS_Notifications::msg_process`. The handler writes inbox rows and queues the push. `msg_recipients` is extended to return user IDs through `was_guardians.wp_user_id`, and it must still accept the plain email lists stored by messages that are mid-send when the change goes live.
  - `do_action( 'was_team_news_published', $post_id, $team_id )` in `WAS_Team_News::ajax_approve`. It pushes to that team's parents and adds an inbox item.

### App (`app/`, Expo with TypeScript strict, Expo Router, `ios.supportsTablet: false`, bundle ID `uk.co.wombourneallstars.app`)
- **Tabs:**
  - **Home:** news, adult fixtures and quick links.
  - **News:** a native list. Each article opens in a WebView with injected CSS that hides the site's chrome. No HTML-rendering library is used, because `react-native-render-html` has been abandoned.
  - **My Family:** a card per child with team, status, payments, arrears and consents. "Update on website" buttons use the handoff.
  - **Inbox.**
  - **More:** info pages in a WebView with injected CSS, notification settings, signed-in devices, delete account and sign out.
- **Libraries:**
  - `@tanstack/react-query`, with persistence for public content only.
  - `expo-secure-store`, `expo-notifications`, `expo-image`.
  - `react-native-webview`, `expo-web-browser` and `expo-linking` (scheme `wombourneallstars`).
- **Children's data on the phone:**
  - Family data is never written to disk.
  - Child photos stay in memory only.
  - Everything is wiped on sign-out or a 401.
  - The Keychain token is cleared on first launch after a reinstall, using a flag in AsyncStorage.
- **Code layout:** `src/api/client.ts` (adds the token, signs out on 401, typed endpoints), `src/auth/`, `src/features/{home,news,family,inbox,info,settings}`, `src/theme/`.
- **Development and release:**
  - Build screens in Expo Go until the Apple account exists, then switch to an EAS **development** build for push, deep links and handoff.
  - TestFlight builds are store-signed `production`-profile builds sent with `eas submit`. External testers join through a public TestFlight link.
  - EAS Update ships bug fixes over the air.
- **App Store 1.0 checklist:**
  - A demo parent account for Apple's reviewers: one test child in a hidden test team, payment type exempt. GoCardless flows are blocked for it, and it's left out of messages and exports.
  - Privacy labels: contact info, health (whether medical details are on file), photos, financial info, user ID and device ID. No tracking.
  - Privacy policy updated to cover the app and push tokens.
  - A short data-protection impact assessment.
  - Review notes explaining the club context.
  - UK only.
- **Android:** start the Google Play closed test at the same time (Firebase/FCM v1 credentials uploaded to EAS for push). The 12 testers must stay opted in for 14 days.

---

## R2 "Coaches & committee" → iOS 1.1 and Android public launch
- **ASTRO refactor (behaviour unchanged on the web):**
  - `WAS_Permissions` grows to replace about 7 duplicated role checks: `WAS_Portal::render`, `is_reviewer`, `treasurer_gate`, `msg_gate`, `WAS_Interest::can_manage`, photo serving, and the inline `in_array` calls.
  - Service classes in `includes/services/` are pulled out of the AJAX handlers, taking arrays and returning `array|WP_Error`:
    - `Family_Service`: update child, consent, photo and profile.
    - `Guardian_Service::upsert`: replaces three copies.
    - `News_Service` and `Message_Service`.
  - The AJAX handlers become thin wrappers.
  - `WAS_Dispatcher` becomes the single route for every email (today's direct `wp_mail` calls), adding inbox rows and push.
  - Versioned database migrations.
  - When replacing the inline amount calculation in `ajax_submit_form`, keep its sibling-rate behaviour: `was_player_payment_breakdown` reads `payment_type` before it's updated, around line 640.
- **Native editing in the app:**
  - Child details, consents, photo (crop and resize with `expo-image-picker` and `expo-image-manipulator`) and profile.
  - "Pay £X now" as a native POST that calls `WAS_Arrears::create_catchup`, behind a confirm step.
- **Coaches:**
  - My Teams and the squad: badges, tap-to-call emergency contacts, photos for their own teams only. One query instead of one per player.
  - Team profile and logo, which the committee is now allowed to save as well.
  - Submit team news with the camera, and edit or withdraw a pending post or resubmit a rejected one.
  - Message their own team's parents. Messages are logged and visible to the committee.
- **Reviewers:**
  - News review queue: approve, reject, edit and Facebook push.
  - Facebook push moves to a queued cron job with retry.
  - The committee is added to reviewer emails.
- **Committee:**
  - Compose and schedule messages, with targeting by team, age group or role.
  - "Send now" goes through `msg_process` so it is logged and resumable.
- **Routes:**
  - Teams: `GET /teams/mine`, `GET /teams/{id}/squad`, `PUT /teams/{id}/profile`, `POST /teams/{id}/logo`.
  - Coach news: `/news/mine`, `PATCH`/`DELETE /news/{id}`.
  - Review: `/news/review` and `POST /news/{id}/{approve|reject|fb-push|fb-mark}`.
  - Messages: `GET`/`POST /messages`, `/messages/preview-recipients`.
  - Family writes: `PATCH /players/{id}`, `PUT /players/{id}/consents`, `POST /players/{id}/photo`, `PUT /me/profile`, `POST /players/{id}/pay-arrears`.

## R3 "Officers" → app 1.2
- **Treasurer:**
  - Summary, arrears, failed payments, reminders, collect arrears and clear hold. These actions need a password re-check.
  - "Sync all mandates" moves to a background job.
- **Registration officer:** the club register. **Interest list:** view, manage and share a CSV. **Social media:** the restrictions list.
- **Season rollover fixes:**
  - Deactivate the old season's teams.
  - Carry coach assignments over.
  - Copy training_info, coach_names, coach_contact, honours and sponsor_line.
  - Wrap it in a transaction.
  - No hardcoded '2024-25' default in `was_season()`.
- **Deferred polish:**
  - Parent Messages tab in the web portal.
  - Medical edits alert the coaches.
  - Consents saved with the Save button rather than on each click.
  - Second-team requests stored with a status.
  - The account anonymisation tool.

## Backlog (prioritise with the committee)
- Second guardian per child: a `was_player_guardians` link table so both parents get messages.
- Structured training schedule and calendar.
- Match/training availability and attendance.
- Youth fixtures.
- Role-gated pitch schedule for coaches.
- Letting parents withdraw medical-data consent. It's required at registration (`validate_post`), so it needs a committee policy first.

---

## Verification
- **Every deploy:** `php -l` on changed files. Deploy to staging, run `scripts/api-smoke.sh`, then deploy to live and run the smoke tests again.
- **`api-smoke.sh`** signs in as test parent A, parent B, the coach of team X, committee and treasurer, and checks the expected 200/401/403 for every route. That includes:
  - parent A can't read parent B's child or photo;
  - a coach can't fetch another team's photos;
  - an app token is rejected by `/wp/v2/users/me` and admin-ajax;
  - two users calling `/me` back to back get their own data (no shared cache).
- **Security fixes on staging:**
  - S12: registering with an existing guardian's email doesn't link until the verification link is clicked.
  - S1/S2: a second test parent can't pick up another family's pending registration or overwrite their guardian record.
  - S3: logged out, the sibling check returns nothing.
  - S5: the coach photo restriction works.
- **Website regression (R0 and R2):** walk the portal as each role. Sign in, edit a child, change a consent, upload a photo, submit and approve news, send a test message to yourself, and complete a sandbox GoCardless registration. Confirm the emails still arrive as before.
- **App:**
  - `npx tsc --noEmit`, ESLint, and Jest for the API client and auth/session wipe.
  - On the iPhone dev build: sign in, Family, handoff edit, return to the app, sign out. After sign-out, check that the website no longer shows you as signed in.
- **End-to-end push:** schedule a club message on staging. The email arrives in the test inbox, a push arrives on the iPhone (allow-listed), the inbox item appears, and tapping the push opens it. Then approve a team news post and check the team's test parent gets a push.
- **Before submitting to the App Store:** demo account and isolation, privacy labels, account deletion working end to end, forced update via `/config`, UK-only availability.
