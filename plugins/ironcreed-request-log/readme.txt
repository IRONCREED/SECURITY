=== IRONCREED Request Log ===
Contributors: ironcreed
Tags: request log, security, privacy, debugging
Requires at least: 6.5
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inspect bounded WordPress requests and optionally scheduled Hosting Ukraine nginx access logs with privacy controls.

== Description ==

IRONCREED Request Log offers two opt-in, clearly separated sources.

* **WordPress Runtime** records requests that load WordPress. It cannot see traffic completed by a CDN, WAF, web server, full-page cache, static handler, or any layer before WordPress.
* **Hosting Ukraine API** retrieves today's nginx access-log archive manually or on an explicitly enabled schedule. It shows only records and coverage returned by the provider API.

This is intentionally a visibility-boundary diagnostic rather than a generic arbitrary-file log viewer. It keeps application-observed WordPress requests separate from provider-supplied nginx records, so operators can see what each layer can and cannot observe without a local-file reader, telemetry, or a general analytics stack.

Both sources start disabled. Records use bounded retention and count limits. Sensitive query values are redacted. Runtime records omit IP addresses, User-Agent, Referer, bodies, cookies, and authorization data. Hosting Ukraine records may include IP addresses, URI identifiers, User-Agent, and Referer and must be covered by the site's privacy notice and lawful basis.

The plugin has no telemetry, advertising, export, live tail, public endpoint, alternate updater, or local-file reader. It never sends fetched logs to IRONCREED or another service.

Development source, tests, build tooling, and release documentation are maintained at [IRONCREED/SECURITY](https://github.com/IRONCREED/SECURITY/tree/main/plugins/ironcreed-request-log).

== Installation ==

1. Install and activate the distribution ZIP in WordPress. For a source checkout, use tools/build.sh as described in the repository README; do not ZIP the development directory with its tests and tools.
2. Open Tools > Request Log. Logging remains disabled until an administrator enables WordPress Runtime.
3. Open Settings, choose Hosting Ukraine API, and enter a token. Resolve the hosting site ID by domain or enter it manually. Test uses the form values; saving a manual ID makes no network request.
4. Use Fetch today's logs, or separately allow Scheduled imports and choose an interval. Refresh saved records only reloads the local table.
5. Open Help or a question-mark link for instructions. The plugin is internationalized; WordPress.org directory installs receive translations through WordPress.org language packs when available.

On Multisite, each site stores and displays its own records. Uninstall removes records, settings, credentials, all scheduled jobs, and capabilities from every site. Deactivation preserves data and credentials but cancels scheduled jobs until reactivation.

== External services ==

The optional Hosting Ukraine integration calls `https://adm.tools/action/hosting/log/web/nginx/` when an authorized administrator explicitly tests/fetches or separately enables scheduled imports. The read-only site lookup calls `https://adm.tools/action/get_services/` with `type=host` and the Bearer token. It receives the host services available to that token, matches the entered domain locally, and uses the matching service `id` as `host_id`; `account_id` and `virtual_domain_id` are not used as substitutes. The discovery list is not stored. Test/import requests send the saved Bearer token in the Authorization header and the matched Hosting Ukraine host ID in the request body. The log response is a gzip nginx access-log archive that may contain timestamps, IP addresses, methods, URIs, statuses, response sizes, User-Agent values, and Referer values. Imported records are retained in the local WordPress database. Disconnect deletes credentials, cancels future scheduled imports, and leaves imported records until retention expiry or manual clearing.

Review the [API method](https://adm.tools/user/api/#/tab-sandbox/hosting/log/web/nginx), [general API guide](https://www.ukraine.com.ua/wiki/account/api/), [access-log documentation](https://www.ukraine.com.ua/wiki/hosting/sites/my-sites/access-log/), [Terms of Service](https://www.ukraine.com.ua/legal/tos/), [public offer](https://www.ukraine.com.ua/legal/publicoffer/), and [Privacy Policy](https://www.ukraine.com.ua/legal/privacypolicy/) before connecting.

== Frequently Asked Questions ==

= Is WordPress Runtime a complete server access log? =

No. It records requests only after WordPress loads.

= How is this different from a generic log viewer? =

It does not open arbitrary server files or present one source as complete. It records only requests WordPress actually sees and can optionally retrieve the hosting provider's current-day nginx archive through a read-only API. The two sources remain separate, with explicit visibility and privacy boundaries.

= Does Hosting Ukraine change my server? =

No. The provider adapter downloads the current day's nginx log and remains read-only with respect to hosting configuration.

= When does the plugin make network requests? =

On an explicit domain lookup, connection test or log fetch, and periodically after separate opt-in. Installing, activating, opening a screen or saving a manual connection does not contact the provider. A token entered for a test is used but not saved.

= Why is automatic import late? =

WP-Cron needs site traffic or an operator-configured system scheduler. Low traffic, disabled cron or failed loopbacks can delay execution. The UI shows the last and next attempt. Errors back off; Retry-After is honored for rate-limited imports. Each download covers today's archive only.

= Which ID should Multisite use? =

For a shared hosting virtual host, start with the main network site domain. Separately hosted mapped domains may need different IDs. IDs from the user header, hosting account or WordPress blog are different objects. Connections remain local to the configured site.

= Does the WordPress personal-data exporter identify records by email? =

The plugin does not register an exporter or eraser because records have no reliable WordPress-user identity and an email-keyed lookup cannot correctly identify all related URI, IP, User-Agent, or Referer values. Administrators can filter and clear records by source, and uninstall removes all plugin data.

== Privacy ==

Administrators control enablement, access, retention, clearing, disconnect, and uninstall. Default retention is 24 hours with a 10,000-record cap; retention ranges from one hour to 30 days and the cap from 100 to 100,000. The plugin supplies suggested Privacy Policy Guide text. Site owners determine their lawful basis and privacy notice; the plugin does not promise legal compliance.

== Changelog ==

= 1.0.0 =
* Initial public release with WordPress Runtime and Hosting Ukraine API sources.
