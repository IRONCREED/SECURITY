# Request Log usability update — verification

Date: 2026-09-16. Upstream base: `7625c5b316f6e05cb6cb94874502b492cdbfc8f4`
from `IRONCREED/SECURITY/main`. Work branch: `feat/request-log-admin-refresh`.
Release status: **pending**. No production ZIP or ZIP SHA-256 is claimed.

## Requested changes

| Request | Implementation and evidence |
| --- | --- |
| Find the correct hosting site ID | Fixed read-only `get_id` lookup with `type=host`, bounded JSON and strict positive `response.host_id`; manual ID remains available. Domain/credential validation and malformed/oversized/error response cleanup are tested. |
| Clearer connection UI and buttons | Provider selector, domain/manual modes, responsive native controls, test beside the password field; saved credentials are never echoed. Main network domain is suggested for Multisite. |
| One menu and a Settings tab | One Request Log entry; four tabs; old Settings bookmarks redirect to the new tab. View permission cannot access settings. |
| Contextual Help | Collapsible native FAQ; question links open the requested section without requiring JavaScript. Covers sources, IDs, token, panel navigation, Multisite, refresh, cron and privacy. |
| Ukrainian | 128 English/uk strings; committed POT/PO/MO; parity, placeholders and compiled MO are checked by `tools/translations.py` and WARDEN. Maintenance requirements are in README. |
| Automatic import | Separate opt-in intervals of 1/5/15/60 minutes, site-local WP-Cron, operation reservation, safe status, bounded retry/backoff, cancellation and restoration on activation. Table refresh only rereads local records. |
| Plugin Check findings | Explicit nonce/capability boundaries, safe opaque-token validation, translator comments, prepared lifecycle identifiers, scoped uncached schema operations and `Tested up to` metadata. Packaging excludes development files. Official Plugin Check rerun is still required. |
| GitHub failure | Restored the two approved submodule gitlinks; repository validation passes. Added a committed lockfile compatible with the PHP 8.0 CI target. |

The provider lookup contract was checked against a public Hosting Ukraine example;
its current authenticated behavior still requires the manual check below. The
operator's successful 7.1 pilot import supports the readme metadata; it does not
prove the new code's complete WordPress compatibility matrix.

## Executed automated checks

Environment: PHP 8.3.6, Composer 2.7.1, locked PHPUnit 9.6.33, WPCS 3.4.1,
PHPCompatibilityWP 2.1.8. Composer resolves dependencies for PHP 8.0.0 so an
installation on the declared minimum does not select PHP-8.1-only development packages.

| Check | Result |
| --- | --- |
| Current PHPUnit | 76 tests / 687 assertions passed |
| Historical provider-v4 | 8 tests / 29 assertions passed |
| Historical runtime-v3 | 10 tests / 12 assertions passed |
| Historical storage-v4 | 14 tests / 66 assertions passed |
| Historical warden-v4 | 16 tests / 467 assertions passed |
| Historical admin-refresh-v1 | 24 tests / 100 assertions passed |
| Total PHPUnit | **148 tests / 1,361 assertions passed** |
| Missing WordPress environment regression | Six typed boundary cases passed; empty path cannot become the current directory |
| Repository validation / historical byte preservation | Passed |
| PHP syntax / package source assertions | Passed |
| WPCS / PHPCompatibilityWP | Passed; zero WPCS errors or warnings |
| English/uk catalog and compiled MO | Passed, 128 strings |
| Composer validation / audit | Passed; no reported security advisories |
| WARDEN integrity | Passed, including committed and clean lockfile |
| WARDEN prebuild / canonical `composer check` | Pending: external WordPress, Multisite and MySQL environments unavailable |
| WARDEN build/postbuild | Not reached by canonical run; no production artifact |

The historical prebuild phase includes an external WordPress lifecycle assertion;
therefore its aggregate gate is unavailable. The five self-contained historical
PHPUnit suites listed above were also run individually, with the locked binary.
Previously accepted historical files were preserved byte-for-byte. New regressions
and the updated testing-interface closure are SHA-256 protected.

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
3. With a newly issued test credential, resolve the main hosting domain and compare
   the returned ID with the hosting panel. Verify manual ID entry and delegated access.
   Never include the token or real log contents in evidence.
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
