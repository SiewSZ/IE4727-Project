<?php
// Sales report for the Sales Report tab on admin.html (admins only).
//   GET sales_report.php?from=YYYY-MM-DD&to=YYYY-MM-DD
// Reads the orders saved by PHP/shopping_cart/checkout.php. MySQL groups and adds up the sales
// (GROUP BY with SUM and COUNT); PHP then works out the analytics: averages, each product's
// share of revenue, the change from the previous period, the best day and the days of stock left.
// Replies with JSON (see the respond() call at the bottom) or {"error": "..."}

require_once __DIR__ . "/../products/common.php";   // $conn, respond() and requireAdmin()
requireAdmin();

const MAX_DAYS = 366;
const RESTOCK_DAYS = 14;   // flag products that will sell out within this many days

// A YYYY-MM-DD date, or null if the text isn't a real date (e.g. 2026-02-31).
// UTC, so adding days never runs into daylight saving changes.
function parseDay($value)
{
    if (!is_string($value)) {
        return null;
    }
    $day = DateTimeImmutable::createFromFormat("!Y-m-d", $value, new DateTimeZone("UTC"));
    return ($day && $day->format("Y-m-d") === $value) ? $day : null;
}

// Runs a SELECT whose two ? marks are the start and end of a period and returns every row
function periodRows($conn, $sql, $start, $end)
{
    $startText = $start->format("Y-m-d");
    $endText = $end->format("Y-m-d");
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $startText, $endText);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

// Percentage change from the previous period, or null when there is nothing to compare with
function percentChange($now, $before)
{
    return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
}

$from = parseDay($_GET['from'] ?? null);
$to = parseDay($_GET['to'] ?? null);
if (!$from || !$to) {
    respond(400, ["error" => "Choose a valid From and To date."]);
}
if ($from > $to) {
    respond(400, ["error" => "The From date must be on or before the To date."]);
}
$days = $from->diff($to)->days + 1;
if ($days > MAX_DAYS) {
    respond(400, ["error" => "Choose a range of up to one year."]);
}

// Orders match when created_at >= start and < end, so end is the day after "To".
// The previous period has the same number of days and ends the day before "From".
$end = $to->modify("+1 day");
$previousStart = $from->modify("-$days days");

// The tables every sales query reads; each query adds its own SELECT and GROUP BY.
// Categories come from the product as it is now; deleted products show as "Removed products".
const SALES_FROM = "
    FROM order_items i
    JOIN orders o USING (order_id)
    LEFT JOIN products p ON p.product_id = i.product_id
    LEFT JOIN categories c ON c.category_id = p.category_id
    WHERE o.created_at >= ? AND o.created_at < ?";

