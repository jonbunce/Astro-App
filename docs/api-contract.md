# App API contract — Release 1

Two WordPress plugins serve the app. Both follow this file; the app's TypeScript types
(`app/src/api/types.ts`) mirror the shapes below.

| Namespace | Plugin | Owns |
|---|---|---|
| `wa/v1` | `server/allstars-app` (new) | app sign-in tokens, the on/off gate, website handoff, devices and push, public club content, config |
| `was/v1` | `server/was-registration` (ASTRO) | the member's own data: profile, children, photos, inbox, account deletion |

## Switch and gate ("dark launch")

- Option `wa_app_mode`: `'off'` (default) | `'testers'` | `'on'`. Option `wa_app_testers`: array of lowercase email addresses.
- `wa_app_user_allowed( int $user_id ): bool` (allstars-app): off → nobody; testers → users whose account email is in the list; on → anyone with an account.
- When `off`, every `wa/v1` route except `GET /health` returns 403 `{ code: 'wa_app_closed', message: "The Allstars app isn't open yet." }`. When `testers`, content routes also need an allowed signed-in user; when `on`, content routes are public.
- ASTRO's `was/v1` routes require `is_user_logged_in()` and `apply_filters( 'was_rest_user_allowed', false, $user_id )`. allstars-app adds that filter (returns `wa_app_user_allowed()`), so ASTRO's API is inert unless the app plugin is active and lets the user in.

## Authentication

- The app sends `Authorization: Bearer <token>` and `X-WA-Token: <token>` (some Apache setups drop `Authorization`).
- Tokens: 32 random bytes, base64url. Only `hash('sha256', $token)` is stored. 180-day sliding expiry.
- allstars-app's `determine_current_user` filter (priority 20) only acts on REST requests whose route starts with `/wa/v1/` or `/was/v1/` (check `REQUEST_URI` for `/wp-json/wa/v1/` or `/wp-json/was/v1/`, and `rest_route=/wa/v1/…` / `/was/v1/…`). It must never authenticate anything else (wp-admin, admin-ajax, `/wp/v2`). It reads `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`, then `HTTP_X_WA_TOKEN`; a revoked/expired token or a user who is no longer allowed yields no user (route then returns 401).
- Every response from both namespaces sends `Cache-Control: no-store, private` and `nocache_headers()` so SiteGround's cache never shares one family's data with another.
- Errors are `WP_Error` with a `status`; the app shows `message`, so messages are written for parents.
- Revoke all of a user's tokens on: password reset (`after_password_reset`), password change (`profile_update` when `user_pass` changed), `was_account_deletion_requested`, and when the user stops being allowed (checked on each request).

## Hooks between the plugins

ASTRO fires these; allstars-app listens. Push text is always generic (no names, medical or money).

```php
do_action( 'was_message_delivered', int $message_id, int[] $user_ids, array $payload );
// $payload = [ 'type' => 'club_message', 'title' => string, 'body' => string /* plain text, ≤140 chars */ ]
// Fired after each batch actually sent, for recipients who have a WP account.

do_action( 'was_team_news_published', int $post_id, int $team_id, int[] $user_ids, array $payload );
// $user_ids = guardians (wp_user_id) of active players on that team, including extra-team players.
// $payload = [ 'type' => 'team_news', 'title' => "New {Team} team news", 'body' => post title, 'image' => crest URL|null ]

do_action( 'was_account_deletion_requested', int $user_id ); // app revokes tokens and devices

apply_filters( 'was_user_team_ids', int[] $ids, int $user_id ); // ASTRO: team IDs of the user's children (+ coached teams)
```

ASTRO writes inbox rows for both events itself (`was_inbox`); allstars-app queues the push.

## Endpoints

Dates are ISO 8601 strings with the site's UTC offset (`2026-10-24T16:05:00+01:00`). Money is a number of pounds (`22`, `-3`).

### `wa/v1` (allstars-app)

