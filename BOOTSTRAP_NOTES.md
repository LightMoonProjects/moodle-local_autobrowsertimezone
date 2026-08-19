# Bootstrap notes

The bootstrap is intentionally structured for an eventual Moodle Marketplace submission rather than as a site-specific hack.

Key design choices:

- Browser/device timezone only; no GPS, IP geolocation, or external API.
- Supported Moodle baseline is 4.5–5.2 for the initial Marketplace-targeted release.
- Moodle Hooks API and autoloaded classes; no legacy `lib.php` callback is required.
- Moodle `core/ajax` and an AJAX-enabled external function.
- Server validates the reported timezone against `core_date::get_list_of_timezones()`.
- The current user must have `moodle/user:editownprofile`.
- Moodle forced timezone (`$CFG->forcetimezone != 99`) disables synchronisation.
- "Login as" sessions and MNet remote users are excluded from automatic profile writes.
- Authentication-plugin ownership and locking of the timezone profile field is respected.
- Default page reload after a successful update makes server-rendered dates correct immediately.
- No plugin-owned database tables and no external data transfer.
- Privacy metadata explicitly declares processing through Moodle's `core_user` subsystem.

See `docs/MARKETPLACE_READINESS.md` for the pre-release validation checklist.
