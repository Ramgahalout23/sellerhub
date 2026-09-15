<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Platform;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Imports marketplace order reports (Amazon / Flipkart / Meesho CSV exports).
 *
 * Design goals:
 *  - Tolerant of column naming: a header is matched by alias, not by position.
 *  - Grouping: several rows that share an order number become one order with
 *    several line items (which is how marketplaces actually export).
 *  - Idempotent: re-importing the same report updates shipment status on orders
 *    that already exist instead of duplicating them.
 *  - No new dependencies and no staging tables: the file is parsed in memory and
 *    written through the same OrderService the manual form uses, so FIFO stock
 *    deduction, charges and platform payments behave identically.
 */
class OrderImportService
{
    /**
     * Hard cap so a giant file can't tie up a request. Split larger exports.
     */
    public const MAX_ROWS = 2000;

    /**
     * Canonical columns we understand, mapped to the normalised header aliases
     * that produce them.
     */
    public const ALIASES = [
        'order_number' => ['order_number', 'order_id', 'orderid', 'order_no', 'order_number_id', 'amazon_order_id', 'order_item_id', 'order_itemid'],
        'platform' => ['platform', 'marketplace', 'channel', 'site', 'source'],
        'sku' => ['sku', 'product_sku', 'seller_sku', 'sellersku', 'sku_code', 'item_sku', 'asin'],
        'quantity' => ['quantity', 'qty', 'units', 'item_quantity', 'order_item_quantity'],
        'selling_price' => ['selling_price', 'price', 'unit_price', 'item_price', 'selling_price_inr', 'item_price_inr', 'unit_price_inr'],
        'line_total' => ['line_total', 'total_price', 'item_total', 'gross', 'gross_amount', 'total_price_inr', 'order_total'],
        'customer_name' => ['customer_name', 'customer', 'buyer_name', 'buyer', 'ship_to_name', 'shipping_name', 'recipient_name'],
        'payment_mode' => ['payment_mode', 'payment_method', 'payment_type', 'mode_of_payment', 'cod_prepaid', 'payment_option'],
        'order_date' => ['order_date', 'date', 'purchase_date', 'invoice_date', 'order_datetime', 'order_date_time'],
        'status' => ['status', 'order_status', 'shipment_status', 'delivery_status'],
        'commission' => ['commission', 'commission_fee', 'referral_fee', 'platform_fee'],
        'shipping' => ['shipping', 'shipping_fee', 'shipping_charge', 'ship_fee', 'delivery_fee'],
        'closing' => ['closing', 'closing_fee', 'closing_charge', 'fixed_fee'],
        'gst' => ['gst', 'tax', 'gst_amount', 'tax_amount'],
        'other' => ['other', 'other_fee', 'other_charges', 'misc', 'misc_charges', 'packing_charge'],
    ];

    /**
     * Columns that count as "the file already told me the fees".
     */
    public const CHARGE_COLUMNS = ['commission', 'shipping', 'closing', 'gst', 'other'];

    public function __construct(
        protected OrderService $orders,
        protected PlatformChargeCalculator $charges,
    ) {}

