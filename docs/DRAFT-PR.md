# Draft PR: Close the eight WARDEN review findings

The previous smoke runner could skip cleanup and could accept deactivation without proving persistence. The revised runner completes cleanup before returning, checks actual single-site and network state, owns its temporary site by a unique token, and compares a digest of synthetic rows, options, credentials, diagnostics and capabilities across deactivation. Uninstall checks all site-local and network state.

Historical trust tests now start from a valid isolated repository, mutate one condition, assert the specific refusal, execute real phases with markers, and clean every fixture in `finally`. The existing global dependency registry is retained, reset between runs, and tested for duplicate paths both within and across entries. Accepted historical files remain immutable with active same-phase successors.

Clear/import serialization keeps the first writer's barrier closed until the second writer demonstrates contention on the production lock and MySQL reports its blocking `GET_LOCK()` query. Parent connections are opened after both forks; cleanup terminates and reaps outstanding children before dropping the synthetic table. Strict PHPUnit parsing also covers current and historical suite launchers.

## Evidence and source identity

The supplied change was reported as `841ab34`. That commit could not be retrieved from the remote, so this review uses the supplied diff applied to the verified upstream base `52c694d6b9b14a5e32f5b8bb0c48ab86ce17a544`. Local reconstructed commits are review snapshots, not evidence that the reported remote commit was checked out. The final validation report identifies the corrected diff and local source snapshot; a future release must record its actual final source commit through WARDEN.

- Release status: `pending`.
- Local diagnostic PHPUnit tests, PHP syntax and package source assertions are run separately; their exact results and tool versions accompany the corrected diff.
- The declared PHPUnit version is 9.6.23. Diagnostic execution with system PHPUnit 9.6.17 does not satisfy the exact locked-toolchain gate.
- Canonical `composer check` stops at the absent committed Composer lockfile. No production ZIP or ZIP SHA-256 is claimed.
- Mandatory gates still require locked dependencies, WPCS, PHPCompatibilityWP, WordPress/Multisite environments, real MySQL/MariaDB concurrency, Plugin Check and installed-package smoke execution.
- Current external SHA-bound manual evidence remains required for authenticated Hosting Ukraine smoke, accessibility, localization and final external-policy review.

## Product boundaries

WordPress Runtime observes only requests that load WordPress and remains opt-in. Hosting Ukraine coverage equals the nginx archive returned by its official API; connection and retrieval remain explicit administrator actions. The provider is read-only, changes no hosting configuration, and makes no background requests. No local access-log reader, additional provider, telemetry or export was introduced.