try {
    // Totals for this period and the one before it
    $totalsSql = "SELECT COUNT(DISTINCT o.order_id) AS orders,
                         COALESCE(SUM(i.qty), 0) AS units,
                         COALESCE(SUM(i.qty * i.unit_price), 0) AS revenue" . SALES_FROM;
    $current = periodRows($conn, $totalsSql, $from, $end)[0];
    $previous = periodRows($conn, $totalsSql, $previousStart, $from)[0];

    // Sales by product, best seller (by revenue) first
    $productRows = periodRows($conn,
        "SELECT COALESCE(p.name, i.product_name) AS name,
                COALESCE(c.name, 'Removed products') AS category,
                p.stock,
                SUM(i.qty) AS units,
                SUM(i.qty * i.unit_price) AS revenue,
                COUNT(DISTINCT o.order_id) AS orders" . SALES_FROM . "
         GROUP BY i.product_id, COALESCE(p.name, i.product_name), c.name, p.stock
         ORDER BY revenue DESC, units DESC", $from, $end);

    // Sales by category
    $categoryRows = periodRows($conn,
        "SELECT COALESCE(c.name, 'Removed products') AS category,
                SUM(i.qty) AS units,
                SUM(i.qty * i.unit_price) AS revenue,
                COUNT(DISTINCT o.order_id) AS orders" . SALES_FROM . "
         GROUP BY category
         ORDER BY revenue DESC", $from, $end);

    // Sales per day (days without orders are filled in below)
    $dailyRows = periodRows($conn,
        "SELECT DATE(o.created_at) AS day,
                COUNT(DISTINCT o.order_id) AS orders,
                SUM(i.qty * i.unit_price) AS revenue" . SALES_FROM . "
         GROUP BY DATE(o.created_at)", $from, $end);

    // Products in the shop that sold nothing in this period
    $unsoldRows = periodRows($conn,
        "SELECT name, stock
         FROM products
         WHERE is_active = 1 AND product_id NOT IN (
             SELECT i.product_id
             FROM order_items i
             JOIN orders o USING (order_id)
             WHERE i.product_id IS NOT NULL AND o.created_at >= ? AND o.created_at < ?)
         ORDER BY name", $from, $end);
} catch (mysqli_sql_exception $e) {
    // 1146 = table doesn't exist: the database was made before orders were added to schema.sql
    if ($e->getCode() === 1146) {
        respond(500, ["error" => "The orders table is missing. Import database/schema.sql again in phpMyAdmin."]);
    }
    respond(500, ["error" => "The report could not be generated."]);
}
mysqli_close($conn);

// ---------- Analytics ----------
$revenue = (float) $current['revenue'];
$orders = (int) $current['orders'];
$units = (int) $current['units'];

$summary = [
    "revenue" => $revenue,
    "orders" => $orders,
    "units" => $units,
    "avgOrderValue" => $orders ? round($revenue / $orders, 2) : 0,
    "avgDailyRevenue" => round($revenue / $days, 2),
    "itemsPerOrder" => $orders ? round($units / $orders, 1) : 0,
    "revenueChange" => percentChange($revenue, (float) $previous['revenue']),
    "ordersChange" => percentChange($orders, (int) $previous['orders']),
    "unitsChange" => percentChange($units, (int) $previous['units'])
];

$products = [];
foreach ($productRows as $index => $row) {
    $productUnits = (int) $row['units'];
    $productRevenue = (float) $row['revenue'];
    $stock = $row['stock'] === null ? null : (int) $row['stock'];   // null = product was deleted
    // At the rate it sold in this period, how many days until it runs out
    $daysOfStock = $stock === null ? null : (int) floor($stock / ($productUnits / $days));

    $products[] = [
        "rank" => $index + 1,
        "name" => $row['name'],
        "category" => $row['category'],
        "units" => $productUnits,
        "orders" => (int) $row['orders'],
        "revenue" => $productRevenue,
        "share" => $revenue > 0 ? round($productRevenue / $revenue * 100, 1) : 0,
        "stock" => $stock,
        "daysOfStock" => $daysOfStock,
        "restockSoon" => $daysOfStock !== null && $daysOfStock < RESTOCK_DAYS
    ];
}

$categories = [];
foreach ($categoryRows as $row) {
    $categories[] = [
        "name" => $row['category'],
        "units" => (int) $row['units'],
        "orders" => (int) $row['orders'],
        "revenue" => (float) $row['revenue'],
        "share" => $revenue > 0 ? round($row['revenue'] / $revenue * 100, 1) : 0
    ];
}

// One entry for every day in the period, with 0 for days without orders
$byDay = array_column($dailyRows, null, "day");
$daily = [];
$bestDay = null;
foreach (new DatePeriod($from, new DateInterval("P1D"), $end) as $day) {
    $key = $day->format("Y-m-d");
    $entry = [
        "date" => $key,
        "orders" => (int) ($byDay[$key]['orders'] ?? 0),
        "revenue" => (float) ($byDay[$key]['revenue'] ?? 0)
    ];
    $daily[] = $entry;
    if ($entry['revenue'] > 0 && (!$bestDay || $entry['revenue'] > $bestDay['revenue'])) {
        $bestDay = $entry;
    }
}

// The product with the most units sold (the best seller by revenue is $products[0])
$mostUnits = null;
foreach ($products as $p) {
    if (!$mostUnits || $p['units'] > $mostUnits['units']) {
        $mostUnits = $p;
    }
}

$unsold = [];
foreach ($unsoldRows as $row) {
    $unsold[] = ["name" => $row['name'], "stock" => (int) $row['stock']];
}

respond(200, [
    "from" => $from->format("Y-m-d"),
    "to" => $to->format("Y-m-d"),
    "days" => $days,
    "previousFrom" => $previousStart->format("Y-m-d"),
    "previousTo" => $from->modify("-1 day")->format("Y-m-d"),
    "restockDays" => RESTOCK_DAYS,
    "summary" => $summary,
    "products" => $products,
    "categories" => $categories,
    "daily" => $daily,
    "bestDay" => $bestDay,
    "mostUnits" => $mostUnits,
    "unsold" => $unsold
]);
?>