    /**
     * The header line + example rows for the downloadable template.
     */
    public function template(): string
    {
        $headers = [
            'order_number', 'platform', 'sku', 'quantity', 'selling_price',
            'customer_name', 'payment_mode', 'order_date', 'status',
            'commission', 'shipping', 'closing', 'gst',
        ];

        $platform = Platform::query()->orderBy('name')->first()?->name ?? 'Amazon';
        $product = Product::query()->orderBy('sku')->first();

        $examples = [
            [$platform.'-1001', $platform, $product->sku ?? 'SKU-1', '1', '499', 'Asha Rao', 'prepaid', now()->toDateString(), 'shipped', '25', '45', '20', '16.20'],
            [$platform.'-1001', $platform, $product->sku ?? 'SKU-2', '2', '299', 'Asha Rao', 'cod', now()->toDateString(), 'shipped', '30', '45', '20', '17.10'],
        ];

        $lines = [$this->csvLine($headers)];
        foreach ($examples as $row) {
            $lines[] = $this->csvLine($row);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Parse and (unless $dryRun) import a report file.
     *
     * @return array{total_rows:int,orders_created:int,orders_updated:int,items_created:int,duplicates:int,auto_charged:bool,errors:array,skipped:array,warnings:array,order_ids:array}
     */
    public function import(string $path, bool $dryRun = false, ?int $defaultPlatformId = null): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $contents = (string) file_get_contents($path);
        if (str_contains($contents, "\0")) {
            throw new InvalidArgumentException(
                'This file looks like an Excel/UTF-16 export. Re-save it as "CSV UTF-8" and try again.'
            );
        }

        $rows = $this->readRows($path, $contents);
        [$map, $headerIndex] = $this->locateHeader($rows);

        $missing = array_values(array_diff(['order_number', 'sku', 'quantity'], array_keys($map)));
        if (! isset($map['selling_price']) && ! isset($map['line_total'])) {
            // A price column is only required when it can't be derived from a line total.
            $missing[] = 'selling_price (or line_total)';
        }
        if ($missing) {
            throw new InvalidArgumentException(
                'Missing required column(s): '.implode(', ', array_unique($missing)).
                '. Recognised headers were: '.implode(', ', array_keys($map)).'.'
            );
        }

        $result = [
            'total_rows' => 0,
            'orders_created' => 0,
            'orders_updated' => 0,
            'items_created' => 0,
            'duplicates' => 0,
            'auto_charged' => false,
            'errors' => [],
            'skipped' => [],
            'warnings' => [],
            'order_ids' => [],
            'dry_run' => $dryRun,
        ];

        $rows = array_slice($rows, $headerIndex + 1);
        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException(
                'This file has '.count($rows).' rows; the limit is '.self::MAX_ROWS.'. Please split it into smaller files.'
            );
        }

        $platforms = $this->platformLookup();
        $defaultPlatform = $defaultPlatformId ? $platforms->get($defaultPlatformId) : null;
        $autoCharge = ! $this->fileHasChargeColumns($map);

        $groups = $this->groupRows($rows, $map, $platforms, $defaultPlatform, $result, $autoCharge);

        $existing = $this->existingOrders($groups);

        foreach ($groups as $group) {
            $this->writeGroup($group, $existing, $result, $dryRun);
        }

        if ($autoCharge && $result['items_created'] + $result['orders_created'] > 0) {
            $result['auto_charged'] = true;
        }

        return $result;
    }

    // ────────────────────────────── parsing ──────────────────────────────

    /**
     * @return array{0: array<string,int>, 1: int}
     */
    protected function locateHeader(array $rows): array
    {
        $best = [];
        $bestIndex = 0;

        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $map = $this->mapHeaders($row);
            if (count($map) > count($best)) {
                $best = $map;
                $bestIndex = (int) $index;
            }
        }

        if (count($best) < 2) {
            throw new InvalidArgumentException(
                'No recognisable header row found. The first line should name your columns, e.g. '.
                'order_number, platform, sku, quantity, selling_price.'
            );
        }

