# Request Log usability and scheduled import

ID: `ics-decision-request-log-ux-001`.
Date: 2026-09-16. Status: approved for implementation.
Authority: the product owner's eight-point request in this conversation.

This decision extends the first public release mandate. The pilot has successfully
imported live Hosting Ukraine logs. The next revision provides a single Request
Log screen with Runtime, Hosting Ukraine, Settings and Help tabs; contextual FAQ
links; English source strings and a synchronized Ukrainian (`uk`) translation.

Connections support a domain lookup through the documented read-only `get_id`
method and manual `host_id` entry. A hosting site ID, hosting account ID and
control-panel user ID have different meanings. Lookup suggests the main site
domain for Multisite. An administrator confirms the actual hosting virtual host;
separately hosted mapped domains may need different IDs. Credentials and storage
remain site-local and are never shared with another site's administrators.

Scheduled imports are explicitly authorized as an opt-in extension of the former
manual-only scope. Installation and upgrades leave scheduling off. A separate
setting grants consent for repeated downloads, states the interval and personal
data received, and can be disabled at any time. Manual import and local table
refresh remain separate controls. WP-Cron execution depends on site traffic or
an operator-configured system scheduler; the UI shows last and next attempts.

The provider still only reads today's archive, with the established size, parsing,
retention, deduplication, transactional batch and hard-cap constraints. A separate
nonblocking provider-operation lock prevents overlapping downloads; the existing
short storage transaction lock never encloses HTTP or decompression. Errors use
bounded backoff and safe diagnostics. Disconnect and deactivation cancel pending
imports. Uninstall deletes the added options and scheduled hook on every site.

Package review targets the allowlisted installed ZIP, including assets and
translations, while development tests stay in Git. Production security findings
are corrected or receive narrow, evidenced explanations. Historical tests retain
their bytes and successors preserve changed contracts. The missing pinned
submodule references are restored without weakening Governance validation.

The plugin keeps version 1.0.0 while preparing its first public release; product
contract revisions advance to 0.4.0. The database remains schema 2. Existing
connections and records survive upgrades. Full WARDEN and external manual gates
remain mandatory; successful local diagnostics do not certify a release.
