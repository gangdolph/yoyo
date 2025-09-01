# Deployment Notes

- Run migration `migrations/010_create_tokens.sql` in all environments to create the `tokens` table,
  which stores user tokens and expiration data for authentication flows.
- Run migration `migrations/002_create_user_2fa.sql` in production to create the `user_2fa` table
  supporting two-factor authentication.
- Run migration `migrations/001_add_status_to_users.sql` in all environments to add the `status`
  column to `users`, required for registration and presence tracking.
- Run migration `migrations/011_create_login_attempts.sql` in all environments to enable persistent
  tracking of failed login attempts by IP, enforcing rate limits across sessions.
- Run migration `migrations/013_create_service_requests.sql` in all environments to create the `service_requests` table,
  which stores device service requests.
- Ensure `assets/themes.json` is deployed and readable by the web server so the client can load theme definitions.
- Run `schema/payments.sql` in all environments to create the `payments` table for recording Square transactions.
- Configure Square payment credentials in `config.php` (or via environment variables):
  - `square_application_id`
  - `square_location_id`
  - `square_access_token`
  - `square_environment` (`sandbox` or `production`)
