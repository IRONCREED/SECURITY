# Request Log usability update — verification

Date: 2026-09-18. Upstream base: `7625c5b316f6e05cb6cb94874502b492cdbfc8f4`
from `IRONCREED/SECURITY/main`. Work branch: `feat/request-log-admin-local`.
Release status: **pending**. No production ZIP or ZIP SHA-256 is claimed.

## Requested changes

| Request | Implementation and evidence |
| --- | --- |
| Find the correct hosting site ID | Fixed read-only `get_services` discovery with `type=host`; the domain is matched locally against `response[].host`, exact match first and a unique leading-`www.` alias only as fallback. The matching positive service `id` becomes `host_id`; `account_id` and `virtual_domain_id` are not substituted. Manual ID remains available. |
| Clearer connection UI and buttons | Provider selector, domain/manual modes, responsive native controls, test beside the password field; saved credentials are never echoed. Main network domain is suggested for Multisite. |
| One menu and a Settings tab | One Request Log entry; four tabs; old Settings bookmarks redirect to the new tab. View permission cannot access settings. |
| Contextual Help | Collapsible native FAQ; question links open the requested section without requiring JavaScript. Covers sources, IDs, token, panel navigation, Multisite, refresh, cron and privacy. |
| Ukrainian | 128 English/uk strings; committed POT/PO/MO; parity, placeholders and compiled MO are checked by `tools/translations.py` and WARDEN. Maintenance requirements are in README. |
| Automatic import | Separate opt-in intervals of 1/5/15/60 minutes, site-local WP-Cron, operation reservation, safe status, bounded retry/backoff, cancellation and restoration on activation. Table refresh only rereads local records. |
| Plugin Check findings | Explicit nonce/capability boundaries, safe opaque-token validation, translator comments, prepared lifecycle identifiers, scoped uncached schema operations and `Tested up to` metadata. Packaging excludes development files. Official Plugin Check rerun is still required. |
| GitHub failure | Restored the two approved submodule gitlinks; Governance CI passes. Added a PHP-8.0-compatible lockfile and portable migration fixtures; current unit tests pass on PHP 8.0 and 8.4. |

The provider's March 2025 changelog states that `get_services` replaced `get_id`
for obtaining service IDs. The current authenticated API screen confirmed
`POST /action/get_services/`, `type=host` and a response list containing service
`id` and `host` plus account metadata. The operator confirmed that request/response
shape on 2026-09-18. A post-correction plugin smoke test remains pending.

The original `admin-refresh-v1` historical suite remains byte-identical and is now
superseded by `admin-refresh-v2`, which protects the corrected discovery contract
together with the prior consent, administration and runtime-privacy regressions.

## Corrected-source verification — 2026-09-18

Environment: corrected working tree based on
`f7d96788cea1d4b587bfaf80ba85ce269ad34f32`, PHP 8.3.6, Composer 2.7.1,
locked PHPUnit 9.6.33, WPCS 3.4.1 and PHPCompatibilityWP 2.1.8. Translation
generation was run under Python 3.12.3 in WSL.

| Check | Result |
| --- | --- |
| Current PHPUnit | **77 tests / 695 assertions passed** |
| Historical admin-refresh-v2 | **25 tests / 108 assertions passed** |
| Translation generation and validation | Passed; 128 English/uk strings, placeholders and compiled MO verified |
| Repository validation | Passed |
| WARDEN integrity | Passed; historical manifest and Composer lockfile checks passed |
| PHP syntax | Passed |
| WordPress Coding Standards | Passed |
| PHPCompatibilityWP | Passed |
| Package source assertions | Passed |
| Credential and fixture scan | Passed |
| `git diff --check` | Passed |
| Full WARDEN prebuild | **PENDING**: historical prebuild corpus, WordPress integration, Multisite and concurrent-storage integration unavailable because typed environments were not configured |
| WARDEN build/postbuild | Not reached; no production artifact |

`COMPOSER_PROCESS_TIMEOUT=0 composer check` completed the WARDEN run. All available
gates passed. The command returned exit code 1 because mandatory typed integration
gates were unavailable and WARDEN therefore retained phase/release status
**PENDING**. An earlier invocation hit Composer's default 300-second process timeout;
that orchestration timeout was removed for the completed run and is not recorded as
a product-test failure.

The original `admin-refresh-v1` suite remains byte-identical and superseded. The
new `admin-refresh-v2` successor and its dependencies are SHA-256 protected by the
historical manifest; WARDEN integrity passed after the correction.

## Previously executed automated checks

The results below belong to the pre-correction source verified on 2026-09-17.
They are retained as historical evidence only; the corrected-source rerun is above.

Environment: PHP 8.3.6, Composer 2.7.1, locked PHPUnit 9.6.33, WPCS 3.4.1,
PHPCompatibilityWP 2.1.8. Composer resolves dependencies for PHP 8.0.0 so an
installation on the declared minimum does not select PHP-8.1-only development packages.

