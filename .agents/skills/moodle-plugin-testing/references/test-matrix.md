# Test matrix

The repository CI configuration and `version.php` are the source of truth. Keep them aligned.

## Current release-intent dimensions

At the time this skill was created, the repository declares Moodle 4.5 through 5.2 support and CI covers:

- Moodle 4.5
- Moodle 5.0
- Moodle 5.1
- Moodle 5.2
- PostgreSQL
- MariaDB

Re-read the files before relying on this list; do not let this reference become a reason to preserve stale compatibility metadata.

## Required scenario families

### Eligibility

- disabled plugin
- guest/not logged in
- suspended/deleted
- login-as
- MNet remote
- forced timezone
- capability denied
- auth plugin ownership/locks

### Validation

- known IANA timezone
- empty value
- `99`
- unsupported/invalid value
- alias/legacy identifier if normalization policy changes

### Mutation

- unchanged no-op
- successful update
- event behaviour
- auth-plugin propagation/rejection
- concurrent/idempotent repeated call

### Client

- match => no request
- mismatch => request
- update + reload enabled
- update + reload disabled
- permanent failure does not storm
- transient failure can recover
