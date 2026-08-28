# Repository instructions

Read these sources before changing implementation files:

1. `CONSTITUTION.md`;
2. `governance/PROFILE.md`;
3. `governance/legislation/WORDPRESS_PLUGIN_DEVELOPMENT.md`;
4. `docs/IRONCREED-SUITE-PROTOCOL.md`;
5. the local `AGENTS.md` and implementation brief inside the affected plugin.

The official WordPress Plugin Directory rules and WordPress Coding Standards
are mandatory external constraints. Where a generic repository rule and an
official WordPress rule prescribe different forms for distributable plugin
code, the WordPress rule governs that code.

Keep every plugin independently installable and releasable. Do not introduce a
runtime dependency on another IRONCREED plugin merely to share navigation,
branding, utilities, or configuration. Shared navigation must use the small
public protocol defined in `docs/IRONCREED-SUITE-PROTOCOL.md`.

Do not add telemetry, remote code, advertisements, trial restrictions, or an
alternate update mechanism. Do not commit generated ZIP files, dependency
directories, credentials, private logs, production data, or access-log
fixtures containing real personal data.

Every implementation change must include the smallest sufficient automated
evidence and must leave the release package compliant with the WordPress.org
release gate.
