# Support Ticket & SLA Management System (Laravel)

## Setup
1. `composer install && npm install && npm run build`
2. Run `cp .env.example .env` ; 
3. Run `php artisan key:generate`
4. Create database named `ticket_management`
5. `php artisan migrate:fresh --seed`
6. Run: `php artisan serve`, `php artisan queue:work`, `php artisan schedule:work`
7. Tests: create a `ticket_system_test` database, then `php artisan test`

Seeded users (password `password`): admin@example.com, agent1@example.com, agent2@example.com, customer1@example.com, customer2@example.com

## Features
Ticket CRUD · yearly ticket numbers (TKT-2026-000001) · Admin/Agent/Customer roles · manual + automatic assignment ·
safe concurrent accept · conversation with internal notes · private attachments · SLA by priority with business hours ·
scheduled warning + escalation · queued e-mail/database notifications · search/filter/pagination · chunked CSV import ·
dashboard · full activity history

## Design decisions
**Authorization.** Role enum on `users`; Policies (`TicketPolicy`, `AttachmentPolicy`) checked in controllers; list queries scoped with `Ticket::visibleTo()`; form requests only accept fields the role may edit. Customers can never read internal notes (excluded in the query) or their attachments (policy).

**Ticket numbers.** `ticket_sequences(year, last_number)`; the row is locked (`lockForUpdate`) inside the transaction that creates the ticket. Unique index as a safety net. Format `TKT-YYYY-%06d`, which grows past six digits automatically.

**Concurrent assignment.** `UPDATE tickets SET assigned_agent_id = ? WHERE id = ? AND assigned_agent_id IS NULL`. The affected-row count decides the winner; the loser receives a clear message. No dependence on in-memory state.

**Automatic assignment.** Fewest active tickets among available agents; ties go to the agent assigned longest ago (`last_assigned_at`), which gives round-robin behaviour. Optional on creation via `TICKETS_AUTO_ASSIGN`.

**SLA.** `sla_policies` maps priority → business minutes (data, not code). `sla_due_at` is stored on the ticket; *within / approaching / breached* are derived from it, so filters, the dashboard and the scheduler are indexed range queries. Changing priority recalculates from the original creation time.

**Business hours.** `config/sla.php` (days + open/close). `BusinessHoursCalculator` consumes the minutes day by day, skipping closed time. Example: Critical (2h) created Thursday 17:00 → due Sunday 10:00.

**Escalation.** `tickets:check-sla` runs every 5 minutes. Each transition (warned, breached) is claimed with a conditional UPDATE, so overlapping runs cannot double-escalate. Escalation sets `escalated_at`, writes history, and notifies the agent and admins.

**Notifications.** Domain events → one subscriber → one queued `TicketNotification` (mail + database), dispatched after commit. Requests never wait for SMTP.

**CSV import.** Queued job; streams with `SplFileObject`; 500-row chunks; per-row validation; duplicates detected via `external_ref` (pre-check + unique index with `insertOrIgnore`); invalid rows stored in `import_errors` and downloadable.

**Performance.** Indexes match real queries (see the tickets migration); lists are paginated; relations are eager loaded; the dashboard counters come from a single conditional-aggregate query; a test guards against N+1.

## Testing strategy
Unit: business-hours calculator. Feature: ticket creation/SLA, numbering, authorization (including internal notes and file downloads), assignment + concurrency guard, escalation idempotency, CSV import, search/filters/N+1.
I focused on security boundaries and business rules; trivial CRUD and Blade rendering are covered indirectly.

## Assumptions and trade-offs
- One timezone (`APP_TIMEZONE`); no public holidays.
- SLA clock does not pause while waiting for the customer.
- Keyword search uses `LIKE` (clear and testable); I would move to full-text/Scout for large data.
- Agents can see all tickets but may only act on tickets assigned to them (or accept unassigned ones).
- Uploaded files are validated by content type and stored on the private disk.

## With more time
SLA pause states, holidays, per-category SLA, Redis + Horizon, full-text search, virus-scanning uploads, rate limiting, UI notifications centre, soft deletes.