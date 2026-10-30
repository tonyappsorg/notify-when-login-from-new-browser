# Tonyapps_NotifyOnNewBrowserLogin

Emails an admin user when their account signs in from a device it hasn't used before.

## How it works

| Piece | What it does |
|---|---|
| `Model\Observer\StoreLoginDataInformation` | On `admin_user_authenticate_after` with a successful result (global: admin panel and admin token requests over REST/SOAP/GraphQL; after the password check, before 2FA): reads the `tonyapps_admin_device_<user id>` cookie. A known token updates the device's IP and last-seen time and stays quiet. Otherwise a new random 256-bit token is set (HttpOnly, Secure on HTTPS, SameSite=Lax, admin cookie path, 2 years), the device is stored, and the admin is emailed. |
| `admin_user_browser` | One row per user and device: SHA-256 of the token (never the token itself), browser, OS, last IP, first and last seen. |
| `cbschuld/browser.php` | Browser name and OS for the email and the table. |

The email shows the browser, OS, IP and time (UTC). Any failure (mail, cookie, DB) is logged and never blocks the login.

Why a cookie and not browser + OS: those are the same on most machines ("Chrome on Windows"), so a stolen password
used from any such machine raised no alert.

## Notes

* Clearing cookies, a private window, or a new browser profile counts as a new device and sends an email.
* Two admins sharing a browser each get their own cookie, so they don't reset each other's device.
* `ponytail:` no alert on a new IP for a known device, and old device rows aren't purged. Add either if needed.

## Review fixes (2026-10-30)

- **Admin logins over the web API alert too** (`/V1/integration/admin/token`, the 2FA token endpoints): the observer
  moved from the panel-only `backend_auth_user_login_success` to the global `admin_user_authenticate_after`, and
  ignores failed attempts. API clients keep no cookie, so there a device is the hash of the client's IP and user
  agent: an integration alerts once, not on every token request, and again when it calls from a new IP.

## Install

```
bin/magento module:enable Tonyapps_NotifyOnNewBrowserLogin
bin/magento setup:upgrade
```

Requires `cbschuld/browser.php` (in `composer.json`).

## Tests

```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/NotifyOnNewBrowserLogin/Test/Unit
```
