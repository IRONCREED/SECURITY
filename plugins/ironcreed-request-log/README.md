# IRONCREED Request Log

Status: version 1.0 implementation; release-gate validation pending.

The approved version-1.0 contract is `IMPLEMENTATION-BRIEF.md`. The implementation
provides a bounded, privacy-aware view of requests reaching WordPress and a
Hosting Ukraine API source for nginx access logs, with manual actions and
separately authorized scheduled imports.

This directory is licensed under `GPL-2.0-or-later` by the root override. A
release-ready implementation must include its own GPL notice and
WordPress.org-compatible `readme.txt`.

## UI and localization maintenance

The 2026-09-16 update adds four tabs, domain lookup and opt-in scheduled imports.
Keep English PHP strings, contextual FAQ, POT, Ukrainian PO/MO and public
disclosure synchronized in every change. Edit the PO, run
python3 tools/translations.py --write, then python3 tools/translations.py.
The checker rejects missing/obsolete/fuzzy strings and changed placeholders;
it also loads the compiled MO through a standard gettext reader. Source language
stays English; the bundled locale is uk. Admin language follows WordPress.

Tested up to: 7.1 records the operator's successful WordPress 7.1 pilot import
shown on 2026-09-16. It does not replace the pending automated WordPress/Multisite
compatibility matrix or full release evidence.
