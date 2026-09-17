# Hosting Ukraine API contract for version 1.0

Log-download contract: reviewed from authenticated documentation on 2026-08-28;
pilot import confirmed by the operator on 2026-09-16. Domain-lookup contract:
reviewed against the public provider example on 2026-09-16; authenticated lookup
smoke remains pending. This document is intentionally secret-free. It is the normative
external-service contract for `HostingUkraineApiProvider` in version 1.0.

## Request

| Item | Verified value |
| --- | --- |
| HTTPS endpoint | `https://adm.tools/action/hosting/log/web/nginx/` |
| HTTP method | `POST` |
| Authorization | `Authorization: Bearer <token>` |
| Required parameter | `host_id` — positive integer Hosting Ukraine site/virtual-host identifier (distinct from account_id and panel user IDs) |
| Optional parameter | `date` — `DateTime`; the documented default is `today` |

The plugin stores the token as a secret connection credential. It never places
the token in a URL, log, diagnostic message, fixture, release evidence or HTML
after saving. The token expires six months after its last use according to the
authenticated account page.

## Version-1.0 request boundary

The provider sends `host_id` and intentionally omits `date`. This requests the
provider's documented default day, `today`. The UI therefore offers only
`Fetch today's logs`; it offers neither a date input nor a date range.

The endpoint host and path are constants in the adapter. The administrator
cannot enter an arbitrary URL, host, path, request method or extra request
parameter. Redirects are rejected, including redirects to the same host.

## Response and import boundary

On success, the method returns a `.gz` archive containing the selected nginx
access log. The response is treated as binary data, never JSON. The adapter
accepts one complete daily archive per manual or explicitly scheduled fetch and has no pagination or
cursor behaviour in version 1.0.

The authenticated method screen does not declare a response JSON schema, an
error-body schema, a date-serialization format beyond `DateTime`, a date-range
contract, or a provider maximum response size. Version 1.0 does not rely on
any of those unspecified behaviours. Any non-2xx response, redirect,
unsupported response, invalid gzip archive, parsing failure, timeout or local
response-size limit is reported as a generic provider failure without exposing
the response body.

The implementation imposes a 32 MiB maximum compressed response size as a
local safety bound. It must reject oversized responses without attempting
decompression. This is a plugin safety limit, not a claim about a Hosting
Ukraine limit.

## Limits and read-only behaviour

The current public API guide documents a replenishing limit of 60 requests per
minute and X-RateLimit-Limit, X-RateLimit-Remaining, and Retry-After on 429.
The operator's account may expose a different current limit; response headers
are authoritative for that request. The former hourly/daily figures are removed.

The adapter runs on explicit test/fetch, or after separate scheduling consent.
WP-Cron intervals are 1, 5, 15 or 60 minutes; default is disabled. Every attempt
downloads today's full archive and imports new retained records through the same
bounded parser/repository. This is periodic archive retrieval. Missed prior days
are not backfilled. Retry delay is bounded and 429 pauses scheduled and manual
imports. A separate provider-operation reservation prevents overlapping downloads;
the storage writer lock retains its short transaction-only scope.
Disconnect/deactivation cancel future jobs; a running download may complete.

The method downloads an access-log archive. `HostingUkraineApiProvider` is
read-only with respect to the hosting account; it does not change hosting,
site, DNS, mail, database or account configuration.

## Synthetic test example

Tests use a synthetic gzip fixture and a synthetic `host_id`, such as `12345`.
They use a placeholder credential only:

```http
POST /action/hosting/log/web/nginx/ HTTP/1.1
Host: adm.tools
Authorization: Bearer test-token-not-a-secret
Content-Type: application/x-www-form-urlencoded

host_id=12345
```

The fixture contains no production domain, IP address, user-agent, referer,
URI, account identifier, token or real log entry.

## Source and maintenance

- Authenticated method documentation:
  <https://adm.tools/user/api/#/tab-sandbox/hosting/log/web/nginx>
- Access-log documentation:
  <https://www.ukraine.com.ua/wiki/hosting/sites/my-sites/access-log/>

Review this contract against the authenticated documentation before every
release candidate and after a provider API notice. Update this file and its
tests together if the contract changes.

## Domain lookup

The fixed read-only method is POST https://adm.tools/action/get_id/ with the same
Bearer header and form fields name=<ASCII-domain> and type=host. Read only
response.host_id as a positive integer. Do not substitute account_id,
virtual_domain_id, the panel user ID or a WordPress blog ID. The public example
is the provider forum response of 2021-06-09:
<https://www.ukraine.com.ua/forum/pozhelaniya-i-predlozheniya/Konsol-v-ChatBote-Telegram.html>.
The current guide also documents get_id for a different object type (domain);
that DNS identifier is not used by this adapter.

The discovery response is bounded to 64 KiB, decoded as JSON, and never shown raw.
Redirects, missing/ambiguous IDs, explicit failure, malformed JSON and oversized
bodies fail without modifying saved credentials. HTTP timeout is 20 seconds.
Only an explicit Connect/Test action may perform lookup. Read-only JSON discovery
and gzip log downloads have distinct parsers. Temporary responses are removed.

For a Multisite network using one hosting virtual host, suggest the main site's
domain. Mapped domains on distinct virtual hosts need operator-confirmed IDs.
Credentials, schedules and records remain site-local. Public-source review is
not authenticated evidence. Recheck lookup against the panel before release;
never use screenshot tokens.

Current public API guide:
<https://www.ukraine.com.ua/wiki/account/api/>.
