# Allstars App (WordPress plugin)

Server side of the Wombourne Allstars mobile app. REST namespace `wa/v1`; the binding contract is
[`docs/api-contract.md`](../../docs/api-contract.md).

What it does:

- **App sign-in.** `POST /wa/v1/auth/login` swaps email and password for a random token (only its
  sha256 is stored). The token is accepted only on `/wa/v1/` and `/was/v1/` REST requests, never on
  wp-admin, admin-ajax or `/wp/v2`. Sign-ins last 180 days from last use. They are revoked on a
  password reset or change, an ASTRO account-deletion request, and when the person is no longer
  allowed to use the app. Five wrong passwords for one email from one IP address lock that pair for
  15 minutes.
- **Website handoff.** `POST /wa/v1/auth/handoff { path }` returns a single-use, 60-second link that
  signs the browser in and opens an ASTRO page (`/account-portal`, `/registration`,
  `/registrations-unified`). While the `wa_app=1` cookie is set on a signed-in page, the Flatsome
  header and footer are hidden and a "Back to the app" button is shown. Logged-out pages are never
  changed.
- **Push notifications.** Phones register Expo push tokens (`POST`/`DELETE /wa/v1/devices`). When
  ASTRO fires `was_message_delivered` or `was_team_news_published`, a push is queued for each signed-in
  phone of each allowed recipient. The text is always generic: "New message from the club" or "New
  team news for U9 Tigers", never names, medical or money details. A WP-Cron job runs every minute,
  sends batches of 100 to Expo, retries a failed send up to 3 times, checks receipts and switches off
  phones Expo reports as `DeviceNotRegistered`. Queue rows are kept for 30 days. Other code can queue
  a push with `wa_queue_push( $user_ids, $title, $body, $data )`.
- **Club content.** `GET /wa/v1/config` (minimum app version, store links, info pages), `/content/news`
  (`?filter=all|mine|club|adults&page=1`) and `/content/home` (each adult team's next game from the
  Allstars Adult Teams data, plus the two newest news items).
- **Club pages as text.** `GET /wa/v1/content/pages/{slug}` sends one of the More tab's pages
  (privacy policy, safeguarding, our story and so on) as plain blocks the app shows in its own style:
  headings, paragraphs, lists, tables, images, links, a document list and committee cards. The text
  comes straight from the page, so editing it in WordPress changes the website and the app together
  (each page is cached for an hour, and the copy is dropped as soon as the page is saved). Pages that
  are mostly a form or a plugin (Contact us, the sponsors directory) are marked `native: false` in the
  config and still open as web pages.
- **ASTRO gate.** It adds the `was_rest_user_allowed` filter, so ASTRO's `was/v1` API only answers
  people this plugin lets in.
- Every `wa/v1` and `was/v1` response is sent with `Cache-Control: no-store, private`, so
  SiteGround's cache never shares one family's data with another.

## Modes

Set the mode under **Settings → Allstars App** (option `wa_app_mode`):

| Mode | What happens |
|---|---|
| `off` (default) | Everything is inert. Every `wa/v1` route except `GET /wa/v1/health` returns 403 "The Allstars app isn't open yet.". Tokens are ignored, nothing is queued or sent, the push cron isn't scheduled, and ASTRO's `was/v1` API refuses everyone. |
| `testers` | Only accounts whose email is on the tester list can sign in, read content and get pushes. |
| `on` | Anyone with a website account can sign in. News, fixtures and config are public. |

Switching to `off` blocks the app straight away but keeps existing sign-ins. To end them as well, use
**Revoke all app sessions** on the same screen. Taking someone off the tester list ends their app
sign-ins the next time their app calls the server.

## Adding testers

Settings → Allstars App → **Tester emails**: one email address per line. Use the email on the
person's website account. Choose **Testers only** and save.

With WP-CLI:

```bash
wp option update wa_app_testers '["parent@example.com","coach@example.com"]' --format=json
wp option update wa_app_mode testers
```

## Expo access token (optional)

If "enhanced push security" is turned on for the project in Expo, add the access token to
`wp-config.php` above "That's all, stop editing!":

```php
define( 'WA_EXPO_ACCESS_TOKEN', 'your-expo-access-token' );
```

It is sent as `Authorization: Bearer …` to Expo's push and receipts endpoints. The settings page
shows only whether it is set.

## Cron

The sender uses a custom `wa_every_minute` schedule (hook `wa_app_push_cron`). If WP-Cron is
disabled, the server cron must run `wp cron event run --due-now` (every 5 minutes on SiteGround).

## Checking the server

`GET /wp-json/wa/v1/health` → `{ ok, version, auth_header, wa_token_header }`. It shows whether the
`Authorization` and `X-WA-Token` headers reach PHP, and works in every mode.

## Filters

`wa_app_user_allowed`, `wa_app_config`, `wa_app_handoff_prefixes`, `wa_app_push_message`,
`wa_app_news_exclude`, `wa_app_adults_category_ids`.

## Uninstall

Deleting the plugin from wp-admin runs `uninstall.php`. That drops `{prefix}wa_app_sessions`,
`{prefix}wa_app_devices` and `{prefix}wa_push_queue`, and removes the plugin's options, transients
and cron event.
