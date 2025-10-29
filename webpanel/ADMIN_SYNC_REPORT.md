# Webpanel ↔ Telegram Bot Synchronization Report

This document captures findings, gaps, and concrete actions to ensure the webpanel fully mirrors the bot’s admin capabilities and both remain strictly synchronized.

## Key Findings
- Payment entity inconsistencies:
  - Mixed identifiers used for Payment_report: some code uses `id`, others `id_payment`. Standardize on one (recommend: `id`) and update all queries/joins to match.
  - Paid states vary (`paid`, `completed`). Treat both as "paid" in reports and totals; normalize write paths to `paid` to avoid drift.
- Duplicate/overlapping functions:
  - `includes/api.php` and `includes/bot_core.php` both implement payment approval/rejection and messaging. Centralize to bot_core (single source of truth) and have all API endpoints call it.
- Permissions:
  - Sidebar links currently not role-gated per item (now wallet history is admin-only). Extend this pattern to sensitive pages (panel/product/payment actions) with `$auth->hasPermission()`.
- CSRF:
  - New admin features include CSRF; legacy endpoints in `webpanel/api/*.php` must be audited to verify CSRF enforcement on all state-changing requests.
- Service lifecycle coupling:
  - Ensure service actions (create/extend/delete) call ManagePanel and DB updates atomically (transactions) and post appropriate Telegram notifications.

## Completed Work
- Added `webpanel/wallet_history.php` (admin-only, filters + pagination).
- Wired to sidebar (admin-only visibility).
- Added sidebar entries for `textbot_manager.php` and `test_quota.php`.

## Required Code Changes (Action Items)
1) Normalize Payment_report identifiers
- Search: `Payment_report` uses of `id_payment` and `id`
- Update all fetch/update/joins to a single PK field (recommend `id`) and adjust WHERE/LIMIT/ORDER BY accordingly.

2) Single approval/rejection path
- Modify `webpanel/api/approve_payment.php` and `reject_payment.php` to call `includes/bot_core.php` `approvePayment($id, $note)` / `rejectPayment($id, $reason)` and return their results; remove duplicated SQL.
- Ensure these functions handle: update status, update user Balance, send user message, send admin report (Channel_Report + topicid).

3) CSRF + Auth hardening for all mutating endpoints
- Enforce: `$auth->requireLogin()`; check role via `$auth->hasPermission()` or `administrator` where needed; verify CSRF token from POST for:
  - `webpanel/api/user_action.php` (balance, status, delete)
  - `webpanel/api/service_action.php` (extend/delete/reissue)
  - `webpanel/api/panel_crud.php`, `product_crud.php`, `discount` endpoints
  - `webpanel/api/approve_payment.php`, `reject_payment.php`

4) Service lifecycle sync
- Ensure `service_action.php` calls ManagePanel for:
  - create user (on new service), extend time/traffic, delete user on panel when service deleted
- Wrap DB + panel ops in transactions or compensating logic; log failures to `admin_logs`.

5) Text content management
- Confirm `textbot_manager.php` edits the exact keys the bot reads from `textbot` table; add a read-only preview that fetches from the table to avoid stale cache.

6) Force-join Channels
- Confirm bot reads `channels` table and enforces on every command/inline entry.
- Add a quick validator in UI to check link format (`t.me/` or `https://t.me/`) and resolve channel ID via bot if necessary (optional enhancement).

7) Test quota management
- Verify bot checks `user.limit_usertest` before issuing tests; panel `test_quota.php` must update that field only and log changes.

8) Auditing and logs
- Extend `admin_logs` usage across all admin actions (payment decisions, service changes, content edits).
- Add an Admin Logs page (filter by admin/action/date).

## E2E Verification Plan
- Payments
  - Create a pending payment → approve via panel → expect: status=paid, Balance+=amount, Telegram DM sent, admin channel report posted.
  - Reject path → expect: status=rejected, DM with reason, no balance change.
- Services
  - Create new service from invoice → user exists on panel, DB invoice active, config delivered to user.
  - Extend service → expiry updated both in DB and panel (where applicable).
  - Delete service → removed from panel and DB.
- Content and quotas
  - Update a text via Textbot Manager → trigger same bot path and confirm rendered text matches.
  - Change test quota and attempt to use bot test flow → enforced per updated limit.
- Channels
  - Add a required channel → a non-joined test account should be blocked until joining.

## Deployment Checklist
- DB backup before schema normalization; migration script for Payment_report PK/name consistency.
- ENV and config parity between bot and panel (DB creds, bot token, Channel_Report).
- Optional: enable maintenance mode while migrating payment identifiers.
- Smoke test endpoints with a limited admin user, then enable all admins.

## Suggested Follow-ups
- Implement Admin Logs page.
- Add role-based menu rendering for all sensitive links, not just wallet history.
- Consolidate duplicate helpers across `includes/api.php` and `includes/bot_core.php`.
