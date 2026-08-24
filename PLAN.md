# Restaurant multi-branch stock system — Phase 0 plan

Target host: **Hostinger shared hosting**. Local dev: XAMPP (PHP 8.2.12, MariaDB 10.4), Node 24, Composer 2.7.7.
Status: awaiting approval. No application code written yet.

---

## 1. Stack — what changed and why

Shared hosting cannot run a persistent process, cannot open a non-HTTP port, and has no `pcntl`.
Three items in the brief are physically impossible there. Everything else stays exactly as written.

| Brief says | On Hostinger shared | Replacement |
|---|---|---|
| Laravel Reverb | Needs a daemon on port 8080 | **Pusher Channels** (hosted). Same `laravel/echo` client, same private channels, same events. Free tier: 200k msg/day, 100 connections. |
| Redis + Horizon | No Redis service; Horizon needs `pcntl` | **`database` queue driver** + one cron running `queue:work --stop-when-empty --max-time=55` |
| Reverb private channels | — | Unchanged — Laravel broadcast auth works identically with Pusher |

Everything else is unchanged: Laravel 11, Inertia 2 + Vue 3 `<script setup>`, Tailwind 3, Vite,
Breeze, spatie permission / medialibrary / activitylog, dompdf, maatwebsite/excel.

**Broadcasting is written driver-agnostic.** `config/broadcasting.php` keeps both a `pusher` and a
`reverb` connection; only `.env` decides. If you move to a VPS later, one env line switches to Reverb
with no code change. If broadcast credentials are absent, the frontend falls back to a **12-second
Inertia poll** — the app still works, just without instant push.

**Cron jobs on Hostinger** (hPanel → Advanced → Cron Jobs):

    * * * * *  cd ~/domains/SITE/app && php artisan schedule:run
    * * * * *  cd ~/domains/SITE/app && php artisan queue:work --stop-when-empty --max-time=55 --tries=3

**The core loop never touches the queue.** Ask → approve → dispatch → receive all run synchronously
inside the request. Queues carry only PDFs, Excel exports, image conversions and the 30-minute
escalation. So even a 15-minute cron interval cannot slow down the two people who use this app.

