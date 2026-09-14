# IRON WARDEN

IRON WARDEN is the repository-level release guard for IRONCREED Security. It
executes registered rules and can refuse a candidate; it cannot change source,
create norms, grant waivers, or replace human review.

The first scope is `plugins/ironcreed-request-log`. Run the complete lifecycle
from that directory with `composer check`. Internal phases are available for
diagnosis:

```bash
php ../../constitutional-guard/run.php integrity
php ../../constitutional-guard/run.php prebuild
php ../../constitutional-guard/run.php postbuild
php ../../constitutional-guard/run.php all
```

`integrity` verifies the historical manifest and SHA-256 corpus. `prebuild`
checks source and behavior. `all` then creates the production ZIP before
`postbuild` verifies the exact artifact. Current tests evolve normally.
Historical tests preserve release-critical invariants; replacement retains the
old file with `superseded` and an active `supersededBy` entry.

The summary uses `passed`, `failed`, `unavailable`, `pending`, and `manual
evidence required`. Every mandatory result other than `passed` keeps the
release pending and returns a non-zero status. WARDEN, its reports, tests, and
development dependencies never enter a production plugin ZIP.

Every phase begins with fail-closed integrity. Diagnostic runs report a
`phaseStatus`; only a complete `all` run can produce `releaseStatus: passed`.
CI provisions dependencies with `composer install` from the tracked lockfile
before invoking the sole decision command, `composer check`.

WordPress environments use `IRON_WARDEN_WORDPRESS_TEST_COMMAND`,
`IRON_WARDEN_MULTISITE_TEST_COMMAND`, `IRON_WARDEN_CONCURRENCY_TEST_COMMAND`,
`IRON_WARDEN_FRESH_SMOKE_COMMAND`, and `IRON_WARDEN_MULTISITE_SMOKE_COMMAND`.
The installed WP-CLI `plugin check` command supplies Plugin Check. Manual
evidence is read only from `IRON_WARDEN_MANUAL_EVIDENCE_FILE`, which must point
outside tracked source or to a Git-ignored file. Each of the four manual gates
has its own current RFC 3339 UTC date, Git SHA, ZIP SHA-256, and passed status;
the freshness window is 30 days.
