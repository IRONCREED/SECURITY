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

The corrected working tree based on `f7d96788cea1d4b587bfaf80ba85ce269ad34f32`
passed current PHPUnit (77 tests / 695 assertions) and the protected
`admin-refresh-v2` successor (25 tests / 108 assertions) with locked PHPUnit
9.6.33. WPCS 3.4.1, PHPCompatibilityWP 2.1.8, PHP syntax, repository validation,
package source assertions, the 128-string English/Ukrainian catalog, credential
scan and WARDEN integrity also passed locally on PHP 8.3.6 and Composer 2.7.1.

The full WARDEN run completed with `COMPOSER_PROCESS_TIMEOUT=0`. Every available
gate passed. The historical prebuild corpus, current WordPress integration,
Multisite and concurrent-storage integration gates were unavailable because their
typed test environments were not configured, so phase and release status remain
**PENDING** and `composer check` correctly returned nonzero. No production ZIP has
been certified.

The 2026-09-17 GitHub CI evidence remains evidence for the pre-correction tree.
Corrected-source GitHub CI, installed-package smoke, official Plugin Check, live
`get_services` discovery/scheduled fetch and visual/accessibility review remain
pending.

See `docs/REQUEST-LOG-UX-VERIFICATION.md` for the precise scope and remaining checks.

## Product boundaries

WordPress Runtime is opt-in and sees requests that load WordPress. Hosting Ukraine
imports the provider's nginx archive and remains read-only toward the host.
Opening an admin page and activation cause no provider request. Lookup/test/fetch
are explicit administrator actions; periodic downloads require separate consent,
as authorized by `ics-decision-request-log-ux-001`. The corrected service-discovery
contract is authorized by `ics-decision-request-log-discovery-001`. No local log
reader, additional provider, export or telemetry is introduced. This draft is not
a release candidate.