**The 5-minute nag re-alert is client-side** (a JS timer in the admin's browser), not a cron job.
It keeps working regardless of hosting plan.

**Deployment:** `npm run build` runs locally and `public/build` is uploaded — Hostinger shared has no
reliable Node. The Laravel app sits outside the web root at `~/domains/SITE/app`, and `public_html`
holds the contents of `public/` with a two-line path edit in `index.php`. Storage is symlinked, or
mirrored by an artisan command if symlinks are blocked.

---

## 2. Database migrations

Order, each its own migration file:

1. Laravel base — users, sessions, cache, **jobs**, failed_jobs, password_reset_tokens
2. spatie permission tables
3. `branches`
4. alter `users` — branch_id, phone, is_active, sound_enabled, sound_volume, last_login_at, soft deletes
5. `categories`, `items`
6. `branch_item_settings`
7. `stock_ledger`, `stock_balances`
8. `requests`, `request_lines`
9. `dispatch_notes`, `receipt_discrepancies`
10. `suppliers`, `purchase_orders`, `purchase_order_lines`
11. `wastage`, `stock_counts`, `stock_count_lines`, `local_purchases`
12. `number_sequences`, notifications, activity_log, media

### Two type decisions that prevent whole classes of bugs

**Quantities are signed BIGINT in base units. No floats anywhere.** 1.5 kg is stored as `1500` grams.
A float quantity in a ledger accumulates rounding error and the balances stop matching. Integers keep
`SUM(qty_delta) = qty_on_hand` exact, forever. Consequence: the base unit must be the smallest real
unit (g / ml / piece), and you cannot hold half a piece.

**Money is `DECIMAL(14,4)` for unit costs, `DECIMAL(14,2)` for totals.** Never float.

### Additions to your schema — 6 columns, 1 table, none of them optional

| Where | Add | Because |
|---|---|---|
| `branches` | `cutoff_time` TIME default 18:00, `timezone` | §9.6 needs a per-branch cut-off and the countdown must be right at the branch, not on the server — the table had nowhere to store either |
| `items` | `storage_location` | §10.3 pack list is "grouped by storage location"; that field did not exist |
| `users` | `sound_volume` | §11 specifies a volume slider; only `sound_enabled` existed |
| `requests` | `is_late`, `cutoff_at` | §9.6 Late flag, snapshotted at send time so it cannot drift if the cut-off is edited later |
| `request_lines` | `reason_code` | §9.5 requires a reason from a fixed list; only free-text `admin_note` existed |
| new table | `number_sequences (key, next_value)` | Gap-free REQ / PO / DN numbers under concurrency, without a `MAX()+1` race |

### Indexes and constraints that matter

- `stock_ledger` — `index(branch_id, item_id, id)` for the rebuild, `index(branch_id, created_at)` for reports
- `stock_ledger` — **`unique(branch_id, item_id, movement_type, reference_type, reference_id)`**. This is §9.8: a double-tapped approve or a retried dispatch hits a duplicate-key error instead of moving stock twice.
- `stock_balances` — `unique(branch_id, item_id)`
- `requests.request_number` unique; `index(to_branch_id, status)`; `index(from_branch_id, status)`
- Soft deletes on branches, users, items, categories, suppliers (§9.7). No hard delete anywhere.

---

## 3. Service classes

Controllers stay thin: validate, authorize, call one service, return an Inertia response.

**`StockLedgerService`** — the only class permitted to write `stock_ledger` or `stock_balances`.
Every movement goes through `record(LedgerMovement $m)`, which in one transaction locks the balance
row with `lockForUpdate()`, computes `balance_after`, inserts the ledger row, updates the cached
balance. Named helpers on top: `purchase`, `transferOut`, `transferIn`, `wastage`, `adjustment`,
`returnStock`, `consumption`.

**`ReservationService`** — `reserve()`, `release()`, `consume()`. Available = `qty_on_hand − qty_reserved`.
Same lock discipline. Approval reserves; dispatch converts the reservation into a real `transfer_out`;
cancellation releases it.

**`Quantity` value object + `UnitConverter`** — a `Quantity` holds an integer and an `Item`. Services
accept `Quantity`, never `int`, never `float`. `Quantity::fromOrderUnit(2.5, $item)` → 2500 g;
`->forDisplay()` → "2.5 kg". This turns the §9.2 bug — a `5` that means kg in one place and g in
another — into a type error instead of silent corruption.

**`RequestWorkflowService`** — `submit`, `approve(array $lineDecisions)`, `dispatch`, `receive`,
`cancel`. Owns the state machine, refuses illegal transitions, derives request status from line
statuses (§9.5), enforces the §9.4 ladder `received ≤ sent ≤ approved ≤ requested`.

**`CutoffService`** — late decision, next cut-off, countdown payload.
**`LowStockService`** — running-low list; suggested qty = par level − on hand.
**`SequenceService`** — gap-free document numbers.
**`PackListBuilder`**, **`DispatchNoteGenerator`** (dompdf), **`ReportService`** + Excel/PDF exporters.
**`AlertService`** — one call fans out to broadcast + database notification + escalation.

**Enforcement:** an architecture test asserts no class outside `StockLedgerService` writes the
`StockLedger` or `StockBalance` models. The rule is only real if it is tested.

---

## 4. Vue structure

    Layouts/
      GuestLayout.vue      split-screen login
      BranchLayout.vue     sticky header + 4-item bottom nav + safe-area padding
      AdminLayout.vue      sidebar; collapses to bottom nav under 768px

    Components/ui/
      SpineCard.vue        THE signature element — 4px status-coloured left spine
      StatusPill.vue       colour + icon + word, always all three (§4 colour-blind rule)
      QtyStepper.vue       − / number / + , 48px targets, long-press repeat, tap for number pad
      AppButton  TextField  BottomSheet  ToastHost
      StatCard  CategoryChips  ItemRow  EmptyState
      Skeleton  CountdownTimer  OfflineBanner  PhotoCapture
      ResponsiveTable      table on desktop, stacked SpineCards under 768px

    Composables/
      useSound         silent-clip unlock on first click, 7 events, volume, navigator.vibrate
      useRealtime      Echo subscribe, or poll fallback when no broadcast key
      useOfflineQueue  localStorage queue + retry on reconnect
      useTabBadge  useToast  useNagTimer  useQty

    Pages/
      Auth/     Login  ForgotPassword  VerifyOtp
      Branch/   Home  AskForStock  MyRequests  RequestDetail  Receive  More/*    (5, bottom nav)
      Admin/    Dashboard  Requests/Inbox  Dispatch/*  Stock/*  Purchase/*  Reports/*  Settings/*

Design tokens live in `tailwind.config.js` only — the ten colours from §4 as named tokens
(`bg-surface`, `text-secondary`, `border-line`, `bg-waiting`…). No raw hex inside a component, ever.
No `dark:` class anywhere. DM Sans 400/500/700 self-hosted rather than loaded from Google's CDN, so a
slow Indian mobile connection does not wait on a third-party round trip.

---

## 5. The three riskiest parts

**1. Concurrent approval against the same stock (§9.3).** Two admins approving 20 kg each when 25 kg
exists is the failure that destroys trust in the whole system. Mitigation: all reservation writes go
through one service; `lockForUpdate()` on `stock_balances`; rows always locked **in ascending item_id
order**, so two multi-line approvals cannot deadlock each other; availability re-checked *inside* the
lock, never trusted from the page the admin was looking at; a feature test that fires two approvals
at one balance and asserts exactly one succeeds. Shared hosting raises the stakes — a slow request
holds locks longer — so the locked section does no PDF work, no HTTP calls, no mail.

**2. Units (§9.2).** The classic silent corrupter. Mitigation is the `Quantity` value object above,
plus a rule that no service signature accepts a bare number for a quantity. Tests cover kg↔g,
litre↔ml, sack↔piece, and a round-trip property test.

**3. Real-time and sound without Reverb.** A new risk, created by the hosting move. Browsers block
audio until a user gesture, and a sound that fails silently means a missed request — the exact
problem this app exists to solve. Mitigation: unlock audio with a silent clip on the first click
after login; persist the unlock state; show a visible "sound off" marker in the header whenever audio
is blocked or disabled, so the admin is never falsely reassured; and never rely on sound alone —
every event also writes a database notification, a toast and a tab badge. Plus the client-side
5-minute nag, which is the single feature that stops requests being missed.

---

## 6. Where I disagree with the brief

**a. There is no source of consumption.** The schema has `movement_type: consumption`, but nothing in
the seven phases ever creates one. Purchases and transfers add stock to a branch; only waste removes
any. So branch on-hand climbs forever and "8 kg left here" becomes fiction within a week — which
breaks the suggested quantities, the running-low list and every report. This is a business rule, so I
am not inventing it. Three options:

1. Daily closing stock count — accurate, but 60 items counted per branch per day. Nobody will do it.
2. **Weekly count, and the difference is booked as consumption**, with waste recorded separately as it happens. Realistic effort, good enough numbers. *(recommended)*
3. POS / recipe integration deducting per dish — the correct answer, and far outside this build.

The ledger will support all three; I will ship (2) unless you say otherwise.

**b. §12 "every destructive action is undoable" collides with §9.7 "nothing is deleted."** For stock
movements, undo cannot mean deletion — it has to write a reversing ledger row with a reason. So: the
10-second undo after sending a request stays exactly as specified, and fails gracefully in plain
words if the admin already opened it. Anything that has already moved stock gets a
reversal-with-reason instead of a silent undo.

**c. SMS OTP and the WhatsApp fallback both need a paid third-party account.** WhatsApp Business API
also needs a BSP and template approval — weeks, not hours. I will build the escalation behind a
driver interface with a `log` driver, so the flow is complete and testable now and MSG91 or Twilio
drops in the day you have credentials. Meanwhile forgot-password falls back to "ask your admin to
reset it", which costs nothing and matches how these teams actually work.

**d. Pagination on the Ask-for-stock screen (§12).** 60 items is not 5,000, and paginating that
screen adds taps to the one flow that must take 30 seconds. I am loading all active items for the
branch in one payload and filtering on the client. Every other list is paginated as you asked.
Choosing the branch user's speed, per §14.

**e. No extra UI libraries.** Everything in §10 is buildable with Tailwind and Lucide, and each extra
dependency is weight on an old Android phone over mobile data.

---

## 7. Assumptions — say the word and I will change any of them

- Currency ₹ INR, timezone Asia/Kolkata, dates DD/MM/YYYY
- Tests in **Pest 3**; the locking tests run against MariaDB/MySQL, not SQLite, because SQLite cannot
  reproduce `lockForUpdate`
- `git init` in this folder at the start of Phase 1, then one commit per phase
- Local MariaDB 10.4 vs Hostinger MySQL 8 — I stay inside the SQL subset both support and flag
  anything version-sensitive
- Branch users never see another branch's data: a global scope **and** a policy on every action, with
  a test proving a branch manager who edits the URL gets 403

---

## 8. What I need from you before Phase 1

1. **Approve or amend this plan.**
2. **Consumption rule** — option 1, 2 or 3 from §6a. Default: option 2.
3. **Pusher account?** Yes → instant push and sound. No → a 12-second poll and the same UI with a
   slightly slower alert. Default: build for both, ship with polling, switch on with one env line.
4. **Which Hostinger plan?** It sets the minimum cron interval and whether you have SSH. Not a
   blocker — the core loop does not depend on cron either way.
5. **Restaurant name** for the login screen and PDFs. Placeholder until you give it.
