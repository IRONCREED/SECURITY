# IRONCREED Request Log instructions

Treat `IMPLEMENTATION-BRIEF.md` as the product contract for version 1.0. Read
the root governance and suite protocol first. Address the task as a careful
junior developer receiving senior review: ask only when an unresolved choice
changes user-visible behavior, security, privacy, licensing, or release scope.

Implement the numbered milestones in separate reviewable commits within one
feature branch. Preserve version-1.0 scope. Do not add Server Access Log Source,
IP/User-Agent/Referer storage, export, telemetry, remote services, charts, or a
companion-plugin dependency.

Complete every acceptance check and include a release-candidate report before
presenting the plugin as ready for WordPress.org submission.