| Method & route | Auth | Body / query | Response |
|---|---|---|---|
| `GET /health` | none | — | `{ ok: true, version, auth_header: bool, wa_token_header: bool }` (whether those headers reached PHP) |
| `POST /auth/login` | none | `{ email, password, device?, platform?, app_version? }` | `{ token, user: { id, firstName, lastName, email } }`; 401 wrong details; 403 not allowed / app closed; 429 after 5 failures in 15 min (IP + email) |
| `POST /auth/logout` | token | — | `{ ok: true }` (revokes this token and its devices) |
| `GET /auth/sessions` | token | — | `[{ id, device, platform, createdAt, lastUsedAt, current }]` |
| `DELETE /auth/sessions/{id}` | token | — | `{ ok: true }` (own sessions only) |
| `POST /auth/handoff` | token | `{ path }` | `{ url }` — single-use, 60 s. Allowed path prefixes: `/account-portal`, `/registration`, `/registrations-unified`. The URL is `admin-ajax.php?action=wa_handoff&t=…` (never page-cached); it signs the browser in, sets a session cookie `wa_app=1` and redirects to the path. While that cookie is set the site hides its header/footer and shows a "Back to the app" button linking `wombourneallstars://done`. |
| `POST /devices` | token | `{ expoPushToken, platform }` | `{ ok: true }` (tied to the current token) |
| `DELETE /devices` | token | `{ expoPushToken }` | `{ ok: true }` |
| `GET /config` | gate | — | `AppConfig` |
| `GET /content/home` | gate | — | `{ fixtures: Fixture[], latest: NewsItem[] }` (`latest` = newest 2 items for the user's teams, else club news) |
| `GET /content/news` | gate | `?filter=all\|mine\|club\|adults&page=1` | `NewsItem[]` (≤ 20) |

### `was/v1` (ASTRO)

| Method & route | Response |
|---|---|
| `GET /me` | `Me` |
| `GET /family` | `Child[]` — the signed-in guardian's children for the current season with status pending/active |
| `GET /players/{id}/photo` | image bytes with its content type; 403 unless `was_can_view_player_photo()`; 404 if none |
| `GET /inbox?page=1` | `InboxItem[]`, newest first, ≤ 50 |
| `GET /inbox/unread-count` | `{ count }` |
| `POST /inbox/{id}/read` | `{ ok: true }` (own items only) |
| `POST /me/delete-request` | body `{ password }` → `{ ok: true }`; 403 wrong password |

## Shapes

```ts
type Role = 'parent' | 'coach' | 'committee' | 'treasurer' | 'social_media' | 'registration_officer';
interface Me { id: number; firstName: string; lastName: string; email: string; roles: Role[]; profileComplete: boolean }
interface Team { id: number; name: string; ageGroup: string; crest: string | null }            // crest = team logo URL
interface MoneyLine { label: string; amount: number }
interface Payment { date: string; amount: number; status: 'paid' | 'pending' | 'failed' }
interface Child {
  id: number; firstName: string; lastName: string; team: Team;
  participation: 'signed' | 'train_only'; status: 'active' | 'pending' | 'inactive' | 'withdrawn';
  monthly: number; breakdown: MoneyLine[]; nextPayment: string | null; lastPayment: Payment | null;
  arrears: { total: number; reason: string } | null;
  consents: { photography: boolean; legal: boolean };
  medicalOnFile: boolean; emergencyOnFile: boolean;
  hasPhoto: boolean;                                   // fetch via GET /was/v1/players/{id}/photo
}
interface Fixture { team: string; opponent: string; homeAway: 'H' | 'A'; kickoff: string; venue?: string;
  league?: string; position?: string; form?: ('W'|'D'|'L')[]; photo?: string | null }
interface NewsItem { id: number; title: string; date: string; kind: 'club' | 'team' | 'adults';
  category?: string; team?: string; image?: string | null; url: string; featured?: boolean }
interface InboxItem { id: number; type: 'club_message' | 'team_news' | 'payment' | 'welcome';
  title: string; body: string; from?: string; image?: string | null; createdAt: string; read: boolean }
interface AppConfig { minVersion: string; clubPages: InfoLink[]; helpPages: InfoLink[] }
interface InfoLink { title: string; url: string; image?: string | null }
```

The Home "needs attention" alert is worked out in the app from `GET /was/v1/family` (`arrears`), not sent by the server.

## Tables

- ASTRO: `{prefix}was_inbox` — `id` BIGINT PK, `user_id` BIGINT, `type` VARCHAR(30), `title` VARCHAR(255), `body` TEXT, `data` LONGTEXT (JSON), `ref_id` BIGINT (message or post ID), `created_at` DATETIME, `read_at` DATETIME NULL; KEY (`user_id`, `created_at`). Added via `install()`; `WAS_DB_VERSION` 2.16.0. (Use `TEXT` without a `DEFAULT` — MySQL rejects defaults on TEXT.)
- allstars-app (own version option `wa_app_db_version`): `wa_app_sessions` (id, user_id, token_hash CHAR(64) UNIQUE, device, platform, app_version, created_at, last_used_at, expires_at, revoked_at NULL), `wa_app_devices` (id, session_id, user_id, expo_push_token VARCHAR(255) UNIQUE, platform, enabled, created_at, updated_at), `wa_push_queue` (id, user_id, title, body, data LONGTEXT, status VARCHAR(20), attempts, expo_ticket_id, error, created_at, sent_at NULL).