| Check | Result |
| --- | --- |
| Current PHPUnit | 76 tests / 687 assertions passed on pre-correction source |
| Historical provider-v4 | 8 tests / 29 assertions passed |
| Historical runtime-v3 | 10 tests / 12 assertions passed |
| Historical storage-v5 | 14 tests / 66 assertions passed |
| Historical warden-v4 | 16 tests / 467 assertions passed |
| Historical admin-refresh-v1 | 24 tests / 100 assertions passed; now superseded |
| Historical admin-refresh-v2 | Pending rerun on corrected source |
| Total PHPUnit | **148 tests / 1,361 assertions passed on pre-correction source** |
| Missing WordPress environment regression | Six typed boundary cases passed; empty path cannot become the current directory |
| Repository validation / historical byte preservation | Passed on pre-correction source |
| PHP syntax / package source assertions | Passed on pre-correction source |
| WPCS / PHPCompatibilityWP | Passed on pre-correction source; zero WPCS errors or warnings |
| English/uk catalog and compiled MO | Passed on pre-correction source, 128 strings |
| Composer validation / audit | Passed on pre-correction source; no reported security advisories |
| WARDEN integrity | Passed on pre-correction source, including committed and clean lockfile |
| WARDEN prebuild / canonical `composer check` | Pending: external WordPress, Multisite and MySQL environments unavailable |
| WARDEN build/postbuild | Not reached by canonical run; no production artifact |

The historical prebuild phase includes an external WordPress lifecycle assertion;
therefore its aggregate gate remains unavailable without the typed environment.
Previously accepted historical files remain byte-for-byte.

GitHub CI exposed an existing migration fixture that attempted to write under
`/synthetic-wordpress` at the filesystem root. This succeeded in the local root
environment but failed on unprivileged GitHub runners. Current migration tests and
the new protected storage-v5 successor now use a committed synthetic include inside
the test tree, without writing to the filesystem. Storage-v4 remains unchanged and
superseded. Both PHP jobs run the successor explicitly; neither job cancels the other,
and a failed WARDEN run also prints current PHPUnit diagnostics.

## GitHub verification

The PR source at `c09ae6886f76375bf59fda2fcd20484854760421` was verified by:

- [Governance run 35181743124](https://github.com/IRONCREED/SECURITY/actions/runs/35181743124): passed.
- [Request Log run 35181743127](https://github.com/IRONCREED/SECURITY/actions/runs/35181743127):
  PHP 8.0.30 and 8.4.25, Composer 2.10.3. Both jobs passed historical storage-v5
  (14 tests / 66 assertions), current PHPUnit (76 tests / 687 assertions), integrity,
  syntax, WPCS, PHPCompatibilityWP, package assertions and translation checks.

Both Request Log jobs retain a failed workflow conclusion because `composer check`
returns nonzero when mandatory WordPress, Multisite and MySQL integration boundaries
are unavailable. No current unit test is failing in that run. The WARDEN phase and
release status are **pending**; missing gates have not been waived or reported as passed.
This GitHub evidence predates the `get_services` correction; no corrected-source CI
run is claimed here.

The original Plugin Check attachment contained 118 errors and 416 warnings, mostly
in development tests/configuration included in the installed source directory.
WPCS passing is not a substitute for official Plugin Check against the eventual
production package. The new protected postbuild assertion checks the complete
runtime allowlist and the exact bytes of every ZIP member.

## Remaining release evidence

1. Provide disposable WordPress single-site and Multisite test environments and
   the typed MySQL/MariaDB boundary, then run `composer check` from the plugin directory.
   The canonical run must pass prebuild before producing the production archive.
2. In the resulting installed package, run official Plugin Check and fresh-site /
   Multisite activation, deactivation, reactivation and uninstall checks. The smoke
   assertions now include import options, saved status and removal of both cron hooks.
3. With a newly issued test credential, call the corrected `get_services` discovery
   through the plugin, resolve the main hosting domain locally, and compare the selected
   service `id`/saved `host_id` with the hosting panel. Verify exact-domain and unique
   leading-`www.` matching, manual ID entry and delegated access. Never include the
   token, complete service list or real log contents in evidence.
4. Enable a one-minute import, close the plugin screen, generate test site traffic,
   then check last success and record count. Confirm repeated retrieval deduplicates,
   disabling/disconnecting stops future attempts, and an overdue cron displays help.
5. Check Ukrainian on desktop/mobile, keyboard-only mode selection and FAQ navigation,
   and the two connection modes with JavaScript disabled. Automated HTML tests pass;
   visual browser review remains pending because a local browser download was unavailable.
6. Complete the repository's final source/ZIP-bound manual evidence requirements.

No runtime data, live credentials, provider response bodies or production archives
were added to the repository. Both runtime and provider opt-ins remain off on a
fresh installation. The UI explains that WordPress cron depends on traffic or a
system scheduler and that scheduled retrieval downloads today's archive only.

## Development dependency maintenance

The previous pins were covered by upstream advisories. Updated to the fixed
PHPUnit 9.6.33 and WPCS 3.4.1 releases and reran Composer audit:

- [PHPUnit advisory](https://github.com/sebastianbergmann/phpunit/security/advisories/GHSA-vvj3-c3rp-c85p)
- [WPCS advisory](https://github.com/WordPress/WordPress-Coding-Standards/security/advisories/GHSA-3pwp-g2mj-5p3v)

These are development dependencies and are excluded from the distributable plugin.
