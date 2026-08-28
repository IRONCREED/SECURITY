# Hosting Ukraine API contract for version 1.0

Status: verified on 2026-08-28 from the authenticated Hosting Ukraine API
documentation. This document is intentionally secret-free. It is the normative
external-service contract for `HostingUkraineApiProvider` in version 1.0.

## Request

| Item | Verified value |
| --- | --- |
| HTTPS endpoint | `https://adm.tools/action/hosting/log/web/nginx/` |
| HTTP method | `POST` |
| Authorization | `Authorization: Bearer <token>` |
| Required parameter | `host_id` — integer Hosting Ukraine hosting-account identifier |
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
accepts one complete daily archive per manual fetch and has no pagination or
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

The authenticated account page displays 5,000 API requests per hour and 28,800
per day. Version 1.0 makes no background calls: the endpoint is invoked only by
an authorized administrator's explicit connection test or manual fetch.

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
