# IRONCREED Security

A governance-first monorepo for focused WordPress administration and security
utilities. Every plugin is designed as an independently installable,
independently testable, and independently releasable package.

## Status

The repository is public and contains the maintained source, tests, build
tooling, governance, and release documentation for IRONCREED WordPress
utilities.

IRONCREED Request Log 1.0.0 has been submitted to the WordPress.org Plugin
Directory and is awaiting manual review. The production package has passed the
automated WordPress.org submission scan and the project's available local and
CI quality gates. Final directory publication remains pending WordPress.org
approval.

## Plugins

| Plugin | Slug | Theme | Status |
| --- | --- | --- | --- |
| IRONCREED Request Log | `ironcreed-request-log` | `security` | 1.0.0 — awaiting WordPress.org review |

IRONCREED Request Log shows concrete request records and URIs in WordPress
administration. Its first release includes a disabled-by-default WordPress
Runtime Source and an optional read-only Hosting Ukraine API provider for nginx
access logs. The interface keeps the sources separate and states the visibility
boundary of each one.

## Repository map

- `code-constitution/` — pinned universal Code Constitution;
- `.licensing-policy/` — pinned shared repository licensing policy;
- `governance/` — project profile, legislation, and act registry;
- `docs/` — cross-plugin and release contracts;
- `plugins/` — one independently distributable plugin per directory.

Clone with submodules:

```bash
git clone --recurse-submodules https://github.com/IRONCREED/SECURITY.git
```

Existing clones can run:

```bash
git submodule update --init --recursive
```

## Governing sources

Read `CONSTITUTION.md`, `governance/PROFILE.md`, and
`governance/legislation/WORDPRESS_PLUGIN_DEVELOPMENT.md` before implementation.
Official WordPress.org rules and WordPress Coding Standards are mandatory
external constraints for distributable code.

The current Hosting Ukraine service, privacy, and applicable-law review is
recorded in `docs/EXTERNAL-SERVICE-AND-PRIVACY-REVIEW.md`.

Run the complete repository release check from the Request Log plugin directory:

```bash
composer check
```

This command delegates to IRON WARDEN. Individual tools remain diagnostic
executors and do not establish release readiness on their own.

## Licensing

The repository uses the pinned shared policy declared in `LICENSE.md`.
Distributable plugin subtrees are licensed under `GPL-2.0-or-later`. Brand
rights remain reserved. Third-party materials retain their upstream terms.
