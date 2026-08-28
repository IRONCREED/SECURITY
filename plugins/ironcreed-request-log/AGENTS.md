# IRONCREED Request Log instructions

Treat `IMPLEMENTATION-BRIEF.md` as the product contract for version 1.0. Read
the root governance and suite protocol first. Address the task as a careful
junior developer receiving senior review: ask only when an unresolved choice
changes user-visible behavior, security, privacy, licensing, or release scope.

Implement the numbered milestones in separate reviewable commits within one
feature branch. Preserve version-1.0 scope. Version 1.0 includes the local
WordPress Runtime Source and the opt-in `HostingUkraineApiProvider` defined in
the brief. Do not add a local-file reader, another hosting provider, background
sync, live tail, export, telemetry, charts, or a companion-plugin dependency.

Treat provider records as potentially personal data and provider credentials as
secrets. Do not make a network request before an authorized administrator
explicitly configures the connection and starts a test or fetch. Verify the
current authenticated Hosting Ukraine API contract instead of inferring request
fields, and document the verified external-service behavior in `readme.txt` and
the Privacy Policy Guide.

Complete every acceptance check and include a release-candidate report before
presenting the plugin as ready for WordPress.org submission.
