# Automatic browser timezone

`local_autobrowsertimezone` keeps a logged-in Moodle user's profile timezone aligned with the IANA timezone reported by their web browser/device.

The plugin deliberately does **not** use GPS, IP geolocation, MaxMind, or a third-party timezone API. The browser timezone is obtained locally with `Intl.DateTimeFormat().resolvedOptions().timeZone` and is only sent back to the same Moodle site when it differs from the user's profile timezone.

## Why

Moodle formats user-facing dates using the user's configured timezone. Keeping that profile field current improves date/time display across Moodle and for plugins that correctly use Moodle's date APIs.

## Behaviour

When the plugin is enabled:

1. An eligible logged-in Moodle web page loads.
2. The browser reports its current IANA timezone, for example `Australia/Sydney`.
3. If it differs from the user's Moodle profile timezone, Moodle validates the value against its supported timezone list.
4. The active authentication plugin is given the same old/new user record it would receive from Moodle's own profile-edit page, so it can accept, propagate, or reject the change.
5. The current user's Moodle profile timezone is updated through Moodle's user API only after that authentication-plugin update succeeds.
6. By default the page reloads once so server-rendered dates immediately use the new timezone.

The plugin does not change a timezone when Moodle has a forced timezone configured. It also respects authentication-plugin profile ownership/locking for the timezone field, and skips guests, suspended/deleted users, MNet remote users, CLI requests, users who lack the `moodle/user:editownprofile` capability, and sessions where an administrator is logged in as another user. If the active authentication plugin rejects or fails to propagate the change (for example, an externally managed account configured to update timezone upstream), Moodle's profile timezone is left exactly as it was; no local-only value is committed.

The browser only attempts a given browser/profile timezone mismatch once per browser session. If that attempt fails for a transient reason (a network interruption or a temporary server error), a later page load in the same session may retry it; a deterministic outcome such as an unsupported browser timezone is not retried again until the browser session ends. See [docs/RETRY_GUARD_QA.md](docs/RETRY_GUARD_QA.md) for the manual QA procedure covering this behaviour.

## Requirements and supported Moodle versions

- Moodle **4.5 through 5.2**.
- A web browser with `Intl.DateTimeFormat` timezone support.

Moodle App behaviour is not currently advertised as supported; this initial release targets Moodle's browser interface.

## Installation

Install the plugin in:

```text
local/autobrowsertimezone
```

Then complete the Moodle upgrade and visit:

**Site administration → Plugins → Local plugins → Automatic browser timezone**

Enable automatic browser timezone updates.

## Settings

- **Enable automatic browser timezone** — enables browser timezone detection and profile updates.
- **Reload after timezone change** — reloads the current page after a successful update so server-rendered dates immediately reflect the new timezone.

## Security

The server does not trust the browser value blindly. The AJAX function requires an authenticated session and the `moodle/user:editownprofile` capability, and the reported timezone is validated against Moodle's supported timezone list before the user's profile is updated.

Please report security issues according to [SECURITY.md](SECURITY.md).

## Privacy

The plugin does not create plugin-owned user data tables and does not transmit timezone or location data to an external service. It processes the browser-reported IANA timezone and writes it to Moodle's existing core `user.timezone` profile field. That core profile data is covered by Moodle's `core_user` Privacy API provider.

## Issue tracker and contributions

Public bug reports and feature requests are handled through GitHub Issues:

https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone/issues

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidance.

## Development status

The current repository version is an alpha bootstrap intended for staging validation before the first Moodle Marketplace release. Marketplace-targeted QA items are tracked in [docs/MARKETPLACE_READINESS.md](docs/MARKETPLACE_READINESS.md).

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).
