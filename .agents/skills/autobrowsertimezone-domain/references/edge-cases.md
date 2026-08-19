# Domain edge cases

## Browser timezone aliases

Browsers normally report IANA identifiers, but aliases and historical identifiers can exist. Moodle's profile timezone list is the product-level authority for what this plugin may store.

Do not add an ad-hoc alias table. If alias normalization is needed:

- use a Moodle/core-supported normalization path where possible;
- ensure the normalized value is still a valid Moodle profile choice;
- test both old and new identifiers across the supported Moodle versions.

## Server timezone (`99`)

`99` means the user profile follows the server timezone. It is not a browser IANA zone.

When the plugin is enabled and all eligibility rules pass, a browser-reported explicit timezone may replace `99`. That is the core purpose of automatic synchronisation.

A site-level forced timezone is different: it is an administrative override and automatic user-profile writes must not fight it.

## Authentication providers

Examples such as LDAP can map and optionally update profile fields upstream.

A field being editable locally is not always equivalent to "safe to write without consulting the auth plugin". Preserve the authentication plugin's update/ownership contract.

## Concurrency

Two tabs can discover the same mismatch at nearly the same time. The server operation should be safe if both calls arrive:

- first call may update;
- second call should observe the same value and become a no-op;
- no unrelated user fields should be overwritten.

## Retry guard

The client-side guard exists to stop repeated failed calls and reload loops.

A good retry policy distinguishes:

- successful update;
- unchanged result;
- permanent rejection (invalid timezone, permission/policy);
- transient failure (network or temporary server error).

Do not permanently suppress a recoverable mismatch solely because the first transport attempt failed.
