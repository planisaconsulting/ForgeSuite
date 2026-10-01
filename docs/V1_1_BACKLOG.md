# v1.1 backlog

These were seen during the v1.0.0 audit and were not required to stop data loss, a security bypass, a wrong total, or a lockout. Do not treat this list as scheduled work.

## High

- Self-service password reset with a single-use hashed token, expiry, and session invalidation.
- Boot the restored database under a second host during the company restore drill, not only a SQL count check.
- Nonce-based Content-Security-Policy so inline scripts can be removed.

## Medium

- Web push sender behind `push_enabled`, still with short lock-screen text.
- Server-side image resize when GD or Imagick is available.
- Leading-wildcard customer search that does not scan the name index on a much larger database.
- Remove the unused early PageController “coming soon” copy for modules that shipped.
- Stronger address lock storage that does not depend only on `login_events` volume.

## Low

- Optional Android Trusted Web Activity using this same site.
- Saved-search debounce on very large lists.
- A printed label sheet checked on the workshop’s actual printer.
- In-app help deep links per screen rather than one Help page.
