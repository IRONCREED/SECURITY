# Request Log Hosting Ukraine service discovery correction

ID: `ics-decision-request-log-discovery-001`.
Date: 2026-09-18. Status: active.
Authority: current authenticated Hosting Ukraine API documentation, the provider's
2025 change log, and the operator's authenticated `get_services` request/response
confirmation.

Hosting Ukraine replaced `get_id` with `get_services` for obtaining service IDs.
Request Log therefore performs domain discovery through the fixed read-only
`POST https://adm.tools/action/get_services/` method with the Bearer token and
`type=host`. The request does not send the entered domain. The provider returns
the host services available to the token; each relevant item exposes a service
`id` and `host` and may also expose `account_id` and `virtual_domain_id`.

Request Log normalizes the administrator-entered domain locally. It selects an
exact `host` match first. Only when no exact match exists may one leading `www.`
be ignored, and that fallback must resolve to exactly one service. The selected
positive service `id` becomes the `host_id` passed to the nginx log method.
`account_id`, `virtual_domain_id`, panel user IDs and WordPress blog IDs never
substitute for `host_id`. Missing, malformed or ambiguous results fail closed.

The complete discovery response is bounded, temporary and not persisted. Public
UI and privacy disclosure state that discovery sends the token and `type=host`,
receives the token-visible host-service list and performs domain matching locally.

This decision supersedes only the `get_id` domain-discovery clause of
`ics-decision-request-log-ux-001`. Its navigation, consent, scheduled-import,
storage, packaging and release-gate decisions remain in force. Historical
`admin-refresh-v1` evidence stays byte-identical and is superseded by a protected
`admin-refresh-v2` successor for the corrected external contract.

Provider references:

- <https://adm.tools/user/api/>
- <https://www.ukraine.com.ua/changelog/2025/>
- <https://www.ukraine.com.ua/wiki/account/api/>
