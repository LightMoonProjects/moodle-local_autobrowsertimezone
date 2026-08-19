# Bootstrap notes

Initial design choices:

- Browser/device timezone only; no GPS, IP geolocation, or external API.
- Moodle 4.4+ Hooks API.
- Moodle `core/ajax` + an AJAX-enabled external function.
- Server validates the reported timezone against `core_date::get_list_of_timezones()`.
- The current user must have `moodle/user:editownprofile`.
- Moodle forced timezone (`$CFG->forcetimezone != 99`) disables synchronisation.
- Default page reload after a successful update makes server-rendered dates (including Secure Video watermark timestamps) correct immediately.
- No plugin-owned user-data table.

Recommended next steps:

1. Install on staging and test a user whose profile is set to Server timezone.
2. Test Sydney ↔ Perth and Sydney ↔ London device timezone changes.
3. Test sites using a forced timezone.
4. Test an account which cannot edit its own profile.
5. Add PHPUnit coverage for supported-timezone validation and Behat coverage for the user flow.
6. Decide whether to add an optional prompt/consent mode before production rollout.