        return [$best, $bestIndex];
    }

    /**
     * @return array<string,int> canonical column => index in the row
     */
    protected function mapHeaders(array $row): array
    {
        $map = [];

        foreach ($row as $index => $raw) {
            $key = $this->key($raw);
            if ($key === '') {
                continue;
            }
            foreach (self::ALIASES as $canonical => $aliases) {
                if (in_array($key, $aliases, true) && ! array_key_exists($canonical, $map)) {
                    $map[$canonical] = $index;
                }
            }
        }

        return $map;
    }

    protected function key(mixed $header): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $header)), '_');
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function readRows(string $path, string $contents): array
    {
        $delimiter = $this->detectDelimiter($contents);
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $rows = [];
        $first = true;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }
            if ($first) {
                $row[0] = $this->stripBom((string) ($row[0] ?? ''));
                $first = false;
            }
            $rows[] = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
        }
        fclose($handle);

        return $rows;
    }

    protected function detectDelimiter(string $contents): string
    {
        $line = '';
        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $candidate) {
            if (trim($candidate) !== '') {
                $line = $candidate;
                break;
            }
        }

        $counts = [
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
            ';' => substr_count($line, ';'),
        ];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    protected function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    // ──────────────────────────── validation ─────────────────────────────

    /**
     * Turn raw rows into validated order groups, collecting row-level problems.
     *
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string,int>  $map
     * @param  Collection<int, Platform>  $platforms
     */
    protected function groupRows(
        array $rows,
        array $map,
        Collection $platforms,
        ?Platform $defaultPlatform,
        array &$result,
        bool $autoCharge
    ): array {
        $skus = [];
        foreach ($rows as $row) {
            if ($sku = $this->cell($row, $map, 'sku')) {
                $skus[strtoupper($sku)] = true;
            }
        }
        $products = Product::query()
            ->whereIn('sku', array_keys($skus))
            ->get()
            ->keyBy(fn (Product $product) => strtoupper(trim((string) $product->sku)));

        $groups = [];

        foreach ($rows as $offset => $row) {
            $line = $offset + 2; // 1-based, and the header occupies line 1

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $result['total_rows']++;

            $orderNumber = $this->cell($row, $map, 'order_number');
            $sku = $this->cell($row, $map, 'sku');

            if ($orderNumber === '') {
                $result['errors'][] = ['line' => $line, 'message' => 'Row has no order number.'];

                continue;
            }
            if ($sku === '') {
                $result['errors'][] = ['line' => $line, 'message' => "Row for order {$orderNumber} has no SKU."];

                continue;
            }

            $product = $products->get(strtoupper($sku));
            if (! $product) {
                $result['errors'][] = ['line' => $line, 'message' => "Unknown SKU \"{$sku}\" — product not found."];

                continue;
            }

            $quantity = (int) $this->number($this->cell($row, $map, 'quantity'));
            if ($quantity < 1) {
                $result['errors'][] = ['line' => $line, 'message' => "Quantity must be at least 1 for SKU \"{$sku}\"."];

                continue;
            }

            $price = $this->number($this->cell($row, $map, 'selling_price'));
            if ($price <= 0 && ($lineTotal = $this->number($this->cell($row, $map, 'line_total'))) > 0) {
                $price = round($lineTotal / $quantity, 2);
            }
            if ($price < 0) {
                $result['errors'][] = ['line' => $line, 'message' => "Price cannot be negative for SKU \"{$sku}\"."];

                continue;
            }

            $platform = $this->resolvePlatform($row, $map, $platforms, $defaultPlatform);
            if (! $platform instanceof Platform) {
                $result['errors'][] = ['line' => $line, 'message' => $platform];

                continue;
            }

            $rawStatus = $this->cell($row, $map, 'status');
            if ($this->isCancelled($rawStatus)) {
                $result['skipped'][] = ['line' => $line, 'message' => "Order {$orderNumber} is cancelled — not imported."];

                continue;
            }

            $date = $this->parseDate($this->cell($row, $map, 'order_date'));
            $customer = $this->cell($row, $map, 'customer_name') ?: null;
            $status = $this->shipmentStatus($rawStatus);
            $paymentMode = $this->paymentMode($this->cell($row, $map, 'payment_mode'));
            $key = $platform->id.'|'.$orderNumber;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'order_number' => $orderNumber,
                    'platform' => $platform,
                    'customer_name' => $customer,
                    'date' => $date,
                    'status' => $status,
                    'payment_mode' => $paymentMode,
                    'first_line' => $line,
                    'items' => [],
                ];
            }

            // Later rows of the same order fill in anything the first row omitted.
            $groups[$key]['customer_name'] ??= $customer;
            $groups[$key]['date'] ??= $date;
            $groups[$key]['status'] ??= $status;
            $groups[$key]['payment_mode'] ??= $paymentMode;

            $charges = $autoCharge
                ? $this->charges->forAmount($platform, round($quantity * $price, 2))['charges']
                : $this->fileCharges($row, $map);

            $groups[$key]['items'][] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'selling_price' => $price,
                'charges' => $charges,
            ];
        }

        // Groups where every row failed validation simply disappear.
        return array_filter($groups, fn ($group) => $group['items'] !== []);
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string,int>  $map
     */
    protected function cell(array $row, array $map, string $column): string
    {
        if (! isset($map[$column])) {
            return '';
        }

        return trim((string) ($row[$map[$column]] ?? ''));
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function fileHasChargeColumns(array $map): bool
    {
        return (bool) array_intersect(self::CHARGE_COLUMNS, array_keys($map));
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string,int>  $map
     * @return array<int, array{charge_name: string, amount: float}>
     */
    protected function fileCharges(array $row, array $map): array
    {
        $charges = [];
        foreach (self::CHARGE_COLUMNS as $column) {
            $amount = round($this->number($this->cell($row, $map, $column)), 2);
            if ($amount > 0) {
                $charges[] = ['charge_name' => $column, 'amount' => $amount];
            }
        }

        return $charges;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string,int>  $map
     * @param  Collection<int, Platform>  $platforms
     * @return Platform|string the platform, or an error message
     */
    protected function resolvePlatform(array $row, array $map, Collection $platforms, ?Platform $defaultPlatform): Platform|string
    {
        $raw = $this->cell($row, $map, 'platform');

        if ($raw === '') {
            if ($defaultPlatform) {
                return $defaultPlatform;
            }

            return 'Row has no platform, and no default platform was selected.';
        }

        $normalised = strtolower(trim($raw));
        $match = $platforms->first(
            fn (Platform $platform) => strtolower($platform->name) === $normalised
                || strtolower((string) $platform->slug) === $normalised
        );

        if ($match) {
            return $match;
        }

        return "Unknown platform \"{$raw}\". Known platforms: ".$platforms->pluck('name')->implode(', ').'.';
    }

    /**
     * @return Collection<int, Platform>
     */
    protected function platformLookup(): Collection
    {
        return Platform::query()->get()->keyBy('id');
    }

    /**
     * Existing orders keyed by "platform|order_number" so re-imports update
     * instead of duplicating.
     *
     * @param  array<string, array>  $groups
     * @return Collection<string, Order>
     */
    protected function existingOrders(array $groups): Collection
    {
        if ($groups === []) {
            return collect();
        }

        $numbers = array_values(array_unique(array_map(fn ($group) => $group['order_number'], $groups)));
        $platformIds = array_values(array_unique(array_map(fn ($group) => $group['platform']->id, $groups)));

        return Order::query()
            ->whereIn('order_number', $numbers)
            ->whereIn('platform_id', $platformIds)
            ->get()
            ->keyBy(fn (Order $order) => $order->platform_id.'|'.$order->order_number);
    }

    // ──────────────────────────── writing ────────────────────────────────

    /**
     * @param  array<string, mixed>  $group
     * @param  Collection<string, Order>  $existing
     */
    protected function writeGroup(array $group, Collection $existing, array &$result, bool $dryRun): void
    {
        $key = $group['platform']->id.'|'.$group['order_number'];

        if ($existing->has($key)) {
            $this->updateExisting($existing->get($key), $group, $result, $dryRun);

            return;
        }

        $result['orders_created']++;
        $result['items_created'] += count($group['items']);

        if ($dryRun) {
            return;
        }

        $order = $this->orders->create([
            'order_number' => $group['order_number'],
            'platform_id' => $group['platform']->id,
            'customer_name' => $group['customer_name'],
            'payment_mode' => $group['payment_mode'] ?? Order::PAYMENT_MODE_PREPAID,
            'status' => Order::STATUS_CREATED,
            'notes' => 'Imported from report',
        ], $group['items']);

        if ($status = $group['status']) {
            $order = $this->orders->updateShipmentStatus($order, $status);
        }

        if ($group['date']) {
            // Backdate so the sale lands in the month it actually happened.
            $order->created_at = $group['date'];
            $order->saveQuietly();
        }

        $result['order_ids'][] = $order->id;
    }

    protected function updateExisting(Order $order, array $group, array &$result, bool $dryRun): void
    {
        $result['duplicates']++;

        // A report cannot un-cancel a sale the app already cancelled.
        if ($order->status === Order::STATUS_CANCELLED) {
            $result['skipped'][] = [
                'line' => $group['first_line'],
                'message' => "Order {$order->order_number} is cancelled here — the report's status was ignored.",
            ];

            return;
        }

        // A report is the source of truth for how the customer paid, so keep it in sync.
        if ($group['payment_mode'] && $group['payment_mode'] !== $order->payment_mode) {
            $result['orders_updated']++;
            if (! $dryRun) {
                $order->update(['payment_mode' => $group['payment_mode']]);
            }
        }

        if (! $group['status'] || $group['status'] === $order->status) {
            return;
        }

        $result['orders_updated']++;

        if ($dryRun) {
            return;
        }

        $this->orders->updateShipmentStatus($order, $group['status']);
    }

    // ───────────────────────────── helpers ───────────────────────────────

    protected function number(string $value): float
    {
        if ($value === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));

        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    protected function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $date->year >= 2000 && $date->year <= 2100 ? $date : null;
    }

    /**
     * Map a report's payment column onto our two modes. Reports say things like
     * "COD", "Cash on Delivery", "Prepaid", "Online", "Prepaid/Online".
     */
    protected function paymentMode(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'cod') || str_contains($value, 'cash')) {
            return Order::PAYMENT_MODE_COD;
        }

        if (str_contains($value, 'prepaid') || str_contains($value, 'pre-paid')
            || str_contains($value, 'online') || str_contains($value, 'upi')
            || str_contains($value, 'card') || str_contains($value, 'netbanking')) {
            return Order::PAYMENT_MODE_PREPAID;
        }

        return null;
    }

    protected function isCancelled(string $status): bool
    {
        return str_contains(strtolower($status), 'cancel');
    }

    protected function shipmentStatus(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'deliver')) {
            return Order::STATUS_DELIVERED;
        }
        if (str_contains($value, 'transit')) {
            return Order::STATUS_IN_TRANSIT;
        }
        if (str_contains($value, 'ship') || str_contains($value, 'dispatch')) {
            return Order::STATUS_SHIPPED;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function csvLine(array $values): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $values);
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return rtrim($line, "\r\n");
    }
}
