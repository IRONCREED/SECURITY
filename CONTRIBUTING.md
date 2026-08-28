# Contributing

IRONCREED Security contains focused WordPress plugins intended for independent
installation and eventual submission to the official WordPress Plugin
Directory.

## Before opening a change

Read the repository governance sources listed in `AGENTS.md`. For work on a
specific plugin, also read that plugin's implementation brief and local agent
instructions.

Use one branch and one pull request for one coherent change. State the affected
plugin, user-visible behavior, security and privacy impact, checks performed,
and any release-package change.

## Required properties

- Distributable PHP, JavaScript, CSS, HTML, and documentation follow the
  official WordPress standards applicable to their language.
- Public symbols, options, database tables, hooks, script handles, and
  translation domains use the plugin's declared prefix or namespace.
- Inputs are validated and sanitized; outputs are escaped at the final output
  boundary; state-changing requests require capability checks and nonces.
- User data is minimized, bounded by retention, and documented.
- Runtime dependencies require an explicit record of purpose, license, and
  release-package effect.
- Generated packages contain one plugin directory and no development-only
  material.

## Review evidence

An implementation pull request must report WordPress Coding Standards,
PHP-compatibility, unit/integration tests, Plugin Check, package inspection,
and manual smoke-test results when those checks apply. A suppressed finding
requires a narrow explanation linked to a recorded decision.
