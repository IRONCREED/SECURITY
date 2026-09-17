# Request Log: guided connection, unified administration and Ukrainian locale

The pilot imported Hosting Ukraine logs successfully, but finding the correct
hosting site ID was difficult, settings had a separate menu entry, and the
refresh interval did not import new provider data. The submitted Plugin Check
report also included development files, and Governance CI failed because the
pinned submodule entries were absent from the source tree.

This change adds Runtime, Hosting Ukraine, Settings and Help tabs; a domain lookup
or manual ID connection flow; responsive native controls; a test button beside
the token; contextual FAQ links; and a complete Ukrainian catalog. Multisite
suggests the main network domain and keeps connection, schedule and records local
to the current site.

Scheduled imports require separate administrator consent and default to off.
WP-Cron imports today's archive, deduplicates retained records, prevents overlapping
imports and backs off after failures or provider rate limiting. The UI separates
local table refresh, external import and retention, and shows attempt/success times.
Disconnect and deactivation cancel future jobs; an in-flight import may complete.

The build allowlist includes runtime PHP, assets, API disclosure and translations.
Development tests, tools and configuration stay outside the production package.
The reported input/nonce/escaping and metadata issues were addressed; WPCS and
PHPCompatibilityWP pass. Both governance submodules are restored at approved SHAs.
Development dependencies now have a committed PHP-8.0-compatible lockfile, patched
PHPUnit/WPCS versions and a clean Composer audit.

## Validation

- Current PHPUnit: 76 tests, 687 assertions.
- Five active historical PHPUnit suites: 72 tests, 674 assertions.
- Total: 148 tests, 1,361 assertions, using locked PHPUnit 9.6.33.
- WPCS 3.4.1: zero errors and warnings; PHPCompatibilityWP 2.1.8: passed.
- PHP syntax, source package assertions, repository contracts and translation parity: passed.
- All previously accepted historical files/dependencies remain byte-identical.
- A separate protected regression verifies that missing/incomplete WordPress test paths are unavailable.
- Migration fixtures no longer require root filesystem write permissions; the
  protected storage-v5 successor is exercised separately on PHP 8.0 and 8.4 in CI.
- GitHub Governance passed. Request Log CI on PHP 8.0.30 and 8.4.25 passed current
  PHPUnit (76 tests / 687 assertions per job), storage-v5 (14 / 66), integrity,
  syntax, WPCS, compatibility, package and translation checks.
- Full WARDEN remains pending for WordPress, Multisite and MySQL integration environments.
  Consequently the Request Log workflow still returns nonzero; no mandatory gate is waived.
- No production ZIP was produced. Installed-package smoke, official Plugin Check,
  authenticated domain lookup/scheduled fetch and visual/accessibility review remain pending.

See `docs/REQUEST-LOG-UX-VERIFICATION.md` for the precise scope and remaining checks.

## Product boundaries

WordPress Runtime is opt-in and sees requests that load WordPress. Hosting Ukraine
imports the provider's nginx archive and remains read-only toward the host.
Opening an admin page and activation cause no provider request. Lookup/test/fetch
are explicit administrator actions; periodic downloads require separate consent,
as authorized by `ics-decision-request-log-ux-001`. No local log reader, additional
provider, export or telemetry is introduced. This draft is not a release candidate.
