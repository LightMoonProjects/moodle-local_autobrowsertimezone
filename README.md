# Automatic Browser Timezone

`local_autobrowsertimezone` keeps a logged-in Moodle user's profile timezone aligned with the timezone reported by their browser/device.

It intentionally does **not** use GPS, IP geolocation, or a third-party timezone API. The browser reports an IANA timezone such as `Australia/Sydney` using `Intl.DateTimeFormat().resolvedOptions().timeZone`.

## Why

Moodle formats user-facing dates with the user's configured timezone. Keeping the profile timezone current means core Moodle date displays and plugins which use Moodle's user date APIs (including Secure Video watermarks) can show the user's local time correctly.

## Behaviour

When enabled:

1. A logged-in Moodle page loads.
2. The browser reports its current IANA timezone.
3. If it differs from the user's Moodle profile timezone, Moodle validates the timezone against its supported timezone list.
4. The current user's profile timezone is updated.
5. By default the page reloads once so server-rendered dates immediately use the new timezone.

The plugin does nothing for guests, suspended/deleted users, during installation, or when Moodle has a forced site timezone configured.

## Requirements

- Moodle 4.4 or later.
- A browser with `Intl.DateTimeFormat` support.

## Installation

Install the repository as:

```text
local/autobrowsertimezone
```

Then run the Moodle upgrade and visit:

**Site administration → Plugins → Local plugins → Automatic browser timezone**

Enable automatic browser timezone updates.

## Settings

- **Enable automatic browser timezone** — enables detection and profile updates.
- **Reload after timezone change** — reloads the current page after an update so server-rendered dates immediately reflect the new timezone.

## Privacy

The plugin does not maintain its own user-data table and does not send location or timezone data to external services. It updates Moodle's existing `user.timezone` profile field for the currently logged-in user.

## Development status

Initial bootstrap / alpha implementation. Test on a staging Moodle site before production deployment.

## License

GPL v3 or later.
