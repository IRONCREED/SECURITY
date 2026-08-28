# IRONCREED Security

A governance-first monorepo for focused WordPress administration and security
utilities. Every plugin is designed as an independently installable,
independently testable, and independently releasable package.

## Status

The repository is in pre-development. It establishes governance, licensing,
the cross-plugin navigation protocol, the WordPress.org release gate, and the
implementation brief for the first plugin. No production package is released.

The repository must be public before the first WordPress.org submission so
reviewers and users can inspect the maintained source and build process.

## Planned plugins

| Plugin | Slug | Theme | Status |
| --- | --- | --- | --- |
| IRONCREED Request Log | `ironcreed-request-log` | `security` | Specification |

IRONCREED Request Log will show concrete request records and URIs in WordPress
administration. Its first release observes requests that reach the WordPress
runtime. Requests answered by a CDN, web-server cache, static-file handler,
firewall, or an earlier failure remain outside WordPress visibility.

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

## Licensing

The repository uses the pinned shared policy declared in `LICENSE.md`.
Distributable plugin subtrees are licensed under `GPL-2.0-or-later`. Brand
rights remain reserved. Third-party materials retain their upstream terms.
