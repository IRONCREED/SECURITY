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
- WordPress Plugin Handbook privacy guidance:
  <https://developer.wordpress.org/plugins/privacy/>
- Hosting Ukraine access-log documentation and API method:
  <https://www.ukraine.com.ua/wiki/hosting/sites/my-sites/access-log/>
  and <https://adm.tools/user/api/#/tab-sandbox/hosting/log/web/nginx>
- Hosting Ukraine public offer, terms, and privacy policy:
  <https://www.ukraine.com.ua/legal/publicoffer/>,
  <https://www.ukraine.com.ua/legal/tos/>, and
  <https://www.ukraine.com.ua/legal/privacypolicy/>
- Law of Ukraine "On Personal Data Protection":
  <https://zakon.rada.gov.ua/laws/show/2297-17>
- EU General Data Protection Regulation, when applicable:
  <https://eur-lex.europa.eu/eli/reg/2016/679/oj>

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
  Activation alone performs no remote request. The Hosting Ukraine provider is
  contacted only after an authorized administrator configures it and explicitly
  starts a connection test or log fetch.
- The external service provides substantive hosting-log functionality and is
  named in `readme.txt`, with its purpose, transmitted and received data, API
  documentation, Terms of Service, and Privacy Policy.
- The release evidence records a fresh review of the authenticated API contract
  and provider terms. A missing public API license or ambiguous permission for a
  third-party client is resolved with the provider before submission.
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
- IP addresses, URIs containing identifiers, User-Agent, and Referer values from
  the provider are treated as potentially personal data throughout parsing,
  storage, display, testing, deletion, and documentation.
- Provider credentials are stored separately with autoload disabled, are never
  returned to HTML after saving, and are absent from URLs, logs, errors, Site
  Health, exports, fixtures, CI output, and release evidence.
- Privacy Policy Guide content identifies both sources, data fields, purposes,
  access, retention, deletion, and the Hosting Ukraine external-service flow.
- Personal-data exporter and eraser behavior is reviewed for both sources. Any
  decision not to register a callback states why the WordPress email-keyed API
  cannot correctly locate the records and identifies the available deletion
  controls.

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
- The package includes the external-service disclosure and no real token,
  provider response, access log, account identifier, or host-specific fixture.
- Package checksum and source Git commit are recorded in the release report.

## Submission and maintenance gate

- A complete installable ZIP and factual description are submitted.
- The WordPress.org account email is current and monitored by a person.
- Review correspondence is answered with exact corrections and a new package
  only after requested changes are complete.
- The release report links the exact provider documentation and terms reviewed,
  states the request/response contract verified in the authenticated API, and
  records a secret-free smoke-test result.
- After approval, WordPress.org SVN is a release repository. Only coherent
  candidates are committed with descriptive messages and matching tags.
- Security reports, support topics, new WordPress releases, and policy changes
  receive continuing maintenance.
