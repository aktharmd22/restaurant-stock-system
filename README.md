# Restaurant stock system

One main store, several branches. Branches ask for stock, the main store
approves it, someone packs and sends it, the branch confirms what actually
arrived. It replaces doing all of that on WhatsApp and paper.

Built for **Hostinger shared hosting** — see [DEPLOY.md](DEPLOY.md).

---

## Running it locally

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# create the database first, then:
php artisan migrate --seed

npm run dev          # in one terminal
php artisan serve    # in another
```

Then sign in at http://localhost:8000 as any of these:

| Who | Phone | Password |
|---|---|---|
| Owner | `9000000001` | `password` |
| Main store admin | `9000000002` | `password` |
| Branch manager (Park Street) | `9000000003` | `password` |
| Branch staff (Park Street) | `9000000004` | `password` |

The seeder builds a month of history by running the real workflow, so the ledger,
the balances and every request are genuinely consistent — around 70 requests
through every state, 1,900 stock movements, waste, emergency buying and supplier
orders.

Run the tests with `php artisan test`.

---

## How it is put together

**The ledger is the truth.** Every stock movement writes one immutable row to
`stock_ledger`. `stock_balances` is a cache of it and can be thrown away:

```bash
php artisan stock:rebuild-balances --check   # do they agree?
php artisan stock:rebuild-balances           # make them agree
```

**Only `StockLedgerService` writes stock.** A test in `ArchitectureTest` fails
the build if anything else tries. A rule that is not tested is a rule that gets
broken in six months.

**Quantities are whole numbers of base units** — grams, millilitres, pieces.
1.5 kg is stored as `1500`. No floats anywhere near stock, so `SUM(qty_delta)`
equals `qty_on_hand` exactly, forever. A `Quantity` value object carries the
item it belongs to, so no service can be handed a bare `5` that means kilograms
in one place and grams in another.

**Approving reserves stock.** `available = on hand − reserved`, checked inside a
row lock, never against whatever the admin's screen was showing a minute ago.
Two branches can never be promised the same 20 kg.

**Four quantities per line** — asked, approved, sent, arrived. The gaps between
them are the whole point, and every report is built on them.

**Nothing is deleted.** Soft deletes and status changes only. A mistake is
corrected by writing an opposite movement with a reason.

### Where things live

```
app/
  Services/Stock/          the ledger, reservations, counting, reading stock
  Services/Requests/       the ask -> approve -> send -> receive state machine
  Services/Purchasing/     supplier orders and goods received
  Services/Reports/        one definition per report, drives screen + Excel + PDF
  Support/Quantity.php     the value object that stops unit bugs
resources/js/
  Components/ui/           the design system
  Composables/             sound, live updates, offline queue, toasts
  Pages/Branch/            five screens, phone first
  Pages/Admin/             laptop first, works on a phone
```

`/design` is a live reference of every part the screens are built from.

---

## Things worth knowing

**Reverb, Redis and Horizon are not used.** None of them can run on shared
hosting. Broadcasting goes through Pusher when credentials exist and falls back
to a 12-second poll when they do not — same interface either way, and one `.env`
line switches to Reverb if this ever moves to a VPS.

**Alert sounds are synthesised in the browser**, not loaded as audio files.
Nothing to download on mobile data and nothing that can 404 into silence.

**The cut-off never blocks anything.** A branch can ask for stock at any hour,
as many times a day as it needs. Being past the cut-off only marks a request
Late and pins it to the top of the admin's list.

**Branch scoping is enforced twice** — a global query scope and a policy on
every action. Asking for another branch's request returns 404 rather than 403,
because a 403 confirms the record exists.

---

## Still to do

- **Consumption** is currently recorded by stock counts, and the demo seeder
  writes it directly. A POS or recipe integration would deduct it per dish,
  which is the real answer.
- **Batch tracking** is not implemented. Perishables get a use-by date derived
  from the last delivery plus shelf life, which answers "what needs using
  first" without a lot table.
- **SMS** runs on a log driver until a provider is connected.
- **The offline retry queue** covers sending a stock request. Confirming a
  delivery and recording waste still need a connection.
