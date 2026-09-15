# Selling Hub

A small but complete inventory + sales bookkeeping app for online resellers who sell on
multiple marketplaces (Amazon, Flipkart, Meesho, …).

It answers the questions a seller actually asks:

- What did I buy, from which supplier, and at what cost? (batch orders + stock batches)
- What sold, on which platform, and what did the platform keep? (orders + per-item charges)
- What came back as a return / RTO / was lost? (returns with condition)
- Did I actually make money? (dashboard + accounting ledger, with FIFO cost of goods)

## Features

- **Products** — SKUs, pricing, supplier links, custom fields, low-stock view.
- **Suppliers** — contact details, purchase history, per-supplier batch order log.
- **Batch orders** — record a purchase lot; each line becomes a **StockBatch**. Attach one or
  more **supplier invoices** (PDFs or photos, e.g. a multi-page scan) to the purchase; they are
  stored on a private disk, shown as a thumbnail gallery on the batch order page, and streamed
  back through an authenticated route, so supplier pricing is never web-served.
- **Stock batches (FIFO)** — every sale is fulfilled from the oldest batch first, unless a
  specific batch is chosen on the order form. Batch→sale links are stored so cost and
  returns can be traced per supplier.
- **Orders** — multi-line orders; each line item moves through its own outcome
  (`pending`, `successful`, `customer_return`, `rto`, `missing`).
- **Auto-filled platform charges** — picking a platform on the order form pre-fills commission,
  shipping, closing and GST from that platform's charge structure, with a live
  "platform fees ≈ ₹X · net ≈ ₹Y" readout, so profit is right by construction instead of
  depending on remembering to type fees. Editing a charge by hand stops the auto-fill for that row.
- **CSV order import** — upload a marketplace order report instead of typing each order.
  Column names are matched loosely (`Order ID`, `order_id`, `Seller SKU`, `Qty`, …), rows sharing
  an order number become one multi-line order, SKUs are validated, stock is deducted FIFO, and
  missing fee columns are filled from the platform's charge structure. Re-importing the same
  report never duplicates an order — it only refreshes shipment status. A "check only" run
  reports what would happen without writing anything.
- **Returns** — sellable returns are restocked *to the exact batch they came from*;
  damaged / RTO / missing are treated as losses.
- **Accounting** — platform payments (auto from successful sales + manual payouts),
  general expenses, and a ledger with revenue, FIFO COGS, charges, return costs and net profit.
- **Insights** — supplier comparison per product, product rankings, health scores, time trends,
  and a **Marketplace Profit** comparison: revenue, fees, return rate, net profit and margin per
  platform, so you can see which channel is worth the ad spend.
- **Alerts** — scheduled low-stock and return-reminder alerts.

## Requirements

- PHP 8.2+
- Composer 2
- Node 18+ (for asset building)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install
npm run build
```

Run the app locally (server, queue listener, log tail and Vite together):

```bash
composer run dev
```

Seed the demo data (a seller, two platforms, suppliers, products, batch orders and expenses):

```bash
php artisan migrate:fresh --seed
```

The demo login is `ram@sellinghub.local` / `password`.

## Testing & code style

```bash
composer test                 # clears the config cache, then runs php artisan test
./vendor/bin/pint             # fix code style
./vendor/bin/pint --test      # check code style (what CI runs)
```

> **Note:** `composer test` clears the config cache first. If you run
> `php artisan test` directly while a cached `bootstrap/cache/config.php` exists,
> the cached `local` environment is used instead of the `testing` one and the
> suite will fail with CSRF (419) errors. Use `php artisan optimize:clear` if in doubt.

## Scheduled jobs

Registered in `routes/console.php`:

| Command | Schedule | Purpose |
| --- | --- | --- |
| `selling-hub:generate-alerts` | daily 09:00 | runs both checks below |
| `selling-hub:check-return-reminders` | every 6 hours | alert when a shipped order is overdue for a delivery check |
| `selling-hub:check-low-stock` | every 4 hours | alert when stock falls to/below the reorder threshold |
| `selling-hub:sync-stock-returns` | Mondays 08:00 | verify `products.stock_quantity` matches the sum of its stock batches; `--dry-run` to preview |

## Architecture

```
app/
  Http/Controllers   thin controllers
  Http/Requests      validation (FormRequests)
  Services           business logic (orders, FIFO, returns, accounting, insights)
  Repositories       query/persistence layer
  Models             Eloquent models
  Console/Commands   scheduled jobs
resources/views      Blade UI (Tailwind v4 via Vite)
```

The important invariants:

- Stock is tracked twice on purpose: `products.stock_quantity` (fast reads) and the sum of
  `stock_batches.remaining_quantity` (source of truth for FIFO, cost and supplier attribution).
  They must stay equal — `selling-hub:sync-stock-returns` verifies this.
- An order line item's outcome drives money: only `successful` items count as revenue and
  produce an automatic platform payment.
- Returns are attributed back to the batches that fulfilled the sale via `return_batches`.

## Importing marketplace order reports

The importer lives at `/orders/import` (linked from the Orders page) and accepts a CSV up to
4 MB / 2,000 rows. Export as **CSV UTF-8** — UTF-16/Excel `.xls` exports are rejected with a
clear message rather than being silently mis-read.

Recognised columns (case-, space- and dash-insensitive):

| Column | Also accepts |
| --- | --- |
| `order_number` *(required)* | order id, order no, amazon-order-id |
| `sku` *(required)* | seller sku, product sku, sku code, asin |
| `quantity` *(required)* | qty, units, item quantity |
| `selling_price` | price, unit price, item price, selling price (inr) |
| `line_total` | total price, item total, gross amount |
| `platform` | marketplace, channel, site |
| `customer_name` | buyer name, ship to name |
| `order_date` | date, purchase date, invoice date |
| `status` | order status, shipment status |
| `commission`, `shipping`, `closing`, `gst`, `other` | commission fee, referral fee, shipping fee, tax, misc charges |

Rules the importer follows:

- `selling_price` may be omitted when a `line_total` is present (unit price = total ÷ quantity).
- If the file has **no** fee columns, charges are generated from the platform's charge structure.
  If it has any, the file's numbers win.
- `order_date` backdates `orders.created_at` so sales land in the month they happened.
- Cancelled rows are skipped (a cancelled sale should not consume stock) and listed in the report.
- Rows that fail validation are reported with their line number; the rest still import.

The charge maths lives in `App\Services\PlatformChargeCalculator` — one implementation shared by
the order form, the importer and the `orders.charge-preview` endpoint.

## File storage

| What | Disk | Served by |
| --- | --- | --- |
| Product images | `public` (`storage/app/public`) | web server / `php artisan storage:link` |
| Supplier invoices | `invoices` (`storage/app/invoices`) | authenticated routes `batch-orders/{id}/invoices/{invoice}` |

The `invoices` disk is intentionally private (no `serve`, outside the web root). Files are stored
in the `batch_order_invoices` table (one row per file) and are removed from disk when an individual
invoice is deleted or when its batch order is deleted.
