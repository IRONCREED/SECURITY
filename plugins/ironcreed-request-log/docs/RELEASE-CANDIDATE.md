# Version 1.0 IRON WARDEN release-readiness record

Status: pending. The implementation becomes a release candidate only after every mandatory automated gate passes.

## Scope and boundaries

The implementation contains two independently enabled sources. WordPress Runtime observes only requests that load WordPress. Hosting Ukraine API manually obtains the current day's gzip nginx access log returned by the fixed, read-only provider method. Provider connection tests and fetches are the only network triggers. The implementation includes no local-file reader, background provider synchronization, live tail, export, telemetry, remote assets, or updater.

## Architecture and data

Immutable domain normalization and event validation feed application provider and HTTP ports. WordPress infrastructure implements HTTP, database, migrations, retention, Cron, capabilities, and lifecycle. The admin adapter provides source-separated display and privileged actions. Suite navigation and immutable Product ID guards remain local to the independently installable plugin.

Schema version 2 uses a site-prefixed event table with indexes for time, source, status, method, and route kind. Runtime fingerprints remain nullable, while a source/fingerprint unique key deduplicates provider records. Default retention is 24 hours and the default hard cap is 10,000. Credentials occupy a distinct non-autoload option and are deleted on disconnect and uninstall.

## Privacy and security review

Runtime records omit client IP, User-Agent, Referer, bodies, cookies, authorization values, and user identity. Both sources redact the mandatory sensitive-query key set before storage. Provider records remain potentially personal data throughout their lifecycle. Capability checks precede nonce checks on every state-changing handler. Output is escaped in its final HTML context. The WordPress email-keyed exporter and eraser cannot reliably associate these request records with a person; source-specific clear controls, bounded retention, and complete uninstall are the supported deletion mechanisms.

## Candidate checks

The repository supplies deterministic unit tests using a mock HTTP client, WPCS and PHPCompatibilityWP configuration, package assertions, an allowlist ZIP builder, and CI. The final release operator must record a fresh-site and Multisite smoke test, accessibility and localization review, authenticated secret-free provider smoke test, official Plugin Check result, exact source commit, tool versions, ZIP SHA-256, and verification against current external policies.

The canonical command is `composer check`. The latest run verified the strengthened manifest, protected testing interface, and historical behavior dependencies, then refused before prebuild because the committed Composer lockfile is unavailable. Separate `prebuild` and `postbuild` diagnostics produced the same integrity refusal. Their `phaseStatus` and the overall `releaseStatus` remained `pending`.

Packagist returned `CONNECT tunnel failed, response 403`, so no lockfile was fabricated. Composer dependencies, WPCS, PHPCompatibilityWP, PHPUnit behavior tests, WordPress and Multisite integration, real-database concurrency, Plugin Check, installed-package smoke tests, and the four independent manual gates remain pending or unavailable. ZIP SHA-256 remains unavailable because fail-closed integrity correctly prevented the canonical build.

## Known limitations

WordPress Runtime cannot observe requests completed by a CDN, WAF, web server, full-page cache, static handler, or another layer before WordPress. Hosting Ukraine coverage equals the records and period returned by its nginx log API. The provider offers today's archive only in version 1.0. There is no email-keyed personal-data export, cross-site viewer, automatic fetch, date range, live tail, or export.
