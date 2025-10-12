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

## Core Commerce Stack Rollout

- Apply migration `migrations/043_device_taxonomy_and_core_flags.sql` to provision the marketplace taxonomy tables,
  wallet ledgers, and supporting indexes. The statements are idempotent and can be run safely on existing databases.
- Enable or disable the new stack with configuration flags in `config.php`/`config.example.php`:
  - `FEATURE_TAXONOMY` — controls brand/model management across products and listings.
  - `FEATURE_ORDER_CENTRALIZATION` — toggles inventory reservation/finalization during checkout.
  - `FEATURE_WALLETS` — exposes wallet balance surfaces and the withdrawal queue.
- Manage brands and models from **Shop Manager → Taxonomy**. Usage counts for products and listings are displayed.
  Only administrators or SkuzE Official staff may create, update, or delete taxonomy entries.
- Inventory reservations and ledger entries are handled by `InventoryService`. When FEATURE_ORDER_CENTRALIZATION is
  enabled the checkout flow should call `OrderService::reserveLocalOrder`, `finalizeLocalOrder`, or `releaseLocalOrder`.
- Wallet balances and withdrawals are surfaced under **Shop Manager → Wallets**. Configure withdrawal fees and minimums
  via `WITHDRAW_FEE_PERCENT_NON_MEMBER`, `WITHDRAW_MIN_CENTS`, and related settings in `config.php`.
- To process payouts programmatically use `WalletService::payoutWithProvider()` with the appropriate `PayoutProvider`
  implementation (e.g. `ManualPayoutProvider` for back office handling).
