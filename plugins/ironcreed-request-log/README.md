# IRONCREED Request Log

Status: version 1.0 implementation; release-gate validation pending.

The approved version-1.0 contract is `IMPLEMENTATION-BRIEF.md`. The implementation
provides a bounded, privacy-aware view of requests reaching WordPress and a
Hosting Ukraine API source for nginx access logs, with manual actions and
separately authorized scheduled imports.

This directory is licensed under `GPL-2.0-or-later` by the root override. A
release-ready implementation must include its own GPL notice and
WordPress.org-compatible `readme.txt`.

## Public source and WordPress.org assets

The canonical public source for this plugin is:
`https://github.com/IRONCREED/SECURITY/tree/main/plugins/ironcreed-request-log`.

`wordpress-org-assets/` contains presentation assets for the WordPress.org
Plugin Directory, such as `icon-128x128.png` and `icon-256x256.png`. These
files belong in the top-level `/assets` directory of the WordPress.org SVN
checkout and are intentionally excluded from the installable plugin ZIP.
The distribution builder below uses an explicit allowlist and does not package
that directory.

## Install a test package from this repository

This directory is the development source, not an installable distribution.
Do not ZIP the entire directory or upload GitHub's source archive to WordPress:
that includes test fixtures, build tools and governance files. Plugin Check
correctly reports those files when they are inside an installed plugin.

From this directory, build the package with Bash and the `zip` utility:

```sh
bash tools/build.sh "$PWD/../../build"
```

On Windows, use Git Bash with `zip` installed, or WSL. The output is
`build/ironcreed-request-log-1.0.0.zip` at the repository root. The builder copies
only the runtime distribution files. Development POT/PO/MO catalogs remain
source-only; WordPress.org production installs use directory language packs.
`composer build -- "$PWD/../../build"` runs the same builder. A standalone build is suitable for
testing; it does not certify a release candidate. Run `composer check` for the
complete WARDEN release gate and retain its actual pending/unavailable results.

Install that ZIP through Plugins > Add New > Upload Plugin. When replacing a
previous source-folder installation, use WordPress's ZIP replacement flow or
replace the plugin directory through your deployment tool; do not merge files
over the old directory, because that can leave `tests/` and `tools/` behind.
Back up first. Do not use WordPress's Delete action to update: uninstall erases
the plugin's records, credentials and settings.

Run Plugin Check against the installed package, with all its files included.
Do not hide findings with directory exclusions. See
`docs/PLUGIN-CHECK-REVIEW.md` for the reviewed packaging findings and the
WordPress.org pre-review follow-up. The production package does not call
`load_plugin_textdomain()` and does not ship plugin-local translation files.

## UI and localization maintenance

The 2026-09-16 update adds four tabs, domain lookup and opt-in scheduled imports.
Keep English PHP strings, contextual FAQ, development POT/PO/MO and public
disclosure synchronized in every change. These catalogs are source QA assets,
not WordPress.org distribution files. Edit the PO, run
python3 tools/translations.py --write, then python3 tools/translations.py.
The checker rejects missing/obsolete/fuzzy strings and changed placeholders;
it also loads the compiled MO through a standard gettext reader. Source language
stays English; the bundled locale is uk. Admin language follows WordPress.

Tested up to: 7.1 records the operator's successful WordPress 7.1 pilot import
shown on 2026-09-16. It does not replace the pending automated WordPress/Multisite
compatibility matrix or full release evidence.
