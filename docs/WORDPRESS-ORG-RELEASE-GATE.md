# WordPress.org release gate

Status date: 2026-08-28.

This gate applies to every plugin intended for the official WordPress Plugin
Directory. Passing it creates release evidence; final acceptance remains a
decision of the WordPress.org Plugins Team.

## Authoritative external sources

- Detailed Plugin Guidelines:
  <https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/>
- Plugin readme standard:
  <https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/>
- Planning, submission, and maintenance:
  <https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/>
- WordPress Coding Standards:
  <https://developer.wordpress.org/coding-standards/>
- Official Plugin Check:
  <https://wordpress.org/plugins/plugin-check/>

Review these sources again before submission because the external order can
change independently of this repository.

## Repository gate

- The development repository is public and links to maintained source.
- The default branch contains the exact source of the submitted candidate.
- Submodules are pinned to reviewed commits.
- Licensing and notices cover every shipped file.
- Security reporting and contributor instructions are current.
- No real access logs, credentials, private fixtures, or production exports are
  present in public Git history.

## Plugin identity gate

- Name, slug, main file, directory, text domain, and prefix are consistent.
- The name is original, non-misleading, and does not begin with another
  project's trademark.
- Main-file and readme headers contain the required current fields.
- `Requires at least` and `Requires PHP` come from the main file and match tests.
- Main-file version, `Stable tag`, changelog, Git tag, package, and later SVN
  tag are identical.
- `Stable tag` is numeric and never `trunk` for the initial plugin.

## Directory-policy gate

- The complete distributable work is GPL-compatible and declares
  `GPL-2.0-or-later`.
- Code is human-readable; transformed assets have public source and documented
  build tools.
- The submitted plugin is complete and usable.
- No trialware, hidden premium code, alternate executable delivery, or
  alternate updater is present.
- No telemetry, remote contact, or tracking occurs without informed consent.
  Version 1.0 of IRONCREED Request Log has none.
- No front-end credit, external asset, advertisement, review pressure, or
  persistent dashboard hijacking is present.
- WordPress-bundled libraries are reused instead of copied.
- `readme.txt` is concise, human-oriented, and uses at most five accurate tags.

## Security and privacy gate

- Every privileged page and endpoint enforces its specific capability.
- Every state-changing request verifies a nonce after capability enforcement.
- Inputs are allowlisted and validated; outputs are escaped at the last
  responsible moment.
- SQL uses `$wpdb` placeholders, controlled table identifiers, and allowlisted
  dynamic ordering.
- File access accepts no URL wrapper, traversal, NUL byte, or unrestricted
  administrator-supplied path.
- Retention, erasure, uninstall, and Multisite behavior are documented.
- Bodies, cookies, authorization headers, passwords, nonces, tokens, secrets,
  and unredacted sensitive query values are never recorded.

## Quality gate

- WordPress Coding Standards pass with zero first-party errors.
- The declared PHP range passes PHPCompatibilityWP.
- Unit, integration, negative-security, Multisite, activation, deactivation,
  uninstall, and upgrade tests pass where applicable.
- Tests cover the minimum WordPress version and the release used for
  `Tested up to`.
- `WP_DEBUG` and `SCRIPT_DEBUG` produce no plugin notice or deprecated call.
- Official Plugin Check passes `Plugin repo`; remaining findings are reviewed.
  Plugin Check assists manual review and does not replace it.
- Accessibility, translations, keyboard operation, and narrow-screen rendering
  receive manual review.

## Package gate

- A clean checkout with pinned tools reproduces the ZIP.
- The ZIP contains one root directory named exactly as the approved slug.
- Development files, tests, fixtures, governance, CI, caches, VCS files, maps,
  reports, and secrets are excluded.
- `readme.txt`, main PHP file, changelog, privacy disclosure, and license are
  present.
- Installed ZIP activation, configuration, observation, deletion, and
  uninstall are tested on a fresh site.
- Package checksum and source Git commit are recorded in the release report.

## Submission and maintenance gate

- A complete installable ZIP and factual description are submitted.
- The WordPress.org account email is current and monitored by a person.
- Review correspondence is answered with exact corrections and a new package
  only after requested changes are complete.
- After approval, WordPress.org SVN is a release repository. Only coherent
  candidates are committed with descriptive messages and matching tags.
- Security reports, support topics, new WordPress releases, and policy changes
  receive continuing maintenance.
