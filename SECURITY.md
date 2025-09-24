# Security Hardening Summary

This patch introduces light-weight application defenses in response to recent probing and brute-force activity.

## Request Filtering
- `.htaccess` blocks requests for VCS metadata (`.git`, `.svn`, `.hg`), environment files (`.env`, `_env`), Composer manifests, and any other hidden dotfiles. Equivalent PHP fallbacks live in `includes/security.php` for environments that do not honor `.htaccess`.
- PHP fallback (`enforce_sensitive_path_blocklist`) returns a 404 and records a security event when those paths are requested.

## Response Headers
- Security headers are set twice for redundancy: via `.htaccess` (when Apache modules are available) and `send_security_headers()` in `includes/security.php`. The headers include `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and a conservative Content-Security-Policy. Adjust the CSP in `.htaccess` and/or `includes/security.php` if assets are blocked.
- `send_no_store_headers()` emits `Cache-Control`, `Pragma`, and `Expires` directives for sensitive views such as `login.php`.

## Authentication Flow
- `includes/auth.php` now exposes `is_authenticated()` and `require_auth()` helpers without forcing an automatic redirect. `includes/require-auth.php` wraps these helpers for pages that must be protected.
- `login.php` now sends no-store headers, redirects authenticated users to the dashboard, verifies a CSRF token, tracks failures in both the database and the session, adds a 10-minute rolling window throttle (sleeping before extra attempts), and logs throttled attempts through `log_security_event()`.

## Robots and Logging
- `robots.txt` discourages crawlers from noisy or private directories (`/.git/`, `/vendor/`, `/node_modules/`, `/admin/`) while allowing public assets.
- Security-relevant events append to `logs/security.log` (directory tracked with a `.gitignore`). Only sensitive-path blocks and excessive login attempts are recorded to reduce noise.

## Operational Notes
- Consider enabling a CDN/WAF bot mitigation or rate-limiter for `/login.php` and explicit rules to drop `/.git/*` probes at the edge.
- If additional third-party assets are introduced, pair them with Subresource Integrity (SRI) attributes and update the CSP accordingly.
- Periodically review `logs/security.log` and rotate it through logrotate or the hosting control panel.
