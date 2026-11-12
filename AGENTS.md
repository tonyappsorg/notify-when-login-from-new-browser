# Tonyapps_NotifyOnNewBrowserLogin — notes for AI agents and developers

Read this before changing the module. Workspace-wide rules are in ../AGENTS.md.

Emails an admin user when their account signs in from a device it hasn't used before. Package
`tonyapps/module-notify-on-new-browser-login` 1.0.0. Free module per the marketing plan.

## Status

- Done and in use. Last changes: email redesign 2026-11-09, review fixes 2026-10-30.

## Architecture

| Piece | What it does |
|---|---|
| `Model\Observer\StoreLoginDataInformation` | Observer `tonyapps_notify_on_new_browser_login` on `admin_user_authenticate_after` (global `etc/events.xml`), successful results only: admin panel and admin token requests over REST/SOAP/GraphQL; after the password check, before 2FA. Reads the `tonyapps_admin_device_<user id>` cookie. A known token updates the device's IP and last-seen time and stays quiet. Otherwise a new random 256-bit token (`bin2hex(random_bytes(32))`) is set (HttpOnly, Secure on HTTPS, SameSite=Lax, admin cookie path, 2 years: `COOKIE_LIFETIME = 2 * 365 * 86400`), the device is stored, and the admin is emailed. |
| Web API branch | Area `webapi_rest`, `webapi_soap` or `graphql`: API clients keep no cookie, so the device is the SHA-256 of `'api:' . IP . '\|' . user agent`. An integration alerts once, not on every token request, and again when it calls from a new IP. |
| `Model\ResourceModel\Browser` | `isKnown()` / `remember()` on `admin_user_browser`. |
| `cbschuld/browser.php` | Browser name and OS for the email and the table. |
| `view/adminhtml/email/new_browser_login_notification.html` | Email template `admin_security_new_browser_login_template`. |

- The email shows account, channel (admin panel or web API), browser, OS, IP and time (UTC). It is sent to the
  signed-in admin user's own address, from the `general` identity, area `adminhtml`, store 0.
- Any failure (mail, cookie, DB) is logged and never blocks the login.
- Why a cookie and not browser + OS: those are the same on most machines ("Chrome on Windows"), so a stolen password
  used from any such machine raised no alert.

## Data model

- `admin_user_browser`: one row per user and device. `entity_id`, `user_id`, `browser`, `platform` (OS), `ip`
  (last IP), `token_hash` (SHA-256 of the token, never the token itself), `created_at` (first seen),
  `last_seen_at`. Indexes on `user_id` and (`user_id`, `token_hash`).

## Configuration and ACL

- `admin/security/new_browser_login_template` (default scope only): **Stores › Configuration › Advanced › Admin ›
  Security › New Device Sign-in Email**. Default `admin_security_new_browser_login_template`.
- No ACL resources or menu.
- Template variables: `firstname`, `browser`, `platform`, `ip`, `time`, `username`, `channel`, `accountUrl`,
  `adminHost`.

## Decisions and constraints

- Device = random token in a long-lived cookie, stored hashed per user (not browser + OS, see above).
- Two admins sharing a browser each get their own cookie, so they don't reset each other's device.
- Email is sent in the adminhtml area, where the storefront email header/footer templates don't exist, so it
  carries its own frame.
- `accountUrl` links to admin account settings without a secret key (`_nosecret`, `_nosid`) so it works from an
  email (a link with a key would only land on the dashboard).

## Known limitations and open points

- Clearing cookies, a private window, or a new browser profile counts as a new device and sends an email.
- `ponytail:` no alert on a new IP for a known device, and old device rows aren't purged. Add either if needed.

## Tests

```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/NotifyOnNewBrowserLogin/Test/Unit
```

Tests: `Test/Unit/Model/Observer/StoreLoginDataInformationTest.php`, `Test/Unit/View/NotificationEmailTest.php`.

Screenshots of the emails as sent (Luma theme, 2026-11-09) are in `docs/emails/` and shown in README.md.

## Change history

- **Template ID rename** (with the "Redesigning the emails" config setting): the template IDs follow core's
  config-path convention so the setting can offer the default: `tonyapps_notify_on_new_browser_login_template` is
  now `admin_security_new_browser_login_template`. Theme overrides keep working (they go by file name). A custom
  template created from the old ID keeps its content, but its *Template Information* no longer names the default
  it was loaded from.
- **2026-11-09 email redesign** — laid out like the other Tonyapps emails: a *Security alert* heading, the sign-in
  details in a table (account, admin panel or web API, browser and OS, IP, time), a *Wasn't you?* box with steps
  and a **Change your password** button (admin account settings, linked without a secret key), and the admin host
  on top for people who run several stores. Own frame (adminhtml area). New template variables: `username`,
  `channel`, `accountUrl`, `adminHost`.
- **2026-10-30 review fixes** — admin logins over the web API alert too (`/V1/integration/admin/token`, the 2FA
  token endpoints): the observer moved from the panel-only `backend_auth_user_login_success` to the global
  `admin_user_authenticate_after`, and ignores failed attempts. API devices = hash of client IP and user agent.
