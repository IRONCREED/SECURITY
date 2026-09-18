# Plugin Check review: source files and installation package

Reviewed on 2026-09-17 against `feat/request-log-admin-local`, source commit
`fd71803de86fa7009157efdf5546e8eddcb69c39`.

## Findings in the supplied report

The supplied report contains 133 errors and 490 warnings, for 623 findings.
Every finding was classified by its file path:

| Files | Errors | Warnings | Resolution |
| --- | ---: | ---: | --- |
| `tests/`, `tools/`, `phpcs.xml.dist`, `phpunit.xml.dist` | 133 | 487 | Keep in development source; exclude from the installed package. |
| `AGENTS.md`, `IMPLEMENTATION-BRIEF.md` | 0 | 2 | Keep governance in source; exclude from the installed package. |
| `includes/class-plugin.php` | 0 | 1 | Retain the bundled translation registration; rationale below. |

The 622 development/governance findings are valid observations about files
present in the installed directory. Test doubles deliberately define WordPress
function names; CLI fixtures deliberately use native filesystem functions,
exceptions and diagnostic output. Shipping those fixtures exposes code that was
not intended to run as part of an installed plugin. Adding runtime prefixes or
escaping to the fixtures would not correct the packaging error.

The existing `tools/build.sh` already copies only the distribution allowlist.
The correction is to install its ZIP and replace the previous plugin directory
completely. Source, current tests and accepted historical tests are preserved.
The source README and public installation instructions now explain this flow.

## Remaining translation warning

Code: `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`.

Plugin Check's message qualifies its recommendation with hosting on
WordPress.org. This package is currently installed from a repository-built ZIP
and includes its own `languages/ironcreed-request-log-uk.mo`. The call registers
that custom path and is attached to `init`; it does not load translations before
WordPress locale initialization.

[WordPress Core's i18n guidance](https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/)
explicitly recommends keeping `load_plugin_textdomain()` on `init` when a plugin
supports older WordPress versions or is distributed outside WordPress.org.
The plugin supports WordPress 6.5 onward. The call therefore remains, with a
source comment linking to that guidance. No sniff suppression or excluded-file
configuration was added. Review this decision again if distribution moves
entirely to WordPress.org language packs.

## Verification on the distribution

A diagnostic ZIP was built by the existing builder from this change. The
protected `admin-package-v1.php` archive assertion passed: every ZIP member is
allowlisted and byte-identical to its source. Development and governance files
are absent. The review document itself remains outside the distribution.

The ZIP was installed and activated on disposable WordPress 7.1 with PHP 8.3.6
and MariaDB 10.11.7. Official Plugin Check 2.1.0 was run twice:

```sh
wp plugin check ironcreed-request-log --format=json
wp --require=wp-content/plugins/plugin-check/cli.php plugin check ironcreed-request-log --format=json
```

Both commands exited with status 0 and reported **0 errors, 1 warning**: only
the translation warning reviewed above. The second command enables Plugin
Check's runtime checks. Default stable checks were used, without additional
directory exclusions, ignored codes or ignored warnings. Raw command output is
included with the handoff evidence.

The installed plugin's bundled MO was also exercised with the WordPress locale
filters set to `uk`: `Settings` and `Help` translated to Ukrainian and
`is_textdomain_loaded()` returned true. This verifies the bundled catalog path;
it is not a visual localization review or a WordPress core language-pack test.

The environment emitted WordPress Core update-check connectivity warnings
while contacting WordPress.org. Those warnings are recorded separately from
the Plugin Check findings; neither Plugin Check command reported a connection
error. This run does not prove production hosting connectivity.

## Release status

`composer check` was executed. Historical integrity, the tracked Composer
lockfile, repository validation, PHP syntax, WPCS, PHPCompatibilityWP, current
PHPUnit, package source assertions, translation synchronization and credential
scan passed. The canonical WARDEN run remains pending because its protected
WordPress/Multisite test environment and concurrency boundary are not
configured. Its historical prebuild phase is consequently unavailable; the
canonical build and postbuild phases did not run.

The disposable Plugin Check installation is separate diagnostic evidence, not
a substitute for those WARDEN gates or the remaining manual review. The ZIP
provided for reproduction is a test package, not a release candidate.
